<?php

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Logs\Exporter\ConsoleExporterFactory;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporterFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
use RuntimeException;

class TracerFactory
{
    protected Repository $config;

    public function __construct(protected Application $app)
    {
        $this->config = $this->app['config'];
    }

    public function create(string $name): Tracer
    {
        $config = $this->getTracerConfig($name);
        $exporter = $this->createSpanExporter($config);
        $processor = $this->createSpanProcessor($exporter, $config);
        $resource = $this->createResourceInfo($config);
        $sampler = $this->createSampler($config);

        $logExporter = $this->createLogsExporter($config);
        $logProcessor = new BatchLogRecordProcessor(
            exporter: $logExporter,
            clock: ClockFactory::getDefault()
        );

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($logProcessor)
            ->build();

        $traceProvider = TracerProvider::builder()
            ->setResource($resource)
            ->addSpanProcessor($processor)
            ->setSampler($sampler)
            ->build();

        $watchers = $config->get('watchers', $this->config->get('otle.global.watchers', []));

        return (new Tracer($name, $traceProvider, $loggerProvider))->setWatchers($watchers);
    }

    public function getTracerConfig($name): Repository
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

        return match (true) {
            is_subclass_of($processor, BatchSpanProcessor::class),
                $processor == BatchSpanProcessor::class => (new BatchSpanProcessorBuilder($exporter))->build(),
            default => new $processor($exporter),
        };
    }

    public function createSampler(Repository $config): SamplerInterface
    {
        $sampler = $config->get('sampler', AlwaysOnSampler::class);

        return new ParentBased(new $sampler);
    }

    protected function createLogsExporter(Repository $config): LogRecordExporterInterface
    {
        $logsExporter = $config->get('logs.exporter', 'memory');

        return match ($logsExporter) {
            'otlp' => new LogsExporter($this->createTransport(new Repository([
                'transport' => 'stream',
                'endpoint' => storage_path('logs/otel.log'),
            ]))),
            'console' => (new ConsoleExporterFactory())->create(),
            default => (new InMemoryExporterFactory())->create()
        };
    }
}
