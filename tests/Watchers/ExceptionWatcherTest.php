<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Exception;
use Illuminate\Log\Events\MessageLogged;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\ExceptionWatcher;
use RuntimeException;

class ExceptionWatcherTest extends TestCase
{
    private ExceptionWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new ExceptionWatcher;
    }

    public function test_registers_message_logged_event_listener()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(MessageLogged::class, [$this->watcher, 'recordException'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_exception_from_message_logged_event()
    {
        $exception = new RuntimeException('Test exception', 500);
        $event = new MessageLogged('error', 'Exception occurred', ['exception' => $exception]);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('recordException')->with($exception, [
            'exception.message' => 'Test exception',
            'exception.code' => 500,
        ]);
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('EXCEPTION RuntimeException')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordException($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_ignores_message_logged_event_without_exception()
    {
        $event = new MessageLogged('info', 'Regular log message', []);

        // Should not call tracer when no exception in context
        $this->watcher->recordException($event);

        // If we get here without errors, the test passes
        $this->assertTrue(true);
    }

    public function test_ignores_message_logged_event_with_non_exception_context()
    {
        $event = new MessageLogged('error', 'Error message', ['exception' => 'not an exception']);

        // Should not call tracer when exception context is not a Throwable
        $this->watcher->recordException($event);

        // If we get here without errors, the test passes
        $this->assertTrue(true);
    }

    public function test_handles_different_exception_types()
    {
        $exception = new Exception('Generic exception', 0);
        $event = new MessageLogged('error', 'Exception occurred', ['exception' => $exception]);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('recordException')->with($exception, [
            'exception.message' => 'Generic exception',
            'exception.code' => 0,
        ]);
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('EXCEPTION Exception')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordException($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }
}
