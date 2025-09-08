<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeter;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use Throwable;

class Metric
{
    private static ?bool $enabled = null;

    public function __construct(protected Application $app) {}

    // ======================= Enable/Disable Management =======================

    public function enable(): void
    {
        self::$enabled = true;
    }

    public function disable(): void
    {
        self::$enabled = false;
    }

    public function isEnabled(): bool
    {
        if (self::$enabled === null) {
            return config('otel.enabled', true);
        }

        return self::$enabled;
    }

    // ======================= Core OpenTelemetry API =======================

    /**
     * Get the meter instance
     */
    public function meter(): MeterInterface
    {
        if (! $this->isEnabled()) {
            return new NoopMeter;
        }

        try {
            return $this->app->get(MeterInterface::class);
        } catch (Throwable $e) {
            Log::error('[laravel-open-telemetry] Meter not found', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return new NoopMeter;
        }
    }

    public function counter(string $name, ?string $unit = null,
        ?string $description = null, array $advisories = []): CounterInterface
    {
        return $this->meter()->createCounter($name, $unit, $description, $advisories);
    }

    public function histogram(string $name, ?string $unit = null,
        ?string $description = null, array $advisories = []): HistogramInterface
    {
        return $this->meter()->createHistogram($name, $unit, $description, $advisories);
    }

    public function gauge(string $name, ?string $unit = null,
        ?string $description = null, array $advisories = []): GaugeInterface
    {
        return $this->meter()->createGauge($name, $unit, $description, $advisories);
    }

    public function observableGauge(string $name, ?string $unit = null,
        ?string $description = null, array|callable $advisories = [], callable ...$callbacks): ObservableGaugeInterface
    {
        return $this->meter()->createObservableGauge($name, $unit, $description, $advisories, ...$callbacks);
    }
}
