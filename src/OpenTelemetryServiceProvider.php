<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest;
use Overtrue\LaravelOpenTelemetry\Support\CarbonClock;
use Overtrue\LaravelOpenTelemetry\Support\Measure;
use Overtrue\LaravelOpenTelemetry\Support\OpenTelemetryMonologHandler;
use Overtrue\LaravelOpenTelemetry\Watchers\CommandWatcher;
use Overtrue\LaravelOpenTelemetry\Watchers\ScheduledTaskWatcher;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    protected array $consoleWatchers = [
        CommandWatcher::class,
        ScheduledTaskWatcher::class,
    ];

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/otle.php' => $this->app->configPath('otle.php'),
        ], 'config');

        if (config('otle.enabled') === false) {
            return;
        }

        $this->injectLogConfig();

        if (config('otle.automatically_trace_requests')) {
            $this->injectHttpMiddleware(app(Kernel::class));
        }

        if ($this->app->runningInConsole() && config('otle.automatically_trace_cli')) {
            $this->startMeasureConsole();
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/otle.php', 'otle',
        );

        if (config('otle.enabled') === false) {
            return;
        }

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
        if (!$kernel instanceof \Illuminate\Foundation\Http\Kernel) {
            return;
        }

        if (!$kernel->hasMiddleware(MeasureRequest::class)) {
            $kernel->prependMiddleware(MeasureRequest::class);
        }
    }

    public function startMeasureConsole(): void
    {
        $tracer = $this->app->make(TracerManager::class)->driver(config('otle.default'));
        $tracer->register($this->app);

        foreach ($this->consoleWatchers as $watcher) {
            $this->app->make($watcher)->register($this->app);
        }

        $span = Facades\Measure::span('artisan')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->start();
        $scope = $span->activate();

        $this->app->terminating(function () use ($span, $scope) {
            $span->end();
            $scope->detach();
        });
    }
}
