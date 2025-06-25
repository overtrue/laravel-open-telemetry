<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransport;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Overtrue\LaravelOpenTelemetry\Support\GuzzleTraceMiddleware;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter as OtlpSpanExporter;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\SdkBuilder;
use OpenTelemetry\API\Trace\TracerInterface as Tracer;
use Psr\Log\LoggerInterface;
use Psr\Log\Logger;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otel.php' => $this->app->configPath('otel.php'),
        ], 'config');

        // Check if OpenTelemetry is enabled
        if (!config('otel.enabled', true)) {
            Log::debug('[laravel-open-telemetry] disabled, skipping registration');
            return;
        }

        Log::debug('[laravel-open-telemetry] started', config('otel'));

        // Register Guzzle trace macro
        PendingRequest::macro('withTrace', function () {
            /** @var PendingRequest $this */
            return $this->withMiddleware(GuzzleTraceMiddleware::make());
        });

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

        $this->app->singleton(\Overtrue\LaravelOpenTelemetry\Support\Measure::class, function ($app) {
            return new \Overtrue\LaravelOpenTelemetry\Support\Measure($app);
        });

        $this->app->alias(\Overtrue\LaravelOpenTelemetry\Support\Measure::class, 'opentelemetry.measure');

        $this->app->singleton(Tracer::class, function () {
            return Globals::tracerProvider()
                ->getTracer(config('otel.tracer_name', 'overtrue.laravel-open-telemetry'));
        });
        $this->app->alias(Tracer::class, 'opentelemetry.tracer');

        Log::debug('[laravel-open-telemetry] registered.');
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
        } elseif (! $this->app->runningInConsole() && ! $this->app->environment('testing')) {
            // FPM mode: Only start in non-console and non-testing environments
            // Initialize context at the beginning of the request
            if (! Context::getCurrent()) {
                Context::attach(Context::getRoot());
            }
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
                \Overtrue\LaravelOpenTelemetry\Console\Commands\TestCommand::class,
            ]);
        }
    }

    /**
     * Register middlewares
     */
    protected function registerMiddlewares(): void
    {
        $router = $this->app->make('router');

        // Register OpenTelemetry root span middleware
        $router->aliasMiddleware('otel', \Overtrue\LaravelOpenTelemetry\Http\Middleware\OpenTelemetryMiddleware::class);

        // Automatically add root span middleware in non-Octane mode (must be at the front)
        if (!Measure::isOctane()) {
            Log::debug('[laravel-open-telemetry] registering OpenTelemetryMiddleware globally');
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            $kernel->prependMiddleware(\Overtrue\LaravelOpenTelemetry\Http\Middleware\OpenTelemetryMiddleware::class);
        }

        // Register Trace ID middleware
        if (config('otel.middleware.trace_id.enabled', true)) {
            // Register middleware alias
            $router->aliasMiddleware('otel.trace_id', \Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceIdMiddleware::class);

            // Enable TraceId middleware globally by default
            if (config('otel.middleware.trace_id.global', true)) {
                $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
                $kernel->pushMiddleware(\Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceIdMiddleware::class);
            }
        }
    }
}
