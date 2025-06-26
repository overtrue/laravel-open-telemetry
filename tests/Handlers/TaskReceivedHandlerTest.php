<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Laravel\Octane\Events\TaskReceived;
use Mockery;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\TaskReceivedHandler;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class TaskReceivedHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\TaskReceived')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_creates_task_span(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock Measure::start to return a StartedSpan
        Measure::shouldReceive('start')
            ->once()
            ->with('TASK test-task')
            ->andReturn($mockStartedSpan);

        // Mock setAttributes method
        $mockStartedSpan->shouldReceive('setAttributes')
            ->once()
            ->with([
                'task.name' => 'test-task',
                'task.payload' => '{"key":"value"}',
            ]);

        $payload = ['key' => 'value'];
        $event = new TaskReceived('test-task', $payload);

        $handler = new TaskReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_creates_span_with_empty_payload(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock Measure::start to return a StartedSpan
        Measure::shouldReceive('start')
            ->once()
            ->with('TASK empty-task')
            ->andReturn($mockStartedSpan);

        // Mock setAttributes method with empty payload
        $mockStartedSpan->shouldReceive('setAttributes')
            ->once()
            ->with([
                'task.name' => 'empty-task',
                'task.payload' => '[]',
            ]);

        $event = new TaskReceived('empty-task', []);

        $handler = new TaskReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_creates_span_with_complex_payload(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock Measure::start to return a StartedSpan
        Measure::shouldReceive('start')
            ->once()
            ->with('TASK complex-task')
            ->andReturn($mockStartedSpan);

        $complexPayload = [
            'user_id' => 123,
            'data' => ['nested' => 'value'],
            'options' => ['flag' => true, 'count' => 42],
        ];

        // Mock setAttributes method with complex payload
        $mockStartedSpan->shouldReceive('setAttributes')
            ->once()
            ->with([
                'task.name' => 'complex-task',
                'task.payload' => json_encode($complexPayload),
            ]);

        $event = new TaskReceived('complex-task', $complexPayload);

        $handler = new TaskReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_creates_span_with_null_payload(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Mock Measure::start to return a StartedSpan
        Measure::shouldReceive('start')
            ->once()
            ->with('TASK null-task')
            ->andReturn($mockStartedSpan);

        // Mock setAttributes method with null payload
        $mockStartedSpan->shouldReceive('setAttributes')
            ->once()
            ->with([
                'task.name' => 'null-task',
                'task.payload' => 'null',
            ]);

        $event = new TaskReceived('null-task', null);

        $handler = new TaskReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_creates_span_name_correctly(): void
    {
        // Mock StartedSpan
        $mockStartedSpan = Mockery::mock(StartedSpan::class);

        // Test that span name is formatted correctly with TASK prefix
        Measure::shouldReceive('start')
            ->once()
            ->with('TASK my-custom-task-name')
            ->andReturn($mockStartedSpan);

        $mockStartedSpan->shouldReceive('setAttributes')
            ->once()
            ->with([
                'task.name' => 'my-custom-task-name',
                'task.payload' => '{"test":true}',
            ]);

        $event = new TaskReceived('my-custom-task-name', ['test' => true]);

        $handler = new TaskReceivedHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }
}
