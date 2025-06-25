<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Facades;

use Illuminate\Support\Facades\Facade;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;

/**
 * @method static SpanInterface startRootSpan(string $name, array $attributes = [])
 * @method static void setRootSpan(SpanInterface $span, ScopeInterface $scope)
 * @method static SpanInterface|null getRootSpan()
 * @method static void endRootSpan()
 * @method static \Overtrue\LaravelOpenTelemetry\Support\SpanBuilder span(string $spanName, string $prefix = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan start(string $spanName, \Closure $callback = null)
 * @method static mixed trace(string $name, \Closure $callback, array $attributes = [])
 * @method static void end()
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan http(\Illuminate\Http\Request $request, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan httpClient(string $method, string $url, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan database(string $operation, string $table = null, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan redis(string $command, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan queue(string $operation, string $jobClass = null, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan cache(string $operation, string $key = null, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan auth(string $operation, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan event(string $eventName, \Closure $callback = null)
 * @method static \Overtrue\LaravelOpenTelemetry\Support\StartedSpan command(string $commandName, \Closure $callback = null)
 * @method static void addEvent(string $name, array $attributes = [])
 * @method static void recordException(\Throwable $exception, array $attributes = [])
 * @method static void setStatus(string $code, string $description = null)
 * @method static TracerInterface tracer()
 * @method static SpanInterface activeSpan()
 * @method static ScopeInterface|null activeScope()
 * @method static string|null traceId()
 * @method static mixed propagator()
 * @method static array propagationHeaders(ContextInterface $context = null)
 * @method static Context extractContextFromPropagationHeaders(array $headers)
 * @method static void flush()
 * @method static void reset()
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
