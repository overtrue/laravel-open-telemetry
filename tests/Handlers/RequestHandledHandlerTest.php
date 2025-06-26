<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Illuminate\Http\Response;
use Laravel\Octane\Events\RequestHandled;
use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\RequestHandledHandler;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class RequestHandledHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\RequestHandled')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_skips_when_no_root_span(): void
    {
        // Mock getRootSpan to return null
        Measure::shouldReceive('getRootSpan')->once()->andReturn(null);

        $response = new Response('OK', 200);
        $event = new RequestHandled('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestHandledHandler;
        $handler->handle($event);

        // No additional assertions needed as method returns early
        $this->assertTrue(true);
    }

    public function test_handle_skips_when_no_response(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        $event = new RequestHandled('app', 'sandbox', null, null, 0.1);

        $handler = new RequestHandledHandler;
        $handler->handle($event);

        // No additional assertions needed as method returns early
        $this->assertTrue(true);
    }

    public function test_handle_sets_span_status_for_successful_response(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        // Mock HttpAttributesHelper::setSpanStatusFromResponse
        // This is a static call, so we'll verify it's called indirectly by checking the span methods
        $mockSpan->shouldReceive('setStatus')->once();

        $response = new Response('OK', 200);
        $event = new RequestHandled('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestHandledHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_sets_span_status_for_error_response(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        // Mock HttpAttributesHelper::setSpanStatusFromResponse for error status
        $mockSpan->shouldReceive('setStatus')->once();

        $response = new Response('Server Error', 500);
        $event = new RequestHandled('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestHandledHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_sets_span_status_for_client_error_response(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        // Mock HttpAttributesHelper::setSpanStatusFromResponse for client error
        $mockSpan->shouldReceive('setStatus')->once();

        $response = new Response('Not Found', 404);
        $event = new RequestHandled('app', 'sandbox', null, $response, 0.1);

        $handler = new RequestHandledHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }
}
