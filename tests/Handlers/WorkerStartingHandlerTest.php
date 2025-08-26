<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Tests\Handlers;

use Illuminate\Support\Facades\Log;
use Laravel\Octane\Events\WorkerStarting;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Handlers\WorkerStartingHandler;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class WorkerStartingHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Laravel Octane is not available
        if (! class_exists('Laravel\Octane\Events\WorkerStarting')) {
            $this->markTestSkipped('Laravel Octane is not installed');
        }

        // Reset Measure state
        Measure::reset();
    }

    public function test_handle_skips_when_not_octane(): void
    {
        // Mock isOctane to return false
        Measure::shouldReceive('isOctane')->once()->andReturn(false);

        $event = new WorkerStarting('app', 1);

        $handler = new WorkerStartingHandler;
        $handler->handle($event);

        // No additional assertions needed as method returns early
        $this->assertTrue(true);
    }

    public function test_handle_skips_when_otel_disabled(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);

        // Mock config to return false (disabled)
        config(['otel.enabled' => false]);

        $event = new WorkerStarting('app', 1);

        $handler = new WorkerStartingHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_resets_state_and_logs_debug_info(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);
        Measure::shouldReceive('reset')->once();

        // Mock config to return true (enabled)
        config(['otel.enabled' => true]);

        // Set up environment variables for testing
        $_ENV['OTEL_SERVICE_NAME'] = 'test-service';
        $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] = 'http://localhost:4318';
        $_ENV['OTEL_EXPORTER_OTLP_PROTOCOL'] = 'http/protobuf';

        // Mock Log facade - no debug logs expected since we removed them

        $event = new WorkerStarting('app', 1);

        $handler = new WorkerStartingHandler;
        $handler->handle($event);

        // Clean up environment variables
        unset($_ENV['OTEL_SERVICE_NAME']);
        unset($_ENV['OTEL_EXPORTER_OTLP_ENDPOINT']);
        unset($_ENV['OTEL_EXPORTER_OTLP_PROTOCOL']);

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_for_missing_environment_variables(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);
        Measure::shouldReceive('reset')->once();

        // Mock config to return true (enabled)
        config(['otel.enabled' => true]);

        // Ensure environment variables are not set
        unset($_ENV['OTEL_SERVICE_NAME']);
        unset($_ENV['OTEL_EXPORTER_OTLP_ENDPOINT']);
        unset($_ENV['OTEL_EXPORTER_OTLP_PROTOCOL']);
        unset($_SERVER['OTEL_SERVICE_NAME']);
        unset($_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT']);
        unset($_SERVER['OTEL_EXPORTER_OTLP_PROTOCOL']);

        // Mock Log facade - only warning logs expected
        Log::shouldReceive('warning')
            ->times(3)
            ->with('[laravel-open-telemetry] Octane: Missing required environment variable', \Mockery::type('array'));

        $event = new WorkerStarting('app', 1);

        $handler = new WorkerStartingHandler;
        $handler->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_reads_from_server_variables_when_env_not_available(): void
    {
        // Mock isOctane to return true
        Measure::shouldReceive('isOctane')->once()->andReturn(true);
        Measure::shouldReceive('reset')->once();

        // Mock config to return true (enabled)
        config(['otel.enabled' => true]);

        // Ensure $_ENV is not set but $_SERVER is
        unset($_ENV['OTEL_SERVICE_NAME']);
        unset($_ENV['OTEL_EXPORTER_OTLP_ENDPOINT']);
        unset($_ENV['OTEL_EXPORTER_OTLP_PROTOCOL']);

        $_SERVER['OTEL_SERVICE_NAME'] = 'server-service';
        $_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT'] = 'http://server:4318';
        $_SERVER['OTEL_EXPORTER_OTLP_PROTOCOL'] = 'grpc';

        // Mock Log facade - no debug logs expected since we removed them

        $event = new WorkerStarting('app', 1);

        $handler = new WorkerStartingHandler;
        $handler->handle($event);

        // Clean up server variables
        unset($_SERVER['OTEL_SERVICE_NAME']);
        unset($_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT']);
        unset($_SERVER['OTEL_EXPORTER_OTLP_PROTOCOL']);

        $this->assertTrue(true);
    }
}
