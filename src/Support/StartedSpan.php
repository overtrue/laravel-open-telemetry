<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

class StartedSpan
{
    public function __construct(
        public SpanInterface $span,
        public ScopeInterface $scope
    ) {
    }

    public function getSpan(): SpanInterface
    {
        return $this->span;
    }

    public function getScope(): ScopeInterface
    {
        return $this->scope;
    }

    public function setAttribute(string $key, $value): self
    {
        $this->span->setAttribute($key, $value);

        return $this;
    }

    public function setAttributes(array $attributes): self
    {
        $this->span->setAttributes($attributes);

        return $this;
    }

    public function addEvent(string $name, array $attributes = [], ?int $timestamp = null): self
    {
        $this->span->addEvent($name, $attributes, $timestamp);

        return $this;
    }

    public function recordException(\Throwable $exception, array $attributes = []): self
    {
        $this->span->recordException($exception, $attributes);

        return $this;
    }

    public function end(?int $endEpochNanos = null): void
    {
        $this->span->end($endEpochNanos);
        $this->scope->detach();
    }
}
