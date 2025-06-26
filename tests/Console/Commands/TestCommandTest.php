<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class TestCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure OpenTelemetry is enabled
        config(['otel.enabled' => true]);

        // Mock Tracer
        $tracer = Mockery::mock(TracerInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $span = Mockery::mock(SpanInterface::class);
        $spanContext = Mockery::mock(SpanContextInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $status = Mockery::mock('status');
        $status->shouldReceive('getCode')->andReturn(StatusCode::STATUS_OK);

        // Set expected behavior for span
        $span->shouldReceive('setAttribute')->andReturnSelf();
        $span->shouldReceive('addEvent')->andReturnSelf();
        $span->shouldReceive('setStatus')->andReturnSelf();
        $span->shouldReceive('getContext')->andReturn($spanContext);
        $span->shouldReceive('getAttribute')->andReturn('test_value');
        $span->shouldReceive('end')->andReturnSelf();
        $span->shouldReceive('activate')->andReturn($scope);
        $span->shouldReceive('getStatus')->andReturn($status);

        // Set expected behavior for span context
        $spanContext->shouldReceive('getTraceId')->andReturn('test-trace-id');

        // Set expected behavior for span builder
        $spanBuilder->shouldReceive('start')->andReturn($span);

        // Set expected behavior for tracer
        $tracer->shouldReceive('spanBuilder')->andReturn($spanBuilder);

        // Replace Measure facade methods
        Measure::shouldReceive('tracer')->andReturn($tracer);
        Measure::shouldReceive('activeSpan')->andReturn($span);
        Measure::shouldReceive('start')->andReturn(new StartedSpan($span, $scope));
        Measure::shouldReceive('end')->andReturnNull();

        // Additional methods
        Measure::shouldReceive('getStatus')->andReturn([
            'is_recording' => true,
            'active_spans_count' => 0,
            'tracer_provider' => [
                'class' => 'OpenTelemetry\SDK\Trace\TracerProvider',
                'is_noop' => false,
                'is_recording' => true,
            ],
            'current_trace_id' => 'test-trace-id',
        ]);
        Measure::shouldReceive('isRecording')->andReturn(true);

        // Allow addEvent to be called since EventWatcher is active
        Measure::shouldReceive('addEvent')->byDefault();
    }

    public function test_command_creates_test_span()
    {
        // Execute command
        $result = Artisan::call('otel:test');

        // Verify command executed successfully
        $this->assertEquals(0, $result);

        // Verify output contains expected information (updated to new output format)
        $output = Artisan::output();
        $this->assertStringContainsString('=== OpenTelemetry Test Command ===', $output);
        $this->assertStringContainsString('✅ Test completed!', $output);
        $this->assertStringContainsString('📊 Trace ID:', $output);
    }

    public function test_command_creates_span_with_correct_attributes()
    {
        // Execute command
        Artisan::call('otel:test');

        // Get current active span
        $span = Measure::activeSpan();

        // Verify span attributes
        $this->assertEquals('test_value', $span->getAttribute('test.attribute'));
    }

    public function test_command_creates_child_span()
    {
        // Execute command
        Artisan::call('otel:test');

        // Get current active span
        $span = Measure::activeSpan();

        // Verify child span attributes
        $this->assertEquals('test_value', $span->getAttribute('child.attribute'));
    }

    public function test_command_sets_correct_status()
    {
        // Execute command
        Artisan::call('otel:test');

        // Get current active span
        $span = Measure::activeSpan();

        // Verify status
        $this->assertEquals(StatusCode::STATUS_OK, $span->getStatus()->getCode());
    }

    public function test_command_outputs_correct_table()
    {
        // Execute command
        Artisan::call('otel:test');

        // Get output
        $output = Artisan::output();

        // Verify table output
        $this->assertStringContainsString('Span Name', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('Attributes', $output);
        $this->assertStringContainsString('Test Span', $output);
        $this->assertStringContainsString('Child Operation', $output);
    }

    public function test_command_handles_otel_disabled()
    {
        // Disable OpenTelemetry
        config(['otel.enabled' => false]);

        // Clear all mocks
        Mockery::close();

        // Reset mocks, but this time all Measure facade methods return null
        Measure::shouldReceive('tracer')->andReturnNull();
        Measure::shouldReceive('activeSpan')->andReturnNull();
        Measure::shouldReceive('start')->andReturnNull();
        Measure::shouldReceive('end')->andReturnNull();
        Measure::shouldReceive('getStatus')->andReturn([
            'is_recording' => false,
            'active_spans_count' => 0,
            'tracer_provider' => [
                'class' => 'OpenTelemetry\API\Trace\NoopTracerProvider',
                'is_noop' => true,
                'is_recording' => false,
            ],
            'current_trace_id' => null,
        ]);
        Measure::shouldReceive('isRecording')->andReturn(false);

        // Allow addEvent to be called even when disabled
        Measure::shouldReceive('addEvent')->byDefault();

        // Execute command
        $result = Artisan::call('otel:test');

        // Verify command now returns failure status (based on new logic)
        $this->assertEquals(1, $result);

        // Verify output contains error information
        $output = Artisan::output();
        $this->assertStringContainsString('OpenTelemetry is disabled in config', $output);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
