<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Exception;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\WorkerErrorOccurredHandler;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class WorkerErrorOccurredHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\WorkerErrorOccurred')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_skips_when_no_root_span(): void
    {
        // Mock getRootSpan to return null
        Measure::shouldReceive('getRootSpan')->once()->andReturn(null);

        $exception = new Exception('Test error');
        $event = new WorkerErrorOccurred('app', 1, $exception);

        $handler = new WorkerErrorOccurredHandler;
        $handler->handle($event);

        // No additional assertions needed as method returns early
        $this->assertTrue(true);
    }

    public function test_handle_skips_when_no_exception(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        $event = new WorkerErrorOccurred('app', 1, null);

        $handler = new WorkerErrorOccurredHandler;
        $handler->handle($event);

        // No additional assertions needed as method returns early
        $this->assertTrue(true);
    }

    public function test_handle_records_exception_and_sets_error_status(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        $exception = new Exception('Worker error occurred');

        // Mock span methods
        $mockSpan->shouldReceive('recordException')
            ->once()
            ->with($exception);

        $mockSpan->shouldReceive('setStatus')
            ->once()
            ->with(StatusCode::STATUS_ERROR, 'Worker error occurred');

        $event = new WorkerErrorOccurred('app', 1, $exception);

        $handler = new WorkerErrorOccurredHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_records_different_exception_types(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        $exception = new \RuntimeException('Runtime error in worker');

        // Mock span methods
        $mockSpan->shouldReceive('recordException')
            ->once()
            ->with($exception);

        $mockSpan->shouldReceive('setStatus')
            ->once()
            ->with(StatusCode::STATUS_ERROR, 'Runtime error in worker');

        $event = new WorkerErrorOccurred('app', 1, $exception);

        $handler = new WorkerErrorOccurredHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_does_not_end_span(): void
    {
        // Mock getRootSpan to return a span
        $mockSpan = Mockery::mock(SpanInterface::class);
        Measure::shouldReceive('getRootSpan')->once()->andReturn($mockSpan);

        $exception = new Exception('Test error');

        // Mock span methods
        $mockSpan->shouldReceive('recordException')->once()->with($exception);
        $mockSpan->shouldReceive('setStatus')->once()->with(StatusCode::STATUS_ERROR, 'Test error');

        // Ensure end() is NOT called on the span
        $mockSpan->shouldNotReceive('end');

        $event = new WorkerErrorOccurred('app', 1, $exception);

        $handler = new WorkerErrorOccurredHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }
}
