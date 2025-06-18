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
        // 首先尝试使用全局的 TracerProvider（由 opentelemetry-auto-laravel 初始化）
        $globalProvider = Globals::tracerProvider();

        // 检查是否是有效的 TracerProvider（不是 NoopTracerProvider）
        if (! ($globalProvider instanceof NoopTracerProvider)) {
            return $globalProvider;
        }

        // 如果官方包没有初始化 TracerProvider，且我们的配置允许，提供一个基本的 fallback
        if (config('otel.enabled', false) && config('otel.sdk.auto_initialize', false)) {
            try {
                if (config('app.debug')) {
                    logger()->info('OpenTelemetry auto-laravel not initialized, creating fallback TracerProvider');
                }

                return self::createFallbackTracerProvider();
            } catch (\Throwable $e) {
                if (config('app.debug')) {
                    logger()->warning('Failed to create fallback OpenTelemetry TracerProvider', [
                        'error' => $e->getMessage(),
                        'suggestion' => 'Ensure opentelemetry-auto-laravel is properly configured or disable OTEL_SDK_AUTO_INITIALIZE',
                    ]);
                }
            }
        }

        // 返回 Noop TracerProvider（这是预期的行为，当 OpenTelemetry 未配置时）
        return $globalProvider;
    }

    private static function createFallbackTracerProvider(): TracerProviderInterface
    {
        $resource = ResourceInfo::create(Attributes::create([
            'service.name' => config('otel.sdk.service_name', config('app.name', 'laravel-app')),
            'service.version' => config('otel.sdk.service_version', '1.0.0'),
            'telemetry.sdk.name' => 'opentelemetry',
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.version' => \Composer\InstalledVersions::getVersion('open-telemetry/sdk') ?? 'unknown',
            'telemetry.auto.version' => 'manual-fallback', // 标识这是手动fallback
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
            // 注意：这里只是一个基本实现
            // 在实际使用中，用户应该通过 opentelemetry-auto-laravel 配置 OTLP
            if (config('app.debug')) {
                logger()->info('Using fallback console exporter instead of OTLP. For production OTLP, configure opentelemetry-auto-laravel properly.');
            }

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
            'source' => $provider instanceof NoopTracerProvider ? 'noop' :
                       (self::isUsingGlobalProvider() ? 'auto-laravel' : 'fallback'),
        ];
    }

    private static function isUsingGlobalProvider(): bool
    {
        $globalProvider = Globals::tracerProvider();

        return ! ($globalProvider instanceof NoopTracerProvider) &&
               self::getTracerProvider() === $globalProvider;
    }
}
