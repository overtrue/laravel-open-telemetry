<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Illuminate\Http\Response;
use Laravel\Octane\Events\RequestTerminated;
use Mockery;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\RequestTerminatedHandler;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class RequestTerminatedHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\RequestTerminated')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_skips_when_no_root_span(): void
    {
        // Mock getRootSpan to return null
        Measure::shouldReceive('getRootSpan')->once()->andReturn(null);
        Measure::shouldReceive('endRootSpan')->once();

        $response = new Response('OK', 200);
        $event = new RequestTerminated('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestTerminatedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_sets_response_attributes_and_trace_id(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockSpanContext = Mockery::mock(SpanContextInterface::class);

        Measure::shouldReceive('getRootSpan')->twice()->andReturn($mockSpan);

        // Mock span context and trace ID
        $mockSpan->shouldReceive('getContext')->once()->andReturn($mockSpanContext);
        $mockSpanContext->shouldReceive('getTraceId')->once()->andReturn('test-trace-id-123');

        // Mock HttpAttributesHelper calls
        // These are static calls, so we verify indirectly through span method calls
        $mockSpan->shouldReceive('setAttributes')->once();
        $mockSpan->shouldReceive('setStatus')->once();

        // Mock endRootSpan
        Measure::shouldReceive('endRootSpan')->once();

        $response = new Response('OK', 200);
        $event = new RequestTerminated('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestTerminatedHandler;
        $handler->handle($event);

        // Verify trace ID was added to response headers
        $this->assertEquals('test-trace-id-123', $response->headers->get('X-Trace-Id'));
    }

    public function test_handle_sets_error_status_for_server_error(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockSpanContext = Mockery::mock(SpanContextInterface::class);

        Measure::shouldReceive('getRootSpan')->twice()->andReturn($mockSpan);

        // Mock span context and trace ID
        $mockSpan->shouldReceive('getContext')->once()->andReturn($mockSpanContext);
        $mockSpanContext->shouldReceive('getTraceId')->once()->andReturn('error-trace-id-456');

        // Mock HttpAttributesHelper calls for error response
        $mockSpan->shouldReceive('setAttributes')->once();
        $mockSpan->shouldReceive('setStatus')->once();

        // Mock endRootSpan
        Measure::shouldReceive('endRootSpan')->once();

        $response = new Response('Internal Server Error', 500);
        $event = new RequestTerminated('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestTerminatedHandler;
        $handler->handle($event);

        // Verify trace ID was added to response headers
        $this->assertEquals('error-trace-id-456', $response->headers->get('X-Trace-Id'));
    }

    public function test_handle_works_without_response(): void
    {
        // Mock getRootSpan to return null (no span)
        Measure::shouldReceive('getRootSpan')->once()->andReturn(null);

        // Mock endRootSpan
        Measure::shouldReceive('endRootSpan')->once();

        $event = new RequestTerminated('app', 'sandbox', null, null, 0.1);

        $handler = new RequestTerminatedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_always_calls_end_root_span(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockSpanContext = Mockery::mock(SpanContextInterface::class);

        Measure::shouldReceive('getRootSpan')->twice()->andReturn($mockSpan);

        // Mock span context and trace ID
        $mockSpan->shouldReceive('getContext')->once()->andReturn($mockSpanContext);
        $mockSpanContext->shouldReceive('getTraceId')->once()->andReturn('cleanup-trace-id');

        // Mock HttpAttributesHelper calls
        $mockSpan->shouldReceive('setAttributes')->once();
        $mockSpan->shouldReceive('setStatus')->once();

        // This is the key assertion - endRootSpan should always be called
        Measure::shouldReceive('endRootSpan')->once();

        $response = new Response('OK', 200);
        $event = new RequestTerminated('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestTerminatedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }
}
