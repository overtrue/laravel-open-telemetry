<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Http\Middleware;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceRequest;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class TraceRequestIntegrationTest extends TestCase
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
        });

        // 发送请求
        $response = $this->get('/test-middleware');

        // 验证响应
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Hello from middleware test']);

        // 检查是否有 Trace ID 头
        $headers = $response->headers->all();

        // 如果有 X-Trace-Id 头，说明中间件工作了
        if (isset($headers['x-trace-id'])) {
            $this->assertTrue(true, 'Trace ID header found: '.$headers['x-trace-id'][0]);
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
        Log::listen(function ($event) use (&$logs) {
            $logs[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        // 注册路由
        Route::get('/debug-test', function () {
            return response('Debug test');
        });

        // 发送请求
        $response = $this->get('/debug-test');

        // 检查 Trace ID
        if ($response->headers->has('x-trace-id')) {
            $this->assertTrue(true, 'Middleware is working! Trace ID: '.$response->headers->get('x-trace-id'));
        } else {
            $this->fail('No Trace ID found');
        }

        $this->assertTrue(true, 'Debug test completed');
    }

    public function test_check_service_provider_registration()
    {
        // 检查服务提供者是否注册
        $providers = $this->app->getLoadedProviders();
        $otelProvider = 'Overtrue\\LaravelOpenTelemetry\\OpenTelemetryServiceProvider';

        $this->assertArrayHasKey($otelProvider, $providers, 'OpenTelemetry Service Provider should be loaded');

        // 检查 Measure 是否可用
        try {
            $measure = $this->app->make('opentelemetry.measure');
            $this->assertNotNull($measure, 'OpenTelemetry Measure service should be available');
        } catch (\Exception $e) {
            $this->fail('OpenTelemetry Measure service is NOT available: '.$e->getMessage());
        }

        // 检查配置
        $this->assertTrue(config('otel.enabled'), 'OpenTelemetry should be enabled in test environment');
        $this->assertNotNull(config('app.env'), 'App environment should be set');

        $this->assertTrue(true, 'Service provider check completed');
    }
}
