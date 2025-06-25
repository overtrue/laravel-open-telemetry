<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context as LaravelContext;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\NoopTracer;

class Measure
{
    private static ?SpanInterface $rootSpan = null;
    private static ?ScopeInterface $rootScope = null;

    public function __construct(protected Application $app)
    {
    }

    public function enable(): void
    {
        LaravelContext::addHidden('otel.tracing.enabled', true);
    }

    public function disable(): void
    {
        LaravelContext::addHidden('otel.tracing.enabled', false);
    }

    public function isEnabled(): bool
    {
        // If context has not been set, fall back to the general config.
        if (LaravelContext::missingHidden('otel.tracing.enabled')) {
            return config('otel.enabled', true);
        }

        return LaravelContext::getHidden('otel.tracing.enabled');
    }

    public function reset(): void
    {
        LaravelContext::addHidden('otel.tracing.enabled', config('otel.enabled', true));

        // Only end root span in Octane mode
        // In FPM mode, each request is a separate process, so root span management is handled by middleware
        if ($this->isOctane()) {
            $this->endRootSpan();
        }
    }

    // ======================= Root Span Management =======================

    /**
     * Start root span (for FrankenPHP mode)
     */
    public function startRootSpan(string $name, array $attributes = []): SpanInterface
    {
        $tracer = $this->tracer();

        $span = $tracer->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes($attributes)
            ->startSpan();

        // Store span in context and activate
        $scope = $span->storeInContext(\OpenTelemetry\Context\Context::getRoot())->activate();

        self::$rootSpan = $span;
        self::$rootScope = $scope;

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
            } catch (\Throwable $e) {
                // Scope may have already been detached, ignore errors
            }
            self::$rootScope = null;
        }
    }

    // ======================= General Span Creation =======================

    /**
     * Create span builder
     */
    public function span(string $spanName, ?string $prefix = null): SpanBuilder
    {
        $fullName = $prefix ? "{$prefix}.{$spanName}" : $spanName;

        return new SpanBuilder(
            $this->tracer()->spanBuilder($fullName)
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

        $scope = $span->storeInContext(\OpenTelemetry\Context\Context::getCurrent())->activate();

        try {
            $result = $callback($span);
            $span->setStatus(StatusCode::STATUS_OK);
            return $result;
        } catch (\Throwable $e) {
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
        if ($span && $span !== Span::getInvalid()) {
            $span->end();
        }
    }

    // ======================= Semantic Shortcut Methods =======================

    /**
     * Create HTTP request span
     */
    public function http(Request $request, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::http($request);

        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes([
                TraceAttributes::HTTP_REQUEST_METHOD => $request->method(),
                TraceAttributes::URL_FULL => $request->fullUrl(),
                TraceAttributes::URL_SCHEME => $request->getScheme(),
                TraceAttributes::URL_PATH => $request->path(),
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create HTTP client request span
     */
    public function httpClient(string $method, string $url, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::httpClient($method, $url);

        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes([
                TraceAttributes::HTTP_REQUEST_METHOD => strtoupper($method),
                TraceAttributes::URL_FULL => $url,
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create database query span
     */
    public function database(string $operation, ?string $table = null, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::database($operation, $table);

        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes([
                TraceAttributes::DB_OPERATION_NAME => strtoupper($operation),
            ]);

        if ($table) {
            $spanBuilder->setAttributes([
                TraceAttributes::DB_COLLECTION_NAME => $table,
            ]);
        }

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create Redis command span
     */
    public function redis(string $command, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::redis($command);
        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes([
                TraceAttributes::DB_SYSTEM => 'redis',
                TraceAttributes::DB_OPERATION_NAME => strtoupper($command),
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create queue task span
     */
    public function queue(string $operation, ?string $jobClass = null, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::queue($operation, $jobClass);
        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttributes([
                TraceAttributes::MESSAGING_OPERATION_TYPE => strtoupper($operation),
                TraceAttributes::MESSAGING_DESTINATION_NAME => $jobClass ? class_basename($jobClass) : null,
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create cache operation span
     */
    public function cache(string $operation, ?string $key = null, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::cache($operation, $key);
        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes(array_filter([
                'cache.operation' => strtoupper($operation), // Cache-related attributes are not defined in TraceAttributes
                'cache.key' => $key,
            ]));

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create authentication span
     */
    public function auth(string $operation, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::auth($operation);
        $spanBuilder = $this->span($spanName)
            ->setAttributes([
                'auth.operation' => strtoupper($operation), // Authentication-related attributes are not defined in TraceAttributes
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create event span
     */
    public function event(string $eventName, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::event($eventName);
        $spanBuilder = $this->span($spanName)
            ->setAttributes([
                TraceAttributes::EVENT_NAME => $eventName,
                'event.domain' => 'laravel', // Custom attribute, to identify this is a Laravel event
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    /**
     * Create command span
     */
    public function command(string $commandName, ?Closure $callback = null): StartedSpan
    {
        $spanName = SpanNameHelper::command($commandName);
        $spanBuilder = $this->span($spanName)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes([
                TraceAttributes::CODE_FUNCTION => 'handle',
                TraceAttributes::CODE_NAMESPACE => $commandName,
            ]);

        if ($callback) {
            $callback($spanBuilder);
        }

        return $spanBuilder->start();
    }

    // ======================= Event Recording Shortcut Methods =======================

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
    public function recordException(\Throwable $exception, array $attributes = []): void
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

    // ======================= OpenTelemetry Base API =======================

    /**
     * Get the tracer instance.
     */
    public function tracer(): TracerInterface
    {
        if (! $this->isEnabled()) {
            return new NoopTracer();
        }

        return $this->app->get(TracerInterface::class);
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

    // ======================= Environment and Lifecycle Management =======================

    /**
     * Force flush (for Octane mode)
     */
    public function flush(): void
    {
        if ($this->isOctane()) {
            return;
        }

        $this->endRootSpan();

        $this->app['opentelemetry.tracer.provider']?->forceFlush();
    }

    /**
     * Check if in Octane environment
     */
    public function isOctane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']);
    }

    /**
     * Check if current span is recording
     */
    public function isRecording(): bool
    {
        $tracerProvider = Globals::tracerProvider();
        if (method_exists($tracerProvider, 'getSampler')) {
            $sampler = $tracerProvider->getSampler();
            // This is a simplified check. A more robust check might involve checking sampler decision.
            return ! ($sampler instanceof \OpenTelemetry\SDK\Trace\Sampler\NeverOffSampler);
        }
        // Fallback for NoopTracerProvider or other types
        return ! ($tracerProvider instanceof \OpenTelemetry\API\Trace\NoopTracerProvider);
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
            'active_spans_count' => Context::storage()->count(),
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
