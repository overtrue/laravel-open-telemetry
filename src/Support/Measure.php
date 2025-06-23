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

    /**
     * 检查是否在 FrankenPHP worker 模式下运行
     */
    public function isFrankenPhpWorkerMode(): bool
    {
        return function_exists('frankenphp_handle_request') &&
               php_sapi_name() === 'frankenphp' &&
               (bool) ($_SERVER['FRANKENPHP_WORKER'] ?? false);
    }

    /**
     * 清理 worker 模式下的 OpenTelemetry 状态
     */
    public function cleanupWorkerState(): void
    {
        if (! $this->isFrankenPhpWorkerMode()) {
            return;
        }

        try {
            // 结束当前活动的 span
            if (static::$currentSpan) {
                static::$currentSpan->end();
                static::$currentSpan = null;
            }

            // 清理 span 上下文
            $currentSpan = $this->activeSpan();
            if ($currentSpan->isRecording()) {
                $currentSpan->end();
            }

            // 清理作用域
            $scope = $this->activeScope();
            if ($scope) {
                $scope->detach();
            }

        } catch (\Throwable $e) {
            // 静默处理清理错误
            error_log('OpenTelemetry worker cleanup error: '.$e->getMessage());
        }
    }

    /**
     * 获取 worker 模式状态信息
     */
    public function getWorkerStatus(): array
    {
        if (! $this->isFrankenPhpWorkerMode()) {
            return ['worker_mode' => false];
        }

        return [
            'worker_mode' => true,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'current_span_recording' => $this->activeSpan()->isRecording(),
            'trace_id' => $this->traceId(),
            'pid' => getmypid(),
        ];
    }
}
