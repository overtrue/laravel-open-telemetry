<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Support;

use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;

class Measure
{
    protected static ?StartedSpan $currentSpan = null;

    public function __construct(protected Application $app) {}

    public function span(string $spanName): SpanBuilder
    {
        return new SpanBuilder(
            $this->tracer()->spanBuilder($spanName)
        );
    }

    public function start(string $spanName): StartedSpan
    {
        $span = $this->span($spanName)->start();
        static::$currentSpan = $span;

        return $span;
    }

    public function end(): void
    {
        if (static::$currentSpan) {
            static::$currentSpan->end();
            static::$currentSpan = null;
        }
    }

    public function tracer(): TracerInterface
    {
        return Globals::tracerProvider()->getTracer('io.opentelemetry.contrib.php.laravel');
    }

    public function activeSpan(): SpanInterface
    {
        return Span::getCurrent();
    }

    public function activeScope(): ?ScopeInterface
    {
        return Context::storage()->scope();
    }

    public function traceId(): string
    {
        return $this->activeSpan()->getContext()->getTraceId();
    }

    public function propagator()
    {
        return Globals::propagator();
    }

    public function propagationHeaders(?Context $context = null): array
    {
        $headers = [];
        $this->propagator()->inject($headers, null, $context);

        return $headers;
    }

    public function extractContextFromPropagationHeaders(array $headers): Context
    {
        return $this->propagator()->extract($headers);
    }
}
