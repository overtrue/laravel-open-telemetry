<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Support;

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

    public function setAttribute(string $key, mixed $value): self
    {
        $this->builder->setAttribute($key, $value);

        return $this;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->builder->setAttributes($attributes);

        return $this;
    }

    /**
     * Set the start timestamp in nanoseconds
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

    /**
     * Start a span without activating its scope
     * This is the new default behavior - more predictable and safer
     */
    public function start(): SpanInterface
    {
        return $this->builder->startSpan();
    }

    /**
     * Start a span and activate its scope
     * Use this when you need the span to be active in the current context
     */
    public function startAndActivate(): StartedSpan
    {
        $span = $this->builder->startSpan();

        // Store span in context and activate
        $spanContext = $span->storeInContext(Context::getCurrent());
        $scope = $spanContext->activate();

        return new StartedSpan($span, $scope);
    }

    /**
     * Start a span without activating its scope
     * Alias for start() method for clarity
     */
    public function startSpan(): SpanInterface
    {
        return $this->start();
    }

    /**
     * Start a span and store it in context without activating scope
     * Returns both the span and the context for manual scope management
     */
    public function startWithContext(): array
    {
        $span = $this->builder->startSpan();
        $context = $span->storeInContext(Context::getCurrent());

        return [$span, $context];
    }

    /**
     * @throws \Throwable
     */
    public function measure(\Closure $callback): mixed
    {
        $span = $this->startAndActivate();

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
