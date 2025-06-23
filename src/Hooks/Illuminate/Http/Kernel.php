<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Hooks\Illuminate\Http;

use Illuminate\Http\Kernel as HttpKernel;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\LaravelHook;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\LaravelHookTrait;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Symfony\Component\HttpFoundation\Response;

use function OpenTelemetry\Instrumentation\hook;

class Kernel implements LaravelHook
{
    use LaravelHookTrait;

    public function instrument(): void
    {
        // Hook into the handle method to add trace ID to response
        hook(
            class: HttpKernel::class,
            function: 'handle',
            pre: function (HttpKernel $kernel, array $params) {
                // FrankenPHP worker 模式下的请求开始处理
                if ($this->isFrankenPhpWorkerMode()) {
                    $this->handleWorkerRequestStart();
                }
            },
            post: function (HttpKernel $kernel, array $params, Response $response) {
                $this->addTraceIdToResponse($response);

                // FrankenPHP worker 模式下的请求结束处理
                if ($this->isFrankenPhpWorkerMode()) {
                    $this->handleWorkerRequestEnd();
                }

                return $response;
            }
        );
    }

    /**
     * Add trace ID to response headers
     */
    private function addTraceIdToResponse(Response $response): void
    {
        $headerName = config('otel.response_trace_header_name');

        // Skip if header name is not configured or empty
        if (empty($headerName)) {
            return;
        }

        try {
            // Get current trace ID
            $traceId = Measure::traceId();

            // Add trace ID to response header if it's valid (not empty and not all zeros)
            if (! empty($traceId) && $traceId !== '00000000000000000000000000000000') {
                $response->headers->set($headerName, $traceId);
            }
        } catch (\Throwable $e) {
            // Silently ignore errors when getting trace ID
            // This prevents failures when there's no trace context
        }
    }

    /**
     * 检测是否运行在 FrankenPHP worker 模式
     */
    private function isFrankenPhpWorkerMode(): bool
    {
        return function_exists('frankenphp_handle_request') &&
               php_sapi_name() === 'frankenphp' &&
               (bool) ($_SERVER['FRANKENPHP_WORKER'] ?? false);
    }

    /**
     * 处理 worker 模式请求开始
     */
    private function handleWorkerRequestStart(): void
    {
        try {
            // 重置 OpenTelemetry 上下文状态
            $this->resetOpenTelemetryContext();

            // 触发请求开始事件
            if (function_exists('event')) {
                event('kernel.handling');
            }
        } catch (\Throwable $e) {
            error_log("FrankenPHP worker request start error: " . $e->getMessage());
        }
    }

    /**
     * 处理 worker 模式请求结束
     */
    private function handleWorkerRequestEnd(): void
    {
        try {
            // 触发请求结束事件
            if (function_exists('event')) {
                event('kernel.handled');
            }

            // 清理可能残留的资源
            $this->cleanupWorkerRequestResources();
        } catch (\Throwable $e) {
            error_log("FrankenPHP worker request end error: " . $e->getMessage());
        }
    }

    /**
     * 重置 OpenTelemetry 上下文状态
     */
    private function resetOpenTelemetryContext(): void
    {
        try {
            // 确保没有残留的 span 上下文
            $currentSpan = \OpenTelemetry\API\Trace\Span::getCurrent();
            if ($currentSpan->isRecording()) {
                $currentSpan->end();
            }
        } catch (\Throwable $e) {
            // 静默处理，避免影响正常请求
        }
    }

    /**
     * 清理 worker 请求资源
     */
    private function cleanupWorkerRequestResources(): void
    {
        try {
            // 强制垃圾回收，避免内存泄漏
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // 清理可能的全局状态
            $this->resetGlobalState();
        } catch (\Throwable $e) {
            error_log("FrankenPHP worker cleanup error: " . $e->getMessage());
        }
    }

    /**
     * 重置全局状态
     */
    private function resetGlobalState(): void
    {
        // 清理可能的静态缓存
        if (class_exists('\Illuminate\Support\Facades\Cache')) {
            try {
                // 清理运行时缓存，但保留持久化缓存
                \Illuminate\Support\Facades\Cache::store('array')->flush();
            } catch (\Throwable $e) {
                // 静默处理
            }
        }
    }
}
