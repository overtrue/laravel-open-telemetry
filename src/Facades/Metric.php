<?php

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;

/**
 * @method static void enable()
 * @method static void disable()
 * @method static bool isEnabled()
 * @method static void reset()
 * @method static \OpenTelemetry\API\Metrics\MeterInterface meter()
 * @method static \OpenTelemetry\API\Metrics\CounterInterface createCounter(string $name, ?string $unit = null, ?string $description = null, array $advisory = [])
 * @method static \OpenTelemetry\API\Metrics\HistogramInterface createHistogram(string $name, ?string $unit = null, ?string $description = null, array $advisory = [])
 * @method static \OpenTelemetry\API\Metrics\GaugeInterface createGauge(string $name, ?string $unit = null, ?string $description = null, array $advisory = [])
 * @method static \OpenTelemetry\API\Metrics\ObservableGaugeInterface createObservableGauge(string $name, ?string $unit = null, ?string $description = null, array|callable $advisory = [], callable ...$callbacks)
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