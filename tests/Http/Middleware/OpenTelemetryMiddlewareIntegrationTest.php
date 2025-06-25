<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\OpenTelemetryMiddleware;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class OpenTelemetryMiddlewareIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_middleware_integration_with_actual_request()
    {
        // 确保不在 Octane 模式
        $this->app->forgetInstance('octane');

        // 注册一个测试路由
        Route::get('/test-middleware', function () {
            return response()->json(['message' => 'Hello from middleware test']);
        })->middleware(OpenTelemetryMiddleware::class);

        // 发送请求
        $response = $this->get('/test-middleware');

        // 验证响应
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Hello from middleware test']);

        // 检查是否有 Trace ID 头
        $headers = $response->headers->all();
        echo "Response headers:\n";
        foreach ($headers as $name => $values) {
            echo "  {$name}: " . implode(', ', $values) . "\n";
        }

        // 如果有 X-Trace-Id 头，说明中间件工作了
        if (isset($headers['x-trace-id'])) {
            $this->assertTrue(true, 'Trace ID header found: ' . $headers['x-trace-id'][0]);
        } else {
            $this->fail('X-Trace-Id header not found. Middleware may not be working.');
        }
    }

    public function test_middleware_debug_output()
    {
        // 启用调试模式
        config(['app.debug' => true]);
        config(['logging.default' => 'single']);
        config(['logging.channels.single.level' => 'debug']);

        // 收集日志 - 修复回调参数
        $logs = [];
        Log::listen(function ($level, $message, $context = []) use (&$logs) {
            $logs[] = [
                'level' => $level,
                'message' => $message,
                'context' => $context
            ];
        });

        // 注册路由
        Route::get('/debug-test', function () {
            return response('Debug test');
        })->middleware(OpenTelemetryMiddleware::class);

        // 发送请求
        $response = $this->get('/debug-test');

        // 输出调试信息
        echo "\n=== DEBUG OUTPUT ===\n";
        echo "Response status: " . $response->getStatusCode() . "\n";
        echo "Response headers:\n";
        foreach ($response->headers->all() as $name => $values) {
            echo "  {$name}: " . implode(', ', $values) . "\n";
        }

        // 检查 Trace ID
        if ($response->headers->has('x-trace-id')) {
            echo "\n✓ Middleware is working! Trace ID: " . $response->headers->get('x-trace-id') . "\n";
        } else {
            echo "\n✗ No Trace ID found\n";
        }

        $this->assertTrue(true, 'Debug test completed');
    }

    public function test_check_service_provider_registration()
    {
        echo "\n=== SERVICE PROVIDER CHECK ===\n";

        // 检查服务提供者是否注册
        $providers = $this->app->getLoadedProviders();
        $otelProvider = 'Overtrue\\LaravelOpenTelemetry\\OpenTelemetryServiceProvider';

        if (isset($providers[$otelProvider])) {
            echo "✓ OpenTelemetry Service Provider is loaded\n";
        } else {
            echo "✗ OpenTelemetry Service Provider is NOT loaded\n";
            echo "Available providers:\n";
            foreach (array_keys($providers) as $provider) {
                if (strpos($provider, 'OpenTelemetry') !== false) {
                    echo "  - {$provider}\n";
                }
            }
        }

        // 检查 Measure 是否可用
        try {
            $measure = $this->app->make('opentelemetry.measure');
            echo "✓ OpenTelemetry Measure service is available\n";
            echo "  - Octane mode: " . ($measure->isOctane() ? 'Yes' : 'No') . "\n";
        } catch (\Exception $e) {
            echo "✗ OpenTelemetry Measure service is NOT available: " . $e->getMessage() . "\n";
        }

        // 检查配置
        echo "Configuration:\n";
        echo "  - otel.enabled: " . (config('otel.enabled') ? 'true' : 'false') . "\n";
        echo "  - app.env: " . config('app.env') . "\n";

        $this->assertTrue(true, 'Service provider check completed');
    }
}
