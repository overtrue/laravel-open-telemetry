<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest;
use Overtrue\LaravelOpenTelemetry\Support\Measure;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otle.php' => $this->app->configPath('otle.php'),
        ], 'config');

        if (config('otle.enabled') === false) {
            return;
        }

        if (config('otle.automatically_trace_requests')) {
            $this->injectHttpMiddleware(app(Kernel::class));
        }

        foreach (config('otle.watchers') as $watcher) {
            $this->app->make($watcher)->register($this->app);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otle.php', 'otle',
        );

        if (config('otle.enabled') === false) {
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
