<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Metrics\MeterProviderFactory;
use OpenTelemetry\SDK\Resource\Detectors\Sdk;
use Overtrue\LaravelOpenTelemetry\Console\Commands\TestCommand;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\AddTraceId;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceRequest;
use Overtrue\LaravelOpenTelemetry\Support\Metric;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otel.php' => $this->app->configPath('otel.php'),
        ], 'config');

        // Check if OpenTelemetry is enabled
        if (! config('otel.enabled', true)) {
            return;
        }

        $this->registerCommands();
        $this->registerWatchers();
        $this->registerLifecycleHandlers();
        $this->registerMiddlewares();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otel.php', 'otel',
        );

        // Check if OpenTelemetry is enabled
        if (! config('otel.enabled', true)) {
            return;
        }

        // Register Tracer
        $this->app->singleton(Support\Measure::class, function ($app) {
            return new Support\Measure($app);
        });

        $this->app->alias(Support\Measure::class, 'opentelemetry.measure');

        $this->app->singleton(TracerInterface::class, function () {
            return Globals::tracerProvider()
                ->getTracer(config('otel.tracer_name', 'overtrue.laravel-open-telemetry'));
        });

        $this->app->alias(TracerInterface::class, 'opentelemetry.tracer');

        // Register metric
        $this->app->singleton(Support\Metric::class, function ($app) {
            return new Support\Metric($app);
        });
        $this->app->alias(Support\Metric::class, 'opentelemetry.metric');

        // register custom meter
        $this->app->singleton(MeterInterface::class, function () {
            $resourceInfo = (new Sdk)->getResource();
            $meterProvider = (new MeterProviderFactory)->create($resourceInfo);
            ShutdownHandler::register($meterProvider->shutdown(...));
            Metric::setProvider($meterProvider);

            return $meterProvider->getMeter(config('otel.meter_name', 'overtrue.laravel-open-telemetry'));
        });

        $this->app->alias(MeterInterface::class, 'opentelemetry.meter');

        Log::debug('[laravel-open-telemetry] Service provider registered successfully');
    }

    /**
     * Register lifecycle handlers
     */
    protected function registerLifecycleHandlers(): void
    {
        if (Measure::isOctane()) {
            // Octane mode: Listen to Octane events
            Event::listen(Events\RequestReceived::class, Handlers\RequestReceivedHandler::class);
            Event::listen(Events\RequestTerminated::class, Handlers\RequestTerminatedHandler::class);
            Event::listen(Events\RequestHandled::class, Handlers\RequestHandledHandler::class);
            Event::listen(Events\WorkerStarting::class, Handlers\WorkerStartingHandler::class);
            Event::listen(Events\WorkerErrorOccurred::class, Handlers\WorkerErrorOccurredHandler::class);
            Event::listen(Events\TaskReceived::class, Handlers\TaskReceivedHandler::class);
            Event::listen(Events\TickReceived::class, Handlers\TickReceivedHandler::class);
        }
    }

    /**
     * Register Watchers
     */
    protected function registerWatchers(): void
    {
        $watchers = config('otel.watchers', []);

        foreach ($watchers as $watcherClass) {
            if (class_exists($watcherClass)) {
                /** @var \Overtrue\LaravelOpenTelemetry\Watchers\Watcher $watcher */
                $watcher = $this->app->make($watcherClass);
                $watcher->register($this->app);
            }
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestCommand::class,
            ]);
        }
    }

    /**
     * Register middlewares
     */
    protected function registerMiddlewares(): void
    {
        $router = $this->app->make('router');
        $kernel = $this->app->make(Kernel::class);

        // Register OpenTelemetry root span middleware
        $router->aliasMiddleware('otel', TraceRequest::class);

        $kernel->prependMiddleware(TraceRequest::class);

        // Register Trace ID middleware
        if (config('otel.middleware.trace_id.enabled', true)) {
            // Register middleware alias
            $router->aliasMiddleware('otel.trace_id', AddTraceId::class);

            // Enable TraceId middleware globally by default
            if (config('otel.middleware.trace_id.global', true)) {
                $kernel->pushMiddleware(AddTraceId::class);
                Log::debug('[laravel-open-telemetry] Middleware registered globally for automatic tracing');
            }
        }
    }
}
