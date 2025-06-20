<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Mockery;
use Overtrue\LaravelOpenTelemetry\Console\Commands\TestCommand;
use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;
use Overtrue\LaravelOpenTelemetry\Support\Measure;

class OpenTelemetryServiceProviderTest extends TestCase
{
    public function test_provider_registers_measure_singleton()
    {
        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Register services
        $provider->register();

        // Verify Measure singleton is registered
        $this->assertTrue($this->app->bound(Measure::class));
        $this->assertInstanceOf(Measure::class, $this->app->make(Measure::class));
    }

    public function test_provider_merges_config()
    {
        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Register services
        $provider->register();

        // Verify config is merged
        $this->assertNotNull(config('otel'));
        $this->assertIsArray(config('otel.watchers'));
        $this->assertIsString(config('otel.response_trace_header_name'));
    }

    public function test_provider_publishes_config()
    {
        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Boot services
        $provider->boot();

        // Get published config files
        $publishes = $provider::$publishes[OpenTelemetryServiceProvider::class] ?? [];

        // Verify config is published
        $this->assertNotEmpty($publishes);
        $expectedConfigPath = $this->app->configPath('otel.php');
        $this->assertContains($expectedConfigPath, array_values($publishes));
    }

    public function test_provider_registers_guzzle_macro()
    {
        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Boot services
        $provider->boot();

        // Verify Guzzle macro is registered
        $this->assertTrue(PendingRequest::hasMacro('withTrace'));
    }

    public function test_provider_registers_console_commands()
    {
        // Set application as running in console
        $this->app->instance('env', 'testing');

        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('registerCommands');
        $method->setAccessible(true);

        // Mock the commands method
        $provider = Mockery::mock(OpenTelemetryServiceProvider::class, [$this->app])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $provider->shouldReceive('commands')
            ->with([TestCommand::class])
            ->once();

        $method->invoke($provider);

        // Verify the expectation was met
        $this->assertTrue(true); // Mockery will fail if expectation not met
    }

    public function test_guzzle_macro_returns_request_with_middleware()
    {
        // Create service provider and boot it
        $provider = new OpenTelemetryServiceProvider($this->app);
        $provider->boot();

        // Create a PendingRequest instance
        $request = new PendingRequest;

        // Use the withTrace macro
        $requestWithTrace = $request->withTrace();

        // Verify it returns a PendingRequest instance
        $this->assertInstanceOf(PendingRequest::class, $requestWithTrace);
    }

    public function test_provider_logs_startup_and_registration()
    {
        // Mock Log facade
        Log::shouldReceive('debug')
            ->with('[laravel-open-telemetry] started', Mockery::type('array'))
            ->once();

        Log::shouldReceive('debug')
            ->with('[laravel-open-telemetry] registered.')
            ->once();

        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Register and boot
        $provider->register();
        $provider->boot();

        // Verify the expectation was met
        $this->assertTrue(true); // Mockery will fail if expectation not met
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
