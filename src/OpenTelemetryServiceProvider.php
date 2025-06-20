<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Overtrue\LaravelOpenTelemetry\Support\GuzzleTraceMiddleware;
use Overtrue\LaravelOpenTelemetry\Support\Measure;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/otel.php' => $this->app->configPath('otel.php'),
        ], 'config');

        Log::debug('[laravel-open-telemetry] started', config('otel'));

        // Register Guzzle trace macro (functionality not provided by official package)
        PendingRequest::macro('withTrace', function () {
            /** @var PendingRequest $this */
            return $this->withMiddleware(GuzzleTraceMiddleware::make());
        });

        $this->registerCommands();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otel.php', 'otel',
        );
        $this->app->singleton(Measure::class, function ($app) {
            return new Measure($app);
        });

        Log::debug('[laravel-open-telemetry] registered.');
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
