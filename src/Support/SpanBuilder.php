<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Support;

use Carbon\CarbonInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;

// this file is copied from https://github.com/keepsuit/laravel-opentelemetry/blob/main/src/Support/SpanBuilder.php
class SpanBuilder
{
    public function __construct(protected SpanBuilderInterface $builder) {}

    public function setParent(?Context $context = null): self
    {
        $this->builder->setParent($context);

        return $this;
    }

    public function addLink(SpanInterface $span, array $attributes = []): self
    {
        $this->builder->addLink($span->getContext(), $attributes);

        return $this;
    }

    public function setAttribute(string $key, $value): self
    {
        $this->builder->setAttribute($key, $value);

        return $this;
    }

    /**
     * @param  iterable<string,mixed>  $attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->builder->setAttributes($attributes);

        return $this;
    }

    /**
     * @param  CarbonInterface|int  $timestamp  A carbon instance or a timestamp in nanoseconds
     */
    public function setStartTimestamp(int $timestampNanos): self
    {
        $this->builder->setStartTimestamp($timestampNanos);

        return $this;
    }

    public function setSpanKind(int $spanKind): self
    {
        $this->builder->setSpanKind($spanKind);

        return $this;
    }

    public function start(): StartedSpan
    {
        $span = $this->builder->startSpan();

        // Store span in context and activate
        $spanContext = $span->storeInContext(Context::getCurrent());
        $scope = $spanContext->activate();

        return new StartedSpan($span, $scope);
    }

    /**
     * @throws \Throwable
     */
    public function measure(\Closure $callback): mixed
    {
        $span = $this->start();

        try {
            return $callback($span->getSpan());
        } catch (\Throwable $exception) {
            $span->recordException($exception);
            throw $exception;
        } finally {
            $span->end();
        }
    }
}
