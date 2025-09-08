<?php

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void enable()
 * @method static void disable()
 * @method static bool isEnabled()
 * @method static void reset()
 * @method static \OpenTelemetry\API\Metrics\MeterInterface meter()
 * @method static \OpenTelemetry\API\Metrics\CounterInterface counter(string $name, ?string $unit = null, ?string $description = null, array $advisories = [])
 * @method static \OpenTelemetry\API\Metrics\HistogramInterface histogram(string $name, ?string $unit = null, ?string $description = null, array $advisories = [])
 * @method static \OpenTelemetry\API\Metrics\GaugeInterface gauge(string $name, ?string $unit = null, ?string $description = null, array $advisories = [])
 * @method static \OpenTelemetry\API\Metrics\ObservableGaugeInterface observableGauge(string $name, ?string $unit = null, ?string $description = null, array|callable $advisories = [], callable ...$callbacks)
 *
 * @see \Overtrue\LaravelOpenTelemetry\Support\Metric
 */
class Metric extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Overtrue\LaravelOpenTelemetry\Support\Metric::class;
    }
}
