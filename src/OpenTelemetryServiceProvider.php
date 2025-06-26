<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface as Tracer;
use OpenTelemetry\Context\Context;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

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
        if (! config('otel.enabled', true)) {
            Log::debug('OpenTelemetry: Service provider registration skipped - OpenTelemetry is disabled');

            return;
        }

        Log::debug('OpenTelemetry: Service provider initialization started', [
            'config' => config('otel'),
        ]);

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

        Log::debug('OpenTelemetry: Service provider registered successfully');
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
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        // Register OpenTelemetry root span middleware
        $router->aliasMiddleware('otel', \Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceRequest::class);

        $kernel->prependMiddleware(\Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceRequest::class);

        // Register Trace ID middleware
        if (config('otel.middleware.trace_id.enabled', true)) {
            // Register middleware alias
            $router->aliasMiddleware('otel.trace_id', \Overtrue\LaravelOpenTelemetry\Http\Middleware\AddTraceId::class);

            // Enable TraceId middleware globally by default
            if (config('otel.middleware.trace_id.global', true)) {
                $kernel->pushMiddleware(\Overtrue\LaravelOpenTelemetry\Http\Middleware\AddTraceId::class);
                Log::debug('OpenTelemetry: Middleware registered globally for automatic tracing');
            }
        }
    }
}
