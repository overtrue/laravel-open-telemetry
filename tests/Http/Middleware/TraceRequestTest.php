<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context as OtelContext;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceRequest;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class TraceRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Allow addEvent to be called since EventWatcher is active
        Measure::shouldReceive('addEvent')->byDefault();
        Measure::shouldReceive('isOctane')->andReturn(false); // Assume not in Octane for most tests
    }

    public function test_middleware_creates_root_span()
    {
        // Mock Measure facade
        Measure::shouldReceive('extractContextFromPropagationHeaders')->once()->andReturn(OtelContext::getRoot());
        Measure::shouldReceive('startRootSpan')->once()->andReturn($this->createMockSpan());
        Measure::shouldReceive('endRootSpan')->once();

        $middleware = new TraceRequest;
        $request = Request::create('/test', 'GET');
        $response = new Response('Test');

        $next = fn ($req) => $response;

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    public function test_middleware_handles_exceptions()
    {
        $exception = new \Exception('Test exception');

        // The middleware is now applied globally, so we just need to mock the facade
        Measure::shouldReceive('extractContextFromPropagationHeaders')->once()->andReturn(OtelContext::getRoot());
        Measure::shouldReceive('startRootSpan')->once()->andReturn($this->createMockSpan());

        // In the test environment, the exception is handled by Laravel's handler,
        // which creates a 500 response. The middleware's catch block is not executed,
        // but the finally block IS. We need to ensure the span is always ended.
        Measure::shouldReceive('endRootSpan')->once();

        // When Laravel's handler reports the exception, our ExceptionWatcher is triggered.
        // We need to provide mocks for the calls it makes to avoid breaking the test.
        Measure::shouldReceive('tracer')->andReturn(new NoopTracer());
        Measure::shouldReceive('activeSpan')->andReturn(Span::getInvalid());

        Route::get('/test-exception', fn () => throw $exception);

        // We expect a 500 error response, not an exception bubble up.
        $this->get('/test-exception')->assertStatus(500);
    }

    private function createMockSpan(): SpanInterface
    {
        $span = Mockery::mock(SpanInterface::class);
        $spanContext = Mockery::mock(SpanContextInterface::class);

        $span->shouldReceive('setAttributes')->andReturnSelf()->byDefault();
        $span->shouldReceive('setAttribute')->andReturnSelf()->byDefault();
        $span->shouldReceive('addEvent')->andReturnSelf()->byDefault();
        $span->shouldReceive('setStatus')->andReturnSelf()->byDefault();
        $span->shouldReceive('getContext')->andReturn($spanContext)->byDefault();
        $span->shouldReceive('recordException')->byDefault();
        $span->shouldReceive('end')->byDefault();

        $spanContext->shouldReceive('getTraceId')->andReturn('1234567890abcdef1234567890abcdef')->byDefault();
        $spanContext->shouldReceive('getSpanId')->andReturn('1234567890abcdef')->byDefault();

        return $span;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
