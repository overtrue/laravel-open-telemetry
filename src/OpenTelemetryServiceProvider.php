<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Behavior\Internal\Logging;
use OpenTelemetry\API\Behavior\Internal\LogWriter\Psr3LogWriter;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use Overtrue\LaravelOpenTelemetry\Support\CarbonClock;
use Overtrue\LaravelOpenTelemetry\Support\Measure;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/otle.php', 'otle',
        );

        ClockFactory::setDefault(new CarbonClock());

        $logWriter = new Psr3LogWriter(Log::getLogger());

        Logging::setLogWriter($logWriter);

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
}
