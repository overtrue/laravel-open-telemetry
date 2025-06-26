<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\AddTraceId;
use Overtrue\LaravelOpenTelemetry\Support\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class AddTraceIdTest extends TestCase
{
    protected function tearDown(): void
    {
        // 确保每个测试后清理状态
        $measure = $this->app->make(Measure::class);
        $measure->endRootSpan(); // 先结束 span
        $measure->reset();

        parent::tearDown();
    }

    public function test_adds_trace_id_header_when_root_span_exists()
    {
        $measure = $this->app->make(Measure::class);

        // 创建根 span
        $rootSpan = $measure->startRootSpan('test-span');
        $traceId = $rootSpan->getContext()->getTraceId();

        $middleware = new AddTraceId;
        $request = Request::create('/test');

        $response = $middleware->handle($request, function ($request) {
            return new Response('test response');
        });

        // 验证响应头包含 trace ID
        $this->assertTrue($response->headers->has('X-Trace-Id'));
        $this->assertEquals($traceId, $response->headers->get('X-Trace-Id'));
    }

    public function test_uses_custom_header_name_from_config()
    {
        // 设置自定义头部名称
        config(['otel.middleware.trace_id.header_name' => 'X-Custom-Trace']);

        $measure = $this->app->make(Measure::class);
        $rootSpan = $measure->startRootSpan('test-span');
        $traceId = $rootSpan->getContext()->getTraceId();

        $middleware = new AddTraceId;
        $request = Request::create('/test');

        $response = $middleware->handle($request, function ($request) {
            return new Response('test response');
        });

        // 验证使用了自定义头部名称
        $this->assertTrue($response->headers->has('X-Custom-Trace'));
        $this->assertEquals($traceId, $response->headers->get('X-Custom-Trace'));
        $this->assertFalse($response->headers->has('X-Trace-Id'));
    }

    public function test_does_not_add_header_when_no_trace_exists()
    {
        // 确保没有根 span
        $measure = $this->app->make(Measure::class);
        $measure->reset(); // 清理任何现有的 span

        $middleware = new AddTraceId;
        $request = Request::create('/test');

        $response = $middleware->handle($request, function ($request) {
            return new Response('test response');
        });

        // 验证没有添加 trace ID 头部
        $this->assertFalse($response->headers->has('X-Trace-Id'));
    }
}
