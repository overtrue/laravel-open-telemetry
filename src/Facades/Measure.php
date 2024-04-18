<?php

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Support\SpanBuilder;

/**
 * @method static SpanBuilder span(string $name)
 * @method static void end()
 * @method static SpanInterface activeSpan()
 * @method static ScopeInterface|null activeScope()
 * @method static TracerInterface getTracer(?string $name = null)
 * @method static string traceId()
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
