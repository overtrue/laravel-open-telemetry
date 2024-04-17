<?php

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;

/**
 * @method static SpanInterface start(string $name, array $attributes = [], int $kind = SpanKind::KIND_SERVER)
 * @method static SpanInterface|null end(string $name, array $attributes = [])
 * @method static SpanInterface|null event(string $name, iterable $attributes = [], int $timestamp = null)
 * @method static SpanInterface|null getCurrentSpan()
 * @method static SpanInterface|null getSpan(string $name)
 * @method static array startedSpans()
 * @method static TracerInterface getTracer(?string $name = null)
 */
class Measure extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Overtrue\LaravelOpenTelemetry\Support\Measure::class;
    }
}
