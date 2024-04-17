<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Config\Repository;
use Illuminate\Support\Manager;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use RuntimeException;

class TracerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'console';
    }

    public function createConsoleDriver(): TracerProviderInterface
    {
        return $this->createTraceProvider('console');
    }

    public function createLogDriver(): TracerProviderInterface
    {
        return $this->createTraceProvider('log');
    }

    public function createHttpJsonDriver(): TracerProviderInterface
    {
        return $this->createTraceProvider('http-json');
    }

    public function createHttpBinaryDriver(): TracerProviderInterface
    {
        return $this->createTraceProvider('http-binary');
    }

    public function createGrpcDriver(): TracerProviderInterface
    {
        return $this->createTraceProvider('grpc');
    }

    public function createTraceProvider($name, TransportInterface $transport = null): TracerProviderInterface
    {
        $config = $this->getDriverConfig($name);
        $exporter = $this->createSpanExporter($config);
        $processor = $this->createSpanProcessor($exporter, $config);
        $resource = $this->createResourceInfo($config);
        $sampler = $this->createSampler($config);

        return TracerProvider::builder()
            ->setResource($resource)
            ->addSpanProcessor($processor)
            ->setSampler($sampler)
            ->build();
    }

    public function getDriverConfig($name): Repository
    {
        $default = $this->config->get('otle.global');
        $driver = $this->config->get("otle.tracers.{$name}");

        return new Repository(array_merge($default, $driver));
    }

    public function createResourceInfo(Repository $config): ResourceInfo
    {
        $attributes = array_merge([
            ResourceAttributes::SERVICE_NAME => $this->config->get('app.name'),
            ResourceAttributes::SERVICE_VERSION => $this->config->get('app.version'),
        ], $config->get('resource.attributes', []));

        return ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create($attributes)));
    }

    public function createTransport(Repository $config): TransportInterface
    {
        $factory = Registry::transportFactory($config->get('transport'));

        return $factory->create(
            endpoint: $config->get('endpoint', 'php://stdout'),
            contentType: $config->get('content_type', 'application/json'),
            headers: $config->get('headers', []),
            compression: $config->get('compression', null),
            timeout: $config->get('timeout', 10),
            retryDelay: $config->get('retry_delay', 100),
            maxRetries: $config->get('max_retries', 3),
            cacert: $config->get('cacert'),
            cert: $config->get('cert'),
            key: $config->get('key')
        );
    }

    public function createSpanExporter(Repository $config): SpanExporterInterface
    {
        return match ($config->get('span_exporter')) {
            'otlp' => new SpanExporter($this->createTransport($config)),
            'memory' => new InMemoryExporter(),
            'console' => new ConsoleSpanExporter($this->createTransport($config)),
            default => throw new RuntimeException('Unsupported span exporter.'),
        };
    }

    public function createSpanProcessor(SpanExporterInterface $exporter, Repository $config): SpanProcessorInterface
    {
        $processor = $config->get('span_processor');

        if (! is_subclass_of($processor, SpanProcessorInterface::class)) {
            throw new RuntimeException(sprintf('The span processor must be an instance of %s.', SpanProcessorInterface::class));
        }

        return new $processor($exporter);
    }

    public function createSampler(Repository $config): SamplerInterface
    {
        $sampler = $config->get('sampler', AlwaysOnSampler::class);

        return new ParentBased(new $sampler);
    }
}
