<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context as OtelContext;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\OpenTelemetryMiddleware;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class OpenTelemetryMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_middleware_skips_in_octane_mode()
    {
        // 模拟 Octane 环境
        $this->app->instance('octane', true);

        $middleware = new OpenTelemetryMiddleware();
        $request = Request::create('/test', 'GET');
        $response = new Response('Test');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    public function test_middleware_creates_root_span_in_non_octane_mode()
    {
        // 确保不在 Octane 模式
        $this->app->forgetInstance('octane');

        // Mock Measure facade
        Measure::shouldReceive('isOctane')->andReturn(false);
        Measure::shouldReceive('extractContextFromPropagationHeaders')->andReturn(OtelContext::getRoot());
        Measure::shouldReceive('tracer')->andReturn($this->createMockTracer());
        Measure::shouldReceive('setRootSpan')->once();
        Measure::shouldReceive('endRootSpan')->once();

        $middleware = new OpenTelemetryMiddleware();
        $request = Request::create('/test', 'GET');
        $response = new Response('Test');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    public function test_middleware_handles_exceptions()
    {
        // 确保不在 Octane 模式
        $this->app->forgetInstance('octane');

        $exception = new \Exception('Test exception');

        // Mock Measure facade
        Measure::shouldReceive('isOctane')->andReturn(false);
        Measure::shouldReceive('extractContextFromPropagationHeaders')->andReturn(OtelContext::getRoot());
        Measure::shouldReceive('tracer')->andReturn($this->createMockTracer());
        Measure::shouldReceive('setRootSpan')->once();
        Measure::shouldReceive('endRootSpan')->once();

        $middleware = new OpenTelemetryMiddleware();
        $request = Request::create('/test', 'GET');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $middleware->handle($request, $next);
    }

    private function createMockTracer()
    {
        $tracer = Mockery::mock(TracerInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $context = Mockery::mock(\OpenTelemetry\Context\ContextInterface::class);
        $spanContext = Mockery::mock(SpanContextInterface::class);

        $span->shouldReceive('setAttributes')->andReturnSelf();
        $span->shouldReceive('setAttribute')->andReturnSelf();
        $span->shouldReceive('addEvent')->andReturnSelf();
        $span->shouldReceive('setStatus')->andReturnSelf();
        $span->shouldReceive('storeInContext')->andReturn($context);
        $span->shouldReceive('getContext')->andReturn($spanContext);
        $span->shouldReceive('recordException');
        $span->shouldReceive('end');

        $spanContext->shouldReceive('getTraceId')->andReturn('1234567890abcdef1234567890abcdef');

        $context->shouldReceive('activate')->andReturn($scope);
        $scope->shouldReceive('detach');

        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer->shouldReceive('spanBuilder')->andReturn($spanBuilder);

        return $tracer;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
