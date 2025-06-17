<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest;
use Overtrue\LaravelOpenTelemetry\Support\GuzzleTraceMiddleware;
use Overtrue\LaravelOpenTelemetry\Support\Measure;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otel.php' => $this->app->configPath('otel.php'),
        ], 'config');

        if (config('otel.enabled') === false) {
            return;
        }

        Log::debug('[laravel-open-telemetry] started', config('otel'));

        if (config('otel.automatically_trace_requests')) {
            Log::debug('[laravel-open-telemetry] automatically tracing requests is enabled');
            $this->injectHttpMiddleware(app(Kernel::class));
        }

        PendingRequest::macro('withTrace', function () {
            /** @var PendingRequest $this */
            return $this->withMiddleware(GuzzleTraceMiddleware::make());
        });

        foreach (config('otel.watchers') as $watcher) {
            $this->app->make($watcher)->register($this->app);
            Log::debug(sprintf('[laravel-open-telemetry] watcher `%s` registered', $watcher));
        }

        $this->registerCommands();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otel.php', 'otel',
        );

        if (config('otel.enabled') === false) {
            return;
        }

        $this->app->singleton(Measure::class, function ($app) {
            return new Measure($app);
        });

        Log::debug('[laravel-open-telemetry] registered.');
    }

    protected function injectHttpMiddleware(Kernel $kernel): void
    {
        if (! $kernel instanceof \Illuminate\Foundation\Http\Kernel) {
            Log::debug('[laravel-open-telemetry] Kernel is not an instance of Illuminate\Foundation\Http\Kernel, skipping middleware injection.');

            return;
        }

        if (! $kernel->hasMiddleware(MeasureRequest::class)) {
            $kernel->prependMiddleware(MeasureRequest::class);
            Log::debug(sprintf('[laravel-open-telemetry] %s middleware injected', MeasureRequest::class));
        }
    }

    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Overtrue\LaravelOpenTelemetry\Console\Commands\TestCommand::class,
            ]);
        }
    }
}
