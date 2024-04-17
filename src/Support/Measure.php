<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind;

class Measure
{
    /**
     * @var array<string, SpanInterface>
     */
    protected array $startedSpans = [];

    public function __construct()
    {
    }

    public function start(string $name, array $attributes = [], int $kind = SpanKind::SPAN_KIND_SERVER): SpanInterface
    {
        $span = $this->getTracer()
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->startSpan();

        $span->setAttributes($attributes);

        Context::storage()->attach($span->storeInContext(Context::getCurrent()));

        $this->startedSpans[$name] = $span;

        return $span;
    }

    public function end(array $attributes = []): ?SpanInterface
    {
        $scope = Context::storage()->scope();
        $scope->detach();
        $span = Span::fromContext($scope->context());
        $span->setStatus(StatusCode::STATUS_OK);
        $span->setAttributes($attributes);
        $span->end();

        return $span;
    }

    public function event(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface
    {
        $this->current()->addEvent($name, $attributes, $timestamp);

        return $this->current();
    }

    public function current(): SpanInterface
    {
        return Span::fromContext(Context::getCurrent());
    }

    public function getSpan(string $name): ?SpanInterface
    {
        return $this->startedSpans[$name] ?? null;
    }

    /**
     * @return array<string, SpanInterface>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }

    public function getTracer(?string $name = null): TracerInterface
    {
        $name ??= config('otle.root_tracer_name', config('app.name'));

        return Globals::tracerProvider()->getTracer($name);
    }
}
