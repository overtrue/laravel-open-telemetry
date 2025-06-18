<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use OpenTelemetry\API\Common\Attribute\Attributes;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class TracerProviderManager
{
    private static ?TracerProviderInterface $instance = null;

    private static bool $isInitialized = false;

    public static function getTracerProvider(): TracerProviderInterface
    {
        if (! self::$isInitialized) {
            self::$instance = self::createTracerProvider();
            self::$isInitialized = true;
        }

        return self::$instance;
    }

    private static function createTracerProvider(): TracerProviderInterface
    {
        // 首先尝试使用全局的 TracerProvider
        $globalProvider = Globals::tracerProvider();

        // 检查是否是有效的 TracerProvider（不是 NoopTracerProvider）
        if (! ($globalProvider instanceof NoopTracerProvider)) {
            return $globalProvider;
        }

        // 如果全局 TracerProvider 无效且配置启用，创建一个基本的 SDK TracerProvider
        if (config('otel.enabled', false) && config('otel.sdk.auto_initialize', true)) {
            try {
                return self::createSDKTracerProvider();
            } catch (\Throwable $e) {
                // 如果创建失败，记录错误并使用 Noop Provider
                if (config('app.debug')) {
                    logger()->warning('Failed to create OpenTelemetry TracerProvider', [
                        'error' => $e->getMessage(),
                        'suggestion' => 'Check your OpenTelemetry configuration or install the opentelemetry extension',
                    ]);
                }
            }
        }

        // 返回 Noop TracerProvider
        return $globalProvider;
    }

    private static function createSDKTracerProvider(): TracerProviderInterface
    {
        $resource = ResourceInfo::create(Attributes::create([
            'service.name' => config('otel.sdk.service_name', config('app.name', 'laravel-app')),
            'service.version' => config('otel.sdk.service_version', '1.0.0'),
            'telemetry.sdk.name' => 'opentelemetry',
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.version' => \Composer\InstalledVersions::getVersion('open-telemetry/sdk') ?? 'unknown',
        ]));

        $tracerProviderBuilder = TracerProvider::builder()
            ->setResource($resource)
            ->setSampler(new AlwaysOnSampler);

        // 根据配置添加导出器
        $exporter = self::createSpanExporter();
        if ($exporter !== null) {
            $processor = new SimpleSpanProcessor($exporter);
            $tracerProviderBuilder->addSpanProcessor($processor);
        }

        return $tracerProviderBuilder->build();
    }

    private static function createSpanExporter(): ?\OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        $exporterType = config('otel.exporters.traces', 'console');

        return match ($exporterType) {
            'console' => new ConsoleSpanExporter,
            'otlp' => self::createOtlpExporter(),
            'none' => null,
            default => new ConsoleSpanExporter,
        };
    }

    private static function createOtlpExporter(): ?\OpenTelemetry\SDK\Trace\SpanExporterInterface
    {
        try {
            $endpoint = config('otel.otlp.endpoint', 'http://localhost:4318');
            $headers = config('otel.otlp.headers', []);

            // 这里需要根据实际的 OTLP exporter 实现来创建
            // 由于我们没有具体的 OTLP 配置，暂时返回 console exporter
            return new ConsoleSpanExporter;
        } catch (\Throwable $e) {
            logger()->warning('Failed to create OTLP exporter, falling back to console', [
                'error' => $e->getMessage(),
            ]);

            return new ConsoleSpanExporter;
        }
    }

    public static function isNoopProvider(): bool
    {
        return self::getTracerProvider() instanceof NoopTracerProvider;
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$isInitialized = false;
    }

    public static function getProviderInfo(): array
    {
        $provider = self::getTracerProvider();

        return [
            'class' => get_class($provider),
            'is_noop' => $provider instanceof NoopTracerProvider,
            'is_recording' => ! ($provider instanceof NoopTracerProvider),
        ];
    }
}
