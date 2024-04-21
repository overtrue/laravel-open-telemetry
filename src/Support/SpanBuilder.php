<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Carbon\CarbonInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

// this file is copied from https://github.com/keepsuit/laravel-opentelemetry/blob/main/src/Support/SpanBuilder.php
class SpanBuilder
{
    public function __construct(
        protected SpanBuilderInterface $spanBuilder
    ) {
    }

    public function setParent(?ContextInterface $context): SpanBuilder
    {
        $this->spanBuilder->setParent($context);

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilder
    {
        $this->spanBuilder->addLink($context, $attributes);

        return $this;
    }

    public function setAttribute(string $key, mixed $value): SpanBuilder
    {
        $this->spanBuilder->setAttribute($key, $value);

        return $this;
    }

    /**
     * @param  iterable<string,mixed>  $attributes
     */
    public function setAttributes(iterable $attributes): SpanBuilder
    {
        $this->spanBuilder->setAttributes($attributes);

        return $this;
    }

    /**
     * @param  CarbonInterface|int  $timestamp  A carbon instance or a timestamp in nanoseconds
     */
    public function setStartTimestamp(CarbonInterface|int $timestamp): SpanBuilder
    {
        if ($timestamp instanceof CarbonInterface) {
            $timestamp = CarbonClock::carbonToNanos($timestamp);
        }

        $this->spanBuilder->setStartTimestamp($timestamp);

        return $this;
    }

    public function setSpanKind(int $spanKind): SpanBuilder
    {
        $this->spanBuilder->setSpanKind($spanKind);

        return $this;
    }

    public function start($attach = true): SpanInterface
    {
        $span = $this->spanBuilder->startSpan();

//        $span->storeInContext(Context::getCurrent());
//        $attach && Context::storage()->attach($span->storeInContext(Context::getCurrent()));

        $span->activate();

        return $span;
    }

    public function measure(\Closure $callback): mixed
    {
//        $span = $this->spanBuilder->startSpan();
//        $scope = Context::storage()->attach($span->storeInContext(Context::getCurrent()));
//
//        try {
//            return $callback($span);
//        } catch (\Throwable $exception) {
//            $span->recordException($exception);
//            throw $exception;
//        } finally {
//            $scope->detach();
//            $span->end();
//        }
    }
}
