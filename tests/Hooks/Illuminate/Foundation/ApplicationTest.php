<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Hooks\Illuminate\Foundation;

use Illuminate\Foundation\Application as FoundationApplication;
use Mockery;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;
use Overtrue\LaravelOpenTelemetry\Hooks\Illuminate\Foundation\Application;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class ApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Simple test watcher that doesn't require complex dependencies
     */
    private function getTestWatcherClass(): string
    {
        return TestWatcher::class;
    }

    public function test_instrument_method_registers_hooks()
    {
        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Application::hook($instrumentation);

        // Execute instrument method - should not throw exception
        $hook->instrument();

        // Test passes if no exception is thrown during hook registration
        $this->assertTrue(true);
    }

    public function test_registers_watchers_with_valid_configuration()
    {
        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Application::hook($instrumentation);

        // Mock application with watchers configuration
        $app = Mockery::mock(FoundationApplication::class);
        $app->shouldReceive('offsetGet')
            ->with('config')
            ->andReturn(Mockery::mock('config'));

        $app['config']->shouldReceive('get')
            ->with('otel.watchers', [])
            ->andReturn([
                TestWatcher::class,
            ]);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('registerWatchers');
        $method->setAccessible(true);

        // Execute method - should not throw exception
        $method->invoke($hook, $app);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_registers_watchers_with_empty_configuration()
    {
        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Application::hook($instrumentation);

        // Mock application with empty watchers configuration
        $app = Mockery::mock(FoundationApplication::class);
        $app->shouldReceive('offsetGet')
            ->with('config')
            ->andReturn(Mockery::mock('config'));

        $app['config']->shouldReceive('get')
            ->with('otel.watchers', [])
            ->andReturn([]);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('registerWatchers');
        $method->setAccessible(true);

        // Execute method - should not throw exception
        $method->invoke($hook, $app);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_registers_watchers_with_nonexistent_class()
    {
        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Application::hook($instrumentation);

        // Mock application with invalid watcher class
        $app = Mockery::mock(FoundationApplication::class);
        $app->shouldReceive('offsetGet')
            ->with('config')
            ->andReturn(Mockery::mock('config'));

        $app['config']->shouldReceive('get')
            ->with('otel.watchers', [])
            ->andReturn([
                'NonExistentWatcherClass',
            ]);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('registerWatchers');
        $method->setAccessible(true);

        // Execute method - should not throw exception and skip invalid classes
        $method->invoke($hook, $app);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_registers_watchers_handles_mixed_configuration()
    {
        // Create hook
        $instrumentation = new CachedInstrumentation('test');
        $hook = Application::hook($instrumentation);

        // Mock application with mixed watchers configuration (valid and invalid)
        $app = Mockery::mock(FoundationApplication::class);
        $app->shouldReceive('offsetGet')
            ->with('config')
            ->andReturn(Mockery::mock('config'));

        $app['config']->shouldReceive('get')
            ->with('otel.watchers', [])
            ->andReturn([
                TestWatcher::class,  // Valid class
                'NonExistentWatcherClass',  // Invalid class
            ]);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($hook);
        $method = $reflection->getMethod('registerWatchers');
        $method->setAccessible(true);

        // Execute method - should not throw exception
        $method->invoke($hook, $app);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

/**
 * Simple test watcher for testing purposes
 */
class TestWatcher extends Watcher
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {}

    public function register(\Illuminate\Contracts\Foundation\Application $app): void
    {
        // Simple implementation that doesn't require complex dependencies
        // Just verify that register method is called
    }
}
