<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Illuminate\Http\Request;
use Laravel\Octane\Events\RequestReceived;
use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\RequestReceivedHandler;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class RequestReceivedHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\RequestReceived')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_skips_when_not_octane(): void
    {
        // Mock isOctane to return false
        Measure::shouldReceive('isOctane')->once()->andReturn(false);

        $request = Request::create('/test');
        $event = new RequestReceived('app', 'sandbox', $request, 'context');

        $handler = new RequestReceivedHandler;

        // Should return early without doing anything
        $handler->handle($event);

        // No additional assertions needed as method returns early
        $this->assertTrue(true);
    }

    public function test_handle_disables_measure_for_ignored_requests(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);
        Measure::shouldReceive('reset')->once();
        Measure::shouldReceive('disable')->once();

        // Create a request that should be ignored (health check)
        $request = Request::create('/health');
        $event = new RequestReceived('app', 'sandbox', $request, 'context');

        $handler = new RequestReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_creates_root_span_for_valid_requests(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);
        Measure::shouldReceive('reset')->once();

        // Mock extractContextFromPropagationHeaders
        Measure::shouldReceive('extractContextFromPropagationHeaders')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(null);

        // Mock tracer and span creation
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $mockTracer = Mockery::mock(TracerInterface::class);

        $mockSpanBuilder = Mockery::mock();
        $mockSpanBuilder->shouldReceive('setParent')->once()->andReturnSelf();
        $mockSpanBuilder->shouldReceive('setSpanKind')->once()->andReturnSelf();
        $mockSpanBuilder->shouldReceive('startSpan')->once()->andReturn($mockSpan);

        $mockTracer->shouldReceive('spanBuilder')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn($mockSpanBuilder);

        Measure::shouldReceive('tracer')->once()->andReturn($mockTracer);

        // Mock span context storage
        $mockContext = Mockery::mock();
        $mockSpan->shouldReceive('storeInContext')->once()->andReturn($mockContext);
        $mockContext->shouldReceive('activate')->once()->andReturn($mockScope);

        // Mock setRootSpan
        Measure::shouldReceive('setRootSpan')->once()->with($mockSpan, $mockScope);

        $request = Request::create('/api/test');
        $event = new RequestReceived('app', 'sandbox', $request, 'context');

        $handler = new RequestReceivedHandler;
        $handler->handle($event);

        // Verify span and scope are stored in container
        $this->assertSame($mockSpan, app('otel.root_span'));
        $this->assertSame($mockScope, app('otel.root_scope'));
    }

    public function test_handle_extracts_parent_context_from_headers(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);
        Measure::shouldReceive('reset')->once();

        // Mock parent context extraction
        $mockParentContext = Mockery::mock();
        Measure::shouldReceive('extractContextFromPropagationHeaders')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($mockParentContext);

        // Mock tracer and span creation
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $mockTracer = Mockery::mock(TracerInterface::class);

        $mockSpanBuilder = Mockery::mock();
        $mockSpanBuilder->shouldReceive('setParent')->once()->with($mockParentContext)->andReturnSelf();
        $mockSpanBuilder->shouldReceive('setSpanKind')->once()->andReturnSelf();
        $mockSpanBuilder->shouldReceive('startSpan')->once()->andReturn($mockSpan);

        $mockTracer->shouldReceive('spanBuilder')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn($mockSpanBuilder);

        Measure::shouldReceive('tracer')->once()->andReturn($mockTracer);

        // Mock span context storage
        $mockContext = Mockery::mock();
        $mockSpan->shouldReceive('storeInContext')->once()->with($mockParentContext)->andReturn($mockContext);
        $mockContext->shouldReceive('activate')->once()->andReturn($mockScope);

        // Mock setRootSpan
        Measure::shouldReceive('setRootSpan')->once()->with($mockSpan, $mockScope);

        // Create request with trace headers
        $request = Request::create('/api/test');
        $request->headers->set('traceparent', '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');

        $event = new RequestReceived('app', 'sandbox', $request, 'context');

        $handler = new RequestReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }
}
