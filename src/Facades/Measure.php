<?php

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Support\SpanBuilder;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tracer;

/**
 * @method static SpanBuilder span(string $name)
 * @method static StartedSpan start(int|string $name, ?callable $callback = null)
 * @method static void end(?string $name = null)
 * @method static SpanInterface activeSpan()
 * @method static ScopeInterface|null activeScope()
 * @method static string traceId()
 * @method static Tracer tracer()
 * @method static TextMapPropagatorInterface propagator()
 * @method static array propagationHeaders(?ContextInterface $context = null)
 * @method static ContextInterface extractContextFromPropagationHeaders(array $headers)
 */
class Measure extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Overtrue\LaravelOpenTelemetry\Support\Measure::class;
    }
}
