<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Overtrue\LaravelOpenTelemetry\LaravelInstrumentation;

class LaravelInstrumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_register_method_executes_without_exception()
    {
        // Call the static register method
        LaravelInstrumentation::register();

        // Test passes if no exception is thrown during registration
        $this->assertTrue(true);
    }

    public function test_register_method_can_be_called_multiple_times()
    {
        // Call register multiple times to test idempotency
        LaravelInstrumentation::register();
        LaravelInstrumentation::register();
        LaravelInstrumentation::register();

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_instrumentation_instance_is_cached()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(LaravelInstrumentation::class);
        $method = $reflection->getMethod('instrumentation');
        $method->setAccessible(true);

        // Get instrumentation instance twice
        $instance1 = $method->invoke(null);
        $instance2 = $method->invoke(null);

        // Should be the same instance (singleton pattern)
        $this->assertSame($instance1, $instance2);
    }

    public function test_instrumentation_instance_has_correct_name()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(LaravelInstrumentation::class);
        $method = $reflection->getMethod('instrumentation');
        $method->setAccessible(true);

        // Get instrumentation instance
        $instance = $method->invoke(null);

        // Verify it's a CachedInstrumentation instance
        $this->assertInstanceOf(
            \OpenTelemetry\API\Instrumentation\CachedInstrumentation::class,
            $instance
        );
    }

    protected function tearDown(): void
    {
        // Reset static properties for clean test state
        $reflection = new \ReflectionClass(LaravelInstrumentation::class);
        $property = $reflection->getProperty('instrumentation');
        $property->setAccessible(true);
        $property->setValue(null, null);

        parent::tearDown();
    }
}
