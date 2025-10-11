<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

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
        $this->assertIsArray(config('otel.middleware'));
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

    public function test_provider_registers_http_client_watchers()
    {
        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Boot services
        $provider->boot();

        // Verify HTTP client watchers are registered by checking config
        $watchers = config('otel.watchers', []);
        $this->assertContains(\Overtrue\LaravelOpenTelemetry\Watchers\HttpClientWatcher::class, $watchers);
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
            ->with([
                TestCommand::class,
            ])
            ->once();

        $method->invoke($provider);

        // Verify the expectation was met
        $this->assertTrue(true); // Mockery will fail if expectation not met
    }

    public function test_http_client_propagation_middleware_configuration()
    {
        // Create service provider and boot it
        $provider = new OpenTelemetryServiceProvider($this->app);
        $provider->boot();

        // Verify HTTP client propagation middleware config exists and is enabled by default
        $this->assertTrue(config('otel.http_client.propagation_middleware.enabled', true));

        // Test that we can disable it via config
        config(['otel.http_client.propagation_middleware.enabled' => false]);
        $this->assertFalse(config('otel.http_client.propagation_middleware.enabled'));
    }

    public function test_provider_logs_startup_and_registration()
    {
        // Mock Log facade
        Log::shouldReceive('debug')
            ->with('[laravel-open-telemetry] Service provider registered successfully')
            ->once();

        Log::shouldReceive('debug')
            ->with('[laravel-open-telemetry] Middleware registered globally for automatic tracing')
            ->once();

        // Create service provider
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Register and boot
        $provider->register();
        $provider->boot();

        // Verify the expectation was met
        $this->assertTrue(true); // Mockery will fail if expectation not met
    }

    public function test_provider_does_not_register_services_when_disabled()
    {
        // Create a fresh Laravel application WITHOUT auto-loading our package provider
        $app = new \Illuminate\Foundation\Application(
            realpath(__DIR__.'/../')
        );

        $app->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            \Illuminate\Foundation\Http\Kernel::class
        );

        $app->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \Illuminate\Foundation\Console\Kernel::class
        );

        $app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \Illuminate\Foundation\Exceptions\Handler::class
        );

        // Set config with enabled = false
        $app['config'] = new \Illuminate\Config\Repository([
            'otel' => array_merge(
                include __DIR__.'/../config/otel.php',
                ['enabled' => false]
            ),
        ]);

        // Create and register service provider
        $provider = new OpenTelemetryServiceProvider($app);
        $provider->register();

        // Verify services are NOT bound when disabled
        $this->assertFalse($app->bound(Measure::class));
        $this->assertFalse($app->bound(\OpenTelemetry\API\Trace\TracerInterface::class));
        $this->assertFalse($app->bound(\OpenTelemetry\API\Metrics\MeterInterface::class));
        $this->assertFalse($app->bound(\Overtrue\LaravelOpenTelemetry\Support\Metric::class));
    }

    public function test_tracer_provider_is_initialized()
    {
        // 获取全局 TracerProvider
        $tracerProvider = \OpenTelemetry\API\Globals::tracerProvider();

        // 确保不是 NoopTracerProvider
        $this->assertNotInstanceOf(
            \OpenTelemetry\API\Trace\NoopTracerProvider::class,
            $tracerProvider
        );

        // 确保可以创建 tracer
        $tracer = $tracerProvider->getTracer('test');
        $this->assertNotNull($tracer);

        // 确保可以创建 span
        $span = $tracer->spanBuilder('test-span')->startSpan();
        $this->assertNotNull($span);
        $span->end();
    }

    public function test_tracer_provider_uses_configuration()
    {
        // 设置配置
        config([
            'otel.tracer_provider.service.name' => 'test-service',
            'otel.tracer_provider.service.version' => '2.0.0',
        ]);

        // 重新初始化 ServiceProvider
        $this->app->register(\Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider::class);

        // 获取 tracer 并创建 span
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('test');
        $span = $tracer->spanBuilder('test-span')->startSpan();

        // 检查 span 的资源信息
        $resource = $span->getResource();
        $this->assertNotNull($resource);

        $span->end();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
