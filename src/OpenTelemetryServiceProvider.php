<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Behavior\Internal\Logging;
use OpenTelemetry\API\Behavior\Internal\LogWriter\Psr3LogWriter;
use Overtrue\LaravelOpenTelemetry\Support\Measure;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otle.php', 'otle',
        );

        $logWriter = new Psr3LogWriter(Log::getLogger());

        Logging::setLogWriter($logWriter);

        $this->app->singleton(Measure::class, function () {
            return new Measure();
        });

        $this->app->singleton(TracerManager::class, function ($app) {
            return new TracerManager($app);
        });
    }
}
