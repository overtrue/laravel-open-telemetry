<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Middlewares;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class MeasureRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 确保 OpenTelemetry 已启用
        config(['otel.enabled' => true]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_middleware_skips_ignored_paths()
    {
        // 配置忽略路径
        config(['otel.ignore_paths' => ['api/*', '/admin']]);

        // 创建请求
        $request = Request::create('/api/users', 'GET');

        // 创建中间件
        $middleware = new MeasureRequest;

        // 模拟 next 回调
        $next = function ($request) {
            return response('OK');
        };

        // Mock Log facade
        Log::shouldReceive('debug')
            ->with('[laravel-open-telemetry] request ignored', [
                'path' => 'api/users',
                'ignoredRoutes' => ['api/*', '/admin'],
            ])
            ->once();

        // 执行中间件
        $response = $middleware->handle($request, $next);

        // 验证响应
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_records_request_attributes()
    {
        // 创建请求
        $request = Request::create('https://example.com/api/users', 'GET');
        $request->headers->set('Content-Length', '100');
        $request->headers->set('User-Agent', 'Test Agent');

        // 创建中间件
        $middleware = new MeasureRequest;

        // Mock span
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->once()->with(Mockery::on(function ($attributes) {
            return $attributes[TraceAttributes::URL_FULL] === 'https://example.com/api/users'
                && $attributes[TraceAttributes::HTTP_REQUEST_METHOD] === 'GET'
                && $attributes[TraceAttributes::HTTP_REQUEST_BODY_SIZE] === '100'
                && $attributes[TraceAttributes::USER_AGENT_ORIGINAL] === 'Test Agent';
        }))->andReturnSelf();
        $span->shouldReceive('setAttribute')->zeroOrMoreTimes()->andReturnSelf();
        $span->shouldReceive('recordException')->never();
        $span->shouldReceive('setStatus')->never();

        // Mock Measure facade
        Measure::shouldReceive('activeSpan')->andReturn($span);
        Measure::shouldReceive('traceId')->andReturn('test-trace-id');

        // 模拟 next 回调
        $next = function ($request) {
            return response('OK');
        };

        // 执行中间件
        $response = $middleware->handle($request, $next);

        // 验证响应
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_middleware_handles_exceptions()
    {
        // 创建请求
        $request = Request::create('https://example.com/api/users', 'GET');

        // 创建中间件
        $middleware = new MeasureRequest;

        // Mock span
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->once()->andReturnSelf();
        $span->shouldReceive('recordException')->once()->with(Mockery::type(\Exception::class))->andReturnSelf();
        $span->shouldReceive('setStatus')->once()->with(StatusCode::STATUS_ERROR)->andReturnSelf();

        // Mock Measure facade
        Measure::shouldReceive('activeSpan')->andReturn($span);

        // 模拟 next 回调抛出异常
        $next = function ($request) {
            throw new \Exception('Test exception');
        };

        // 验证异常被重新抛出
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        // 执行中间件
        $middleware->handle($request, $next);
    }

    public function test_middleware_adds_trace_id_to_response()
    {
        // 配置响应头名称
        config(['otel.response_trace_header_name' => 'X-Trace-ID']);

        // 创建请求
        $request = Request::create('https://example.com/api/users', 'GET');

        // 创建中间件
        $middleware = new MeasureRequest;

        // Mock span
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->once()->andReturnSelf();
        $span->shouldReceive('setAttribute')->zeroOrMoreTimes()->andReturnSelf();
        $span->shouldReceive('recordException')->never();
        $span->shouldReceive('setStatus')->never();

        // Mock Measure facade
        Measure::shouldReceive('activeSpan')->andReturn($span);
        Measure::shouldReceive('traceId')->andReturn('test-trace-id');

        // 模拟 next 回调
        $next = function ($request) {
            return response('OK');
        };

        // 执行中间件
        $response = $middleware->handle($request, $next);

        // 验证响应头
        $this->assertEquals('test-trace-id', $response->headers->get('X-Trace-ID'));
    }

    public function test_middleware_handles_sensitive_headers()
    {
        // 配置敏感头部
        config(['otel.sensitive_headers' => ['authorization']]);

        // 创建请求
        $request = Request::create('https://example.com/api/users', 'GET');
        $request->headers->set('Authorization', 'Bearer secret-token');
        $request->headers->set('X-Custom-Header', 'custom-value');

        // 创建中间件
        $middleware = new MeasureRequest;

        // Mock span
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->once()->andReturnSelf();
        $span->shouldReceive('setAttribute')->zeroOrMoreTimes()->andReturnSelf();
        $span->shouldReceive('recordException')->never();
        $span->shouldReceive('setStatus')->never();

        // Mock Measure facade
        Measure::shouldReceive('activeSpan')->andReturn($span);
        Measure::shouldReceive('traceId')->andReturn('test-trace-id');

        // 模拟 next 回调
        $next = function ($request) {
            return response('OK');
        };

        // 执行中间件
        $response = $middleware->handle($request, $next);

        // 验证响应
        $this->assertEquals('OK', $response->getContent());
    }
}
