<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;

/**
 * @method static \Overtrue\LaravelOpenTelemetry\Support\SpanBuilder span(string $spanName)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan start(string $spanName)
 * @method static void end()
 * @method static TracerInterface tracer()
 * @method static SpanInterface activeSpan()
 * @method static ScopeInterface|null activeScope()
 * @method static string traceId()
 * @method static mixed propagator()
 * @method static array propagationHeaders(Context $context = null)
 * @method static Context extractContextFromPropagationHeaders(array $headers)
 *
 * @see \Overtrue\LaravelOpenTelemetry\Support\Measure
 */
class Measure extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Overtrue\LaravelOpenTelemetry\Support\Measure::class;
    }
}
