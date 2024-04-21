<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;

class Measure
{
    public function __construct(protected Application $app)
    {

    }

    public function span(string $name): SpanBuilder
    {
        return new SpanBuilder($this->getTracer()->spanBuilder($name));
    }

    public function end(?SpanInterface $span = null): void
    {
        $this->activeSpan()->end();
        $this->activeScope()?->detach();
    }

    public function activeSpan(): SpanInterface
    {
        return Span::getCurrent();
    }

    public function activeScope(): ?ScopeInterface
    {
        return Context::storage()->scope();
    }

    public function traceId(): ?string
    {
        $traceId = $this->activeSpan()->getContext()->getTraceId();

        return SpanContextValidator::isValidTraceId($traceId) ? $traceId : null;
    }

    public function getTracer(?string $name = null): TracerInterface
    {
        $name ??= config('otle.root_tracer_name', config('app.name'));

        return Globals::tracerProvider()->getTracer($name);
    }

    public function propagator()
    {
        return $this->app->make(TextMapPropagatorInterface::class) ?? TraceContextPropagator::getInstance();
    }

    public function propagationHeaders(?ContextInterface $context = null): array
    {
        $headers = [];

        $this->propagator()->inject($headers, context: $context);

        return $headers;
    }

    public function extractContextFromPropagationHeaders(array $headers): ContextInterface
    {
        return $this->propagator()->extract($headers);
    }
}
