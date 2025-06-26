<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void enable()
 * @method static void disable()
 * @method static bool isEnabled()
 * @method static void reset()
 * @method static \OpenTelemetry\API\Trace\SpanInterface startRootSpan(string $name, array $attributes = [])
 * @method static void setRootSpan(\OpenTelemetry\API\Trace\SpanInterface $span, \OpenTelemetry\Context\ScopeInterface $scope)
 * @method static \OpenTelemetry\API\Trace\SpanInterface|null getRootSpan()
 * @method static void endRootSpan()
 * @method static \Overtrue\LaravelOpenTelemetry\Support\SpanBuilder span(string $spanName)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan start(string $spanName, \Closure $callback = null)
 * @method static mixed trace(string $name, \Closure $callback, array $attributes = [])
 * @method static void end()
 * @method static void addEvent(string $name, array $attributes = [])
 * @method static void recordException(\Throwable $exception, array $attributes = [])
 * @method static void setStatus(string $code, string $description = null)
 * @method static \OpenTelemetry\API\Trace\TracerInterface tracer()
 * @method static \OpenTelemetry\API\Trace\SpanInterface activeSpan()
 * @method static \OpenTelemetry\Context\ScopeInterface|null activeScope()
 * @method static string|null traceId()
 * @method static \OpenTelemetry\Context\Propagation\TextMapPropagatorInterface propagator()
 * @method static array propagationHeaders(\OpenTelemetry\Context\ContextInterface $context = null)
 * @method static \OpenTelemetry\Context\ContextInterface extractContextFromPropagationHeaders(array $headers)
 * @method static void flush()
 * @method static bool isOctane()
 * @method static bool isRecording()
 * @method static array getStatus()
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
