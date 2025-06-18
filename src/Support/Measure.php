<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\NoopSpan;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;

class Measure
{
    /**
     * @var array<string, StartedSpan>
     */
    protected array $startedSpans = [];

    /**
     * @var array<int, string> 用于通过span对象ID反向查找span名称
     */
    protected array $spanIdToName = [];

    public function __construct(protected Application $app)
    {
        $app->terminating($this->flush(...));
    }

    public function span(string $name, ?string $prefix = null): SpanBuilder
    {
        if ($prefix) {
            $name = sprintf('[%s] %s', $prefix, $name);
        }

        return new SpanBuilder($this->tracer()->spanBuilder($name));
    }

    public function start(string|int $name, ?Closure $callback = null): StartedSpan
    {
        $name = (string) $name;
        $spanBuilder = $this->span($name);

        if ($callback) {
            $callback($spanBuilder);
        }

        $span = $spanBuilder->start();
        $scope = $span->activate();

        $startedSpan = new StartedSpan($span, $scope);

        // 使用唯一键来避免名称冲突
        $uniqueKey = $this->generateUniqueKey($name);
        $this->startedSpans[$uniqueKey] = $startedSpan;
        $this->spanIdToName[spl_object_id($span)] = $uniqueKey;

        return $startedSpan;
    }

    public function end(string|int|null $name = null): void
    {
        if ($name === null) {
            // 结束最后一个开始的 span
            $uniqueKey = array_key_last($this->startedSpans);
        } else {
            $name = (string) $name;
            $uniqueKey = $this->findSpanKey($name);
        }

        if ($uniqueKey && isset($this->startedSpans[$uniqueKey])) {
            $startedSpan = $this->startedSpans[$uniqueKey];

            try {
                $startedSpan->span->end();
                $startedSpan->scope->detach();
            } catch (\Throwable $e) {
                // 记录错误但不抛出，避免影响应用正常运行
                if (config('app.debug')) {
                    logger()->warning('Failed to end OpenTelemetry span', [
                        'span_name' => $name,
                        'error' => $e->getMessage(),
                    ]);
                }
            } finally {
                // 清理引用
                $spanId = spl_object_id($startedSpan->span);
                unset($this->startedSpans[$uniqueKey]);
                unset($this->spanIdToName[$spanId]);
            }
        }
    }

    public function tracer(): TracerInterface
    {
        $tracerProvider = TracerProviderManager::getTracerProvider();
        $serviceName = config('otel.sdk.service_name', config('app.name', 'laravel-app'));

        return $tracerProvider->getTracer($serviceName);
    }

    public function activeSpan(): SpanInterface
    {
        $span = Span::getCurrent();

        // 如果是 NoopSpan，可能意味着没有活动的 span
        if ($span instanceof NoopSpan && ! empty($this->startedSpans)) {
            // 返回最后一个开始的 span
            $lastStartedSpan = end($this->startedSpans);
            reset($this->startedSpans); // 重置数组指针

            return $lastStartedSpan->span;
        }

        return $span;
    }

    public function activeScope(): ?ScopeInterface
    {
        try {
            return Context::storage()->scope();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function traceId(): ?string
    {
        try {
            $span = $this->activeSpan();
            $traceId = $span->getContext()->getTraceId();

            return SpanContextValidator::isValidTraceId($traceId) ? $traceId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function propagator(): TextMapPropagatorInterface
    {
        return Globals::propagator();
    }

    public function propagationHeaders(?ContextInterface $context = null): array
    {
        $headers = [];

        try {
            $this->propagator()->inject($headers, context: $context);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                logger()->warning('Failed to inject propagation headers', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $headers;
    }

    public function extractContextFromPropagationHeaders(array $headers): ContextInterface
    {
        try {
            return $this->propagator()->extract($headers);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                logger()->warning('Failed to extract context from headers', [
                    'error' => $e->getMessage(),
                ]);
            }

            return Context::getCurrent();
        }
    }

    public function flush(): void
    {
        $spanNames = array_keys($this->startedSpans);

        foreach ($spanNames as $uniqueKey) {
            $spanName = $this->extractSpanName($uniqueKey);
            $this->end($spanName);
        }
    }

    /**
     * 检查当前是否使用 NoopTracerProvider
     */
    public function isRecording(): bool
    {
        return ! TracerProviderManager::isNoopProvider();
    }

    /**
     * 获取追踪状态信息
     */
    public function getStatus(): array
    {
        return [
            'is_recording' => $this->isRecording(),
            'active_spans_count' => count($this->startedSpans),
            'tracer_provider' => TracerProviderManager::getProviderInfo(),
            'current_trace_id' => $this->traceId(),
        ];
    }

    /**
     * 生成唯一的 span 键
     */
    private function generateUniqueKey(string $name): string
    {
        $counter = 1;
        $baseKey = $name;
        $uniqueKey = $baseKey;

        while (isset($this->startedSpans[$uniqueKey])) {
            $uniqueKey = $baseKey.'_'.$counter;
            $counter++;
        }

        return $uniqueKey;
    }

    /**
     * 查找 span 键
     */
    private function findSpanKey(string $name): ?string
    {
        // 首先尝试精确匹配
        if (isset($this->startedSpans[$name])) {
            return $name;
        }

        // 然后尝试模糊匹配（查找以该名称开头的键）
        foreach (array_keys($this->startedSpans) as $key) {
            if (str_starts_with($key, $name.'_') || $key === $name) {
                return $key;
            }
        }

        return null;
    }

    /**
     * 从唯一键中提取原始 span 名称
     */
    private function extractSpanName(string $uniqueKey): string
    {
        $parts = explode('_', $uniqueKey);

        // 如果最后一部分是数字，则移除它
        if (count($parts) > 1 && is_numeric(end($parts))) {
            array_pop($parts);

            return implode('_', $parts);
        }

        return $uniqueKey;
    }
}
