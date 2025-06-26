<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

class StartedSpan
{
    private bool $ended = false;

    public function __construct(
        private readonly SpanInterface $span,
        private readonly ScopeInterface $scope
    ) {}

    public function getSpan(): SpanInterface
    {
        return $this->span;
    }

    public function getScope(): ScopeInterface
    {
        return $this->scope;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        if ($this->ended) {
            return $this; // Silently ignore if already ended
        }

        $this->span->setAttribute($key, $value);

        return $this;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function setAttributes(array $attributes): self
    {
        if ($this->ended) {
            return $this; // Silently ignore if already ended
        }

        $this->span->setAttributes($attributes);

        return $this;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function addEvent(string $name, array $attributes = [], ?int $timestamp = null): self
    {
        if ($this->ended) {
            return $this; // Silently ignore if already ended
        }

        $this->span->addEvent($name, $attributes, $timestamp);

        return $this;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    public function recordException(\Throwable $exception, array $attributes = []): self
    {
        if ($this->ended) {
            return $this; // Silently ignore if already ended
        }

        $this->span->recordException($exception, $attributes);

        return $this;
    }

    public function isEnded(): bool
    {
        return $this->ended;
    }

    public function end(?int $endEpochNanos = null): void
    {
        if ($this->ended) {
            return; // Prevent double-ending
        }

        $this->span->end($endEpochNanos);

        try {
            $this->scope->detach();
        } catch (\Throwable $e) {
            // Scope may already be detached, ignore silently
        }

        $this->ended = true;
    }
}
