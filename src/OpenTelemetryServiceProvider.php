<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest;
use Overtrue\LaravelOpenTelemetry\Support\CarbonClock;
use Overtrue\LaravelOpenTelemetry\Support\Measure;
use Overtrue\LaravelOpenTelemetry\Support\OpenTelemetryMonologHandler;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otle.php' => $this->app->configPath('otle.php'),
        ], 'config');

        $this->injectLogConfig();

        if (config('otle.automatically_trace_requests')) {
            $this->injectHttpMiddleware(app(Kernel::class));
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otle.php', 'otle',
        );

        ClockFactory::setDefault(new CarbonClock());

        $this->app->singleton(TracerFactory::class, function ($app) {
            return new TracerFactory($app);
        });

        $this->app->singleton(Measure::class, function ($app) {
            return new Measure($app);
        });

        $this->app->singleton(TracerManager::class, function ($app) {
            return new TracerManager($app);
        });
    }

    protected function injectLogConfig(): void
    {
        $this->callAfterResolving(Repository::class, function (Repository $config) {
            if ($config->has('logging.channels.otlp')) {
                return;
            }

            $config->set('logging.channels.otlp', [
                'driver' => 'monolog',
                'handler' => OpenTelemetryMonologHandler::class,
                'level' => 'debug',
            ]);
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
