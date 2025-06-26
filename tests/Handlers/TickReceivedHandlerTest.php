<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Laravel\Octane\Events\TickReceived;
use Mockery;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\TickReceivedHandler;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class TickReceivedHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\TickReceived')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_creates_and_ends_tick_span(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock Measure::start with callback
        Measure::shouldReceive('start')
            ->once()
            ->with('octane.tick', Mockery::type('callable'))
            ->andReturnUsing(function ($name, $callback) use ($mockStartedSpan) {
                // Mock SpanBuilder for the callback
                $mockSpanBuilder = Mockery::mock();
                $mockSpanBuilder->shouldReceive('setAttributes')
                    ->once()
                    ->with([
                        'tick.timestamp' => Mockery::type('int'),
                        'tick.type' => 'scheduled',
                    ]);

                // Execute the callback
                $callback($mockSpanBuilder);

                return $mockStartedSpan;
            });

        // Mock span end
        $mockStartedSpan->shouldReceive('end')->once();

        $event = new TickReceived;

        $handler = new TickReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_sets_correct_attributes(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock the current time for testing
        $currentTime = time();

        // Mock Measure::start with callback
        Measure::shouldReceive('start')
            ->once()
            ->with('octane.tick', Mockery::type('callable'))
            ->andReturnUsing(function ($name, $callback) use ($mockStartedSpan, $currentTime) {
                // Mock SpanBuilder for the callback
                $mockSpanBuilder = Mockery::mock();
                $mockSpanBuilder->shouldReceive('setAttributes')
                    ->once()
                    ->with(Mockery::on(function ($attributes) use ($currentTime) {
                        // Verify attributes structure
                        return isset($attributes['tick.timestamp']) &&
                               isset($attributes['tick.type']) &&
                               $attributes['tick.type'] === 'scheduled' &&
                               abs($attributes['tick.timestamp'] - $currentTime) <= 1; // Allow 1 second difference
                    }));

                // Execute the callback
                $callback($mockSpanBuilder);

                return $mockStartedSpan;
            });

        // Mock span end
        $mockStartedSpan->shouldReceive('end')->once();

        $event = new TickReceived;

        $handler = new TickReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_always_ends_span(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock Measure::start
        Measure::shouldReceive('start')
            ->once()
            ->with('octane.tick', Mockery::type('callable'))
            ->andReturnUsing(function ($name, $callback) use ($mockStartedSpan) {
                // Mock SpanBuilder for the callback
                $mockSpanBuilder = Mockery::mock();
                $mockSpanBuilder->shouldReceive('setAttributes')->once();

                // Execute the callback
                $callback($mockSpanBuilder);

                return $mockStartedSpan;
            });

        // This is the key assertion - end() should always be called
        $mockStartedSpan->shouldReceive('end')->once();

        $event = new TickReceived;

        $handler = new TickReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_uses_correct_span_name(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Verify the span name is exactly 'octane.tick'
        Measure::shouldReceive('start')
            ->once()
            ->with('octane.tick', Mockery::type('callable'))
            ->andReturnUsing(function ($name, $callback) use ($mockStartedSpan) {
                // Mock SpanBuilder for the callback
                $mockSpanBuilder = Mockery::mock();
                $mockSpanBuilder->shouldReceive('setAttributes')->once();

                // Execute the callback
                $callback($mockSpanBuilder);

                return $mockStartedSpan;
            });

        // Mock span end
        $mockStartedSpan->shouldReceive('end')->once();

        $event = new TickReceived;

        $handler = new TickReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }
}
