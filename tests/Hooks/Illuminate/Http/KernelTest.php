<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Hooks\Illuminate\Http;

use Mockery;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Hooks\Illuminate\Http\Kernel;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class KernelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_adds_trace_id_to_response_header()
    {
        // Set configuration
        config(['otel.response_trace_header_name' => 'X-Trace-Id']);

        // Mock trace ID
        $expectedTraceId = '12345678901234567890123456789012';
        Measure::shouldReceive('traceId')->andReturn($expectedTraceId);

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method
        $method->invoke($hook, $response);

        // Verify response header is set
        $this->assertEquals($expectedTraceId, $response->headers->get('X-Trace-Id'));
    }

    public function test_does_not_add_header_when_config_is_null()
    {
        // Set configuration to null
        config(['otel.response_trace_header_name' => null]);

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method
        $method->invoke($hook, $response);

        // Verify response header is not set
        $this->assertNull($response->headers->get('X-Trace-Id'));
    }

    public function test_does_not_add_header_when_config_is_empty()
    {
        // Set configuration to empty string
        config(['otel.response_trace_header_name' => '']);

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method
        $method->invoke($hook, $response);

        // Verify response header is not set
        $this->assertNull($response->headers->get('X-Trace-Id'));
    }

    public function test_does_not_add_header_when_trace_id_is_empty()
    {
        // Set configuration
        config(['otel.response_trace_header_name' => 'X-Trace-Id']);

        // Mock empty trace ID
        Measure::shouldReceive('traceId')->andReturn('');

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method
        $method->invoke($hook, $response);

        // Verify response header is not set
        $this->assertNull($response->headers->get('X-Trace-Id'));
    }

    public function test_does_not_add_header_when_trace_id_is_all_zeros()
    {
        // Set configuration
        config(['otel.response_trace_header_name' => 'X-Trace-Id']);

        // Mock all-zeros trace ID (indicates NonRecordingSpan)
        $allZerosTraceId = '00000000000000000000000000000000';
        Measure::shouldReceive('traceId')->andReturn($allZerosTraceId);

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method
        $method->invoke($hook, $response);

        // Verify response header is not set
        $this->assertNull($response->headers->get('X-Trace-Id'));
    }

    public function test_handles_exception_gracefully()
    {
        // Set configuration
        config(['otel.response_trace_header_name' => 'X-Trace-Id']);

        // Mock Measure::traceId() to throw exception
        Measure::shouldReceive('traceId')->andThrow(new \Exception('Test exception'));

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method should not throw exception
        $method->invoke($hook, $response);

        // Verify response header is not set
        $this->assertNull($response->headers->get('X-Trace-Id'));
    }

    public function test_uses_custom_header_name()
    {
        // Set custom header name
        config(['otel.response_trace_header_name' => 'Custom-Trace-Header']);

        // Mock trace ID
        $expectedTraceId = '98765432109876543210987654321098';
        Measure::shouldReceive('traceId')->andReturn($expectedTraceId);

        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Kernel::hook($instrumentation);

        // Create response
        $response = new Response();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('addTraceIdToResponse');
        $method->setAccessible(true);

        // Execute method
        $method->invoke($hook, $response);

        // Verify custom response header is set
        $this->assertEquals($expectedTraceId, $response->headers->get('Custom-Trace-Header'));
        $this->assertNull($response->headers->get('X-Trace-Id')); // Default header should be empty
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
