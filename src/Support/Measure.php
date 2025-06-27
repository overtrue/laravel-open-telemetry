<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

class Measure
{
    private static ?SpanInterface $rootSpan = null;

    private static ?ScopeInterface $rootScope = null;

    private static ?bool $enabled = null;

    public function __construct(protected Application $app) {}

    // ======================= Enable/Disable Management =======================

    public function enable(): void
    {
        self::$enabled = true;
    }

    public function disable(): void
    {
        self::$enabled = false;
    }

    public function isEnabled(): bool
    {
        if (self::$enabled === null) {
            return config('otel.enabled', true);
        }

        return self::$enabled;
    }

    public function reset(): void
    {
        self::$enabled = null;

        // Only end root span in Octane mode
        // In FPM mode, each request is a separate process, so root span management is handled by middleware
        if ($this->isOctane()) {
            $this->endRootSpan();
        }
    }

    // ======================= Root Span Management =======================

    /**
     * Start root span and set it as the current active span.
     */
    public function startRootSpan(string $name, array $attributes = [], ?ContextInterface $parentContext = null): SpanInterface
    {
        $parentContext = $parentContext ?: Context::getRoot();
        $tracer = $this->tracer();

        $span = $tracer->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($parentContext)
            ->setAttributes($attributes)
            ->startSpan();

        // The activate() call returns a ScopeInterface object. We MUST hold on to this object
        // and store it in a static property to prevent it from being garbage-collected prematurely.
        $scope = $span->storeInContext($parentContext)->activate();
        self::$rootScope = $scope;

        Log::debug('OpenTelemetry: Starting root span', [
            'name' => $name,
            'attributes' => $attributes,
            'trace_id' => $span->getContext()->getTraceId(),
        ]);

        self::$rootSpan = $span;

        return $span;
    }

    /**
     * Set root span (for Octane mode)
     */
    public function setRootSpan(SpanInterface $span, ScopeInterface $scope): void
    {
        self::$rootSpan = $span;
        self::$rootScope = $scope;
    }

    /**
     * Get root span
     */
    public function getRootSpan(): ?SpanInterface
    {
        return self::$rootSpan;
    }

    /**
     * End root span
     */
    public function endRootSpan(): void
    {
        if (self::$rootSpan) {
            self::$rootSpan->end();
            self::$rootSpan = null;
        }

        if (self::$rootScope) {
            try {
                self::$rootScope->detach();
            } catch (Throwable $e) {
                // Scope may have already been detached, ignore errors
            }
            self::$rootScope = null;
        }
    }

    // ======================= Core Span API =======================

    /**
     * Create span builder
     */
    public function span(string $spanName): SpanBuilder
    {
        return new SpanBuilder(
            $this->tracer()->spanBuilder($spanName)
        );
    }

    /**
     * Quickly start a span
     */
    public function start(string $spanName, ?Closure $callback = null): StartedSpan
    {
        $span = $this->tracer()
            ->spanBuilder($spanName)
            ->startSpan();

        $scope = $span->activate();

        $startedSpan = new StartedSpan($span, $scope);

        if ($callback) {
            $callback($startedSpan);
        }

        return $startedSpan;
    }

    /**
     * Execute a callback with a span
     */
    public function trace(string $name, Closure $callback, array $attributes = []): mixed
    {
        $span = $this->tracer()
            ->spanBuilder($name)
            ->setAttributes($attributes)
            ->startSpan();

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $result = $callback($span);
            $span->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    /**
     * End current active span
     */
    public function end(): void
    {
        $span = Span::getCurrent();

        if ($span !== Span::getInvalid()) {
            $span->end();
        }
    }

    // ======================= Event Recording =======================

    /**
     * Add event to current span
     */
    public function addEvent(string $name, array $attributes = []): void
    {
        $this->activeSpan()->addEvent($name, $attributes);
    }

    /**
     * Record exception
     */
    public function recordException(Throwable $exception, array $attributes = []): void
    {
        $this->activeSpan()->recordException($exception, $attributes);
    }

    /**
     * Set span status
     */
    public function setStatus(string $code, ?string $description = null): void
    {
        $this->activeSpan()->setStatus($code, $description);
    }

    // ======================= Core OpenTelemetry API =======================

    /**
     * Get the tracer instance
     */
    public function tracer(): TracerInterface
    {
        if (! $this->isEnabled()) {
            return new NoopTracer;
        }

        try {
            return $this->app->get(TracerInterface::class);
        } catch (Throwable $e) {
            Log::error('OpenTelemetry: Tracer not found', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return new NoopTracer;
        }
    }

    /**
     * Get current active span
     */
    public function activeSpan(): SpanInterface
    {
        return Span::getCurrent();
    }

    /**
     * Get current active scope
     */
    public function activeScope(): ?ScopeInterface
    {
        return Context::storage()->scope();
    }

    /**
     * Get current trace ID
     */
    public function traceId(): ?string
    {
        $traceId = $this->activeSpan()->getContext()->getTraceId();

        return SpanContextValidator::isValidTraceId($traceId) ? $traceId : null;
    }

    // ======================= Context Propagation =======================

    /**
     * Get propagator
     */
    public function propagator(): TextMapPropagatorInterface
    {
        return Globals::propagator();
    }

    /**
     * Get propagation headers
     */
    public function propagationHeaders(?ContextInterface $context = null): array
    {
        $headers = [];
        $this->propagator()->inject($headers, null, $context);

        return $headers;
    }

    /**
     * Extract context from propagation headers
     */
    public function extractContextFromPropagationHeaders(array $headers): ContextInterface
    {
        return $this->propagator()->extract($headers);
    }

    // ======================= Environment Management =======================

    /**
     * Force flush (for Octane mode)
     */
    public function flush(): void
    {
        Globals::tracerProvider()?->forceFlush();
    }

    /**
     * Check if in Octane environment
     */
    public function isOctane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']) || isset($_ENV['LARAVEL_OCTANE']);
    }

    /**
     * Check if current span is recording
     */
    public function isRecording(): bool
    {
        $tracerProvider = Globals::tracerProvider();

        // Fallback for NoopTracerProvider or other types
        return ! ($tracerProvider instanceof NoopTracerProvider);
    }

    /**
     * Get current tracking status
     */
    public function getStatus(): array
    {
        $tracerProvider = Globals::tracerProvider();
        $isRecording = $this->isRecording();
        $activeSpan = $this->activeSpan();
        $traceId = $activeSpan->getContext()->getTraceId();

        return [
            'is_recording' => $isRecording,
            'is_noop' => ! $isRecording,
            'current_trace_id' => $traceId !== '00000000000000000000000000000000' ? $traceId : null,
            'tracer_provider' => [
                'class' => get_class($tracerProvider),
                'source' => $this->app->bound('opentelemetry.tracer.provider.source')
                    ? $this->app->get('opentelemetry.tracer.provider.source')
                    : 'unknown',
            ],
        ];
    }
}
