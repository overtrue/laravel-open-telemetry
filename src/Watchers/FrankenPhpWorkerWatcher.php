<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;

/**
 * FrankenPHP Worker Watcher
 *
 * 专门处理 FrankenPHP worker 模式下的状态管理和内存清理
 */
class FrankenPhpWorkerWatcher extends Watcher
{
    private static int $requestCount = 0;

    private static array $initialMemoryState = [];

    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {}

    public function register(Application $app): void
    {
        // 只在 FrankenPHP worker 模式下注册
        if (! $this->isFrankenPhpWorkerMode()) {
            return;
        }

        // 监听请求开始和结束事件
        $app['events']->listen('kernel.handling', [$this, 'onRequestStart']);
        $app['events']->listen('kernel.handled', [$this, 'onRequestEnd']);

        // 监听应用终止事件
        $app->terminating([$this, 'onApplicationTerminating']);
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
     * 请求开始时的处理
     */
    public function onRequestStart(): void
    {
        self::$requestCount++;

        // 记录请求开始的内存状态
        if (empty(self::$initialMemoryState)) {
            self::$initialMemoryState = [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ];
        }

        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('frankenphp.worker.request_start')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'frankenphp.worker.request_count' => self::$requestCount,
            'frankenphp.worker.memory_usage' => memory_get_usage(true),
            'frankenphp.worker.peak_memory' => memory_get_peak_usage(true),
            'frankenphp.worker.pid' => getmypid(),
        ]);

        $span->end();
    }

    /**
     * 请求结束时的处理
     */
    public function onRequestEnd(): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = $currentMemory - self::$initialMemoryState['memory_usage'];

        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('frankenphp.worker.request_end')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'frankenphp.worker.request_count' => self::$requestCount,
            'frankenphp.worker.memory_usage' => $currentMemory,
            'frankenphp.worker.peak_memory' => $peakMemory,
            'frankenphp.worker.memory_increase' => $memoryIncrease,
            'frankenphp.worker.pid' => getmypid(),
        ]);

        // 如果内存增长过多，记录警告
        if ($memoryIncrease > 10 * 1024 * 1024) { // 10MB
            $span->setAttribute('frankenphp.worker.memory_warning', true);
            $span->addEvent('High memory increase detected', [
                'memory_increase_mb' => round($memoryIncrease / 1024 / 1024, 2),
            ]);
        }

        $span->end();

        // 清理 OpenTelemetry 相关状态
        $this->cleanupOpenTelemetryState();
    }

    /**
     * 应用终止时的处理
     */
    public function onApplicationTerminating(): void
    {
        // 强制垃圾回收
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();

            if ($collected > 0) {
                $span = $this->instrumentation
                    ->tracer()
                    ->spanBuilder('frankenphp.worker.gc_cleanup')
                    ->setSpanKind(SpanKind::KIND_INTERNAL)
                    ->startSpan();

                $span->setAttributes([
                    'frankenphp.worker.gc_collected' => $collected,
                    'frankenphp.worker.memory_after_gc' => memory_get_usage(true),
                ]);

                $span->end();
            }
        }
    }

    /**
     * 清理 OpenTelemetry 相关状态
     */
    private function cleanupOpenTelemetryState(): void
    {
        try {
            // 清理可能残留的 span 上下文
            $currentSpan = \OpenTelemetry\API\Trace\Span::getCurrent();
            if ($currentSpan->isRecording()) {
                $currentSpan->end();
            }

            // 清理传播器上下文
            \OpenTelemetry\Context\Context::storage()->detach(
                \OpenTelemetry\Context\Context::storage()->scope()
            );

        } catch (\Throwable $e) {
            // 静默处理清理错误，避免影响正常请求
            error_log('OpenTelemetry cleanup error in FrankenPHP worker: '.$e->getMessage());
        }
    }

    /**
     * 获取 worker 统计信息
     */
    public static function getWorkerStats(): array
    {
        return [
            'request_count' => self::$requestCount,
            'current_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'initial_memory' => self::$initialMemoryState['memory_usage'] ?? 0,
            'memory_increase' => memory_get_usage(true) - (self::$initialMemoryState['memory_usage'] ?? 0),
            'pid' => getmypid(),
        ];
    }
}
