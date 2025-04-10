<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Common\Time\Clock;
use Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest;
use Overtrue\LaravelOpenTelemetry\Support\CarbonClock;
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

        if (config('otel.automatically_trace_requests')) {
            $this->injectHttpMiddleware(app(Kernel::class));
        }

        PendingRequest::macro('withTrace', function () {
            /** @var PendingRequest $this */
            return $this->withMiddleware(GuzzleTraceMiddleware::make());
        });

        foreach (config('otel.watchers') as $watcher) {
            $this->app->make($watcher)->register($this->app);
        }
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
    }

    protected function injectHttpMiddleware(Kernel $kernel): void
    {
        if (! $kernel instanceof \Illuminate\Foundation\Http\Kernel) {
            return;
        }

        if (! $kernel->hasMiddleware(MeasureRequest::class)) {
            $kernel->prependMiddleware(MeasureRequest::class);
        }
    }
}
