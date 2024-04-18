<?php

namespace Overtrue\LaravelOpenTelemetry;

use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\TraceAttributes;

class Tracer
{
    public function __construct(
        protected string $name,
        protected TracerProviderInterface $tracerProvider,
        protected LoggerProviderInterface $loggerProvider,
        protected ?TextMapPropagatorInterface $textMapPropagator = null,
        protected array $watchers = [],
    ) {
    }

    public function setWatchers(array $watchers): static
    {
        $this->watchers = $watchers;

        return $this;
    }

    public function start(Application $app): void
    {
        $textMapPropagator = $this->textMapPropagator ?? TraceContextPropagator::getInstance();

        Sdk::builder()
            ->setTracerProvider($this->tracerProvider)
            ->setLoggerProvider($this->loggerProvider)
            ->setPropagator($textMapPropagator)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        $instrumentation = new CachedInstrumentation(
            name: 'laravel-open-telemetry',
            version: class_exists(InstalledVersions::class) ? InstalledVersions::getPrettyVersion('overtrue/laravel-open-telemetry') : null,
            schemaUrl: TraceAttributes::SCHEMA_URL,
        );

        $app->bind(TextMapPropagatorInterface::class, fn () => $this->textMapPropagator);
        $app->bind(TracerInterface::class, fn () => $instrumentation->tracer());
        $app->bind(LoggerInterface::class, fn () => $instrumentation->logger());

        foreach ($this->watchers as $watcher) {
            $app->make($watcher)->register($app);
        }

        $app->terminating(function () {
            $this->tracerProvider->forceFlush();
            $this->loggerProvider->forceFlush();
        });
    }
}
