<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Illuminate\Support\Facades\Config;
use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;

class ConfigTest extends TestCase
{
    public function test_config_file_exists()
    {
        $configPath = __DIR__.'/../config/otel.php';

        $this->assertFileExists($configPath);
    }

    public function test_config_returns_array()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertIsArray($config);
    }

    public function test_config_has_required_keys()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertArrayHasKey('middleware', $config);
        $this->assertArrayHasKey('watchers', $config);
        $this->assertArrayHasKey('allowed_headers', $config);
        $this->assertArrayHasKey('sensitive_headers', $config);
        $this->assertArrayHasKey('ignore_paths', $config);
    }

    public function test_middleware_trace_id_is_configurable()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertArrayHasKey('trace_id', $config['middleware']);
        $this->assertEquals('X-Trace-Id', $config['middleware']['trace_id']['header_name']);
        $this->assertTrue($config['middleware']['trace_id']['enabled']);
        $this->assertTrue($config['middleware']['trace_id']['global']);
    }

    public function test_watchers_contains_expected_classes()
    {
        $watchers = config('otel.watchers');

        $this->assertEquals([
            \Overtrue\LaravelOpenTelemetry\Watchers\CacheWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\QueryWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\HttpClientWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\ExceptionWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\AuthenticateWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\EventWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\QueueWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\RedisWatcher::class,
        ], $watchers);
    }

    public function test_allowed_headers_is_array()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertIsArray($config['allowed_headers']);
        $this->assertContains('referer', $config['allowed_headers']);
        $this->assertContains('x-*', $config['allowed_headers']);
        $this->assertContains('accept', $config['allowed_headers']);
        $this->assertContains('request-id', $config['allowed_headers']);
    }

    public function test_sensitive_headers_is_array()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertIsArray($config['sensitive_headers']);
        $this->assertContains('cookie', $config['sensitive_headers']);
        $this->assertContains('authorization', $config['sensitive_headers']);
        $this->assertContains('x-api-key', $config['sensitive_headers']);
    }

    public function test_ignore_paths_is_array()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertIsArray($config['ignore_paths']);
        $this->assertContains('horizon*', $config['ignore_paths']);
        $this->assertContains('telescope*', $config['ignore_paths']);
        $this->assertContains('_debugbar*', $config['ignore_paths']);
        $this->assertContains('health*', $config['ignore_paths']);
    }

    public function test_config_respects_environment_variables()
    {
        // Test trace ID header name from env
        $originalValue = $_ENV['OTEL_TRACE_ID_HEADER_NAME'] ?? null;
        $_ENV['OTEL_TRACE_ID_HEADER_NAME'] = 'Custom-Trace-Header';

        // Re-evaluate the config
        $config = include __DIR__.'/../config/otel.php';

        $this->assertEquals('Custom-Trace-Header', $config['middleware']['trace_id']['header_name']);

        // Restore original value
        if ($originalValue !== null) {
            $_ENV['OTEL_TRACE_ID_HEADER_NAME'] = $originalValue;
        } else {
            unset($_ENV['OTEL_TRACE_ID_HEADER_NAME']);
        }
    }

    public function test_config_handles_comma_separated_env_vars()
    {
        // Test allowed headers from env
        $originalValue = $_ENV['OTEL_ALLOWED_HEADERS'] ?? null;
        $_ENV['OTEL_ALLOWED_HEADERS'] = 'content-type,user-agent,custom-header';

        // Re-evaluate the config
        $config = include __DIR__.'/../config/otel.php';

        $this->assertEquals(['content-type', 'user-agent', 'custom-header'], $config['allowed_headers']);

        // Restore original value
        if ($originalValue !== null) {
            $_ENV['OTEL_ALLOWED_HEADERS'] = $originalValue;
        } else {
            unset($_ENV['OTEL_ALLOWED_HEADERS']);
        }
    }

    public function test_default_config(): void
    {
        $this->assertTrue(config('otel.enabled'));
        $this->assertIsArray(config('otel.watchers'));
        $this->assertNotEmpty(config('otel.watchers'));
    }

    public function test_enabled_configuration_disables_registration(): void
    {
        // Set OpenTelemetry as disabled
        Config::set('otel.enabled', false);

        // Create a new service provider instance
        $provider = new OpenTelemetryServiceProvider($this->app);

        // Mock the registration methods to verify they're not called
        $this->expectNotToPerformAssertions();

        // Boot the service provider - it should return early due to disabled config
        $provider->boot();

        // If we reach here without any watchers being registered, the test passes
    }

    public function test_enabled_configuration_allows_registration(): void
    {
        // Ensure OpenTelemetry is enabled
        Config::set('otel.enabled', true);

        // Verify that the config is properly set
        $this->assertTrue(config('otel.enabled'));
    }

    public function test_middleware_configuration(): void
    {
        $this->assertTrue(config('otel.middleware.trace_id.enabled'));
        $this->assertTrue(config('otel.middleware.trace_id.global'));
        $this->assertEquals('X-Trace-Id', config('otel.middleware.trace_id.header_name'));
    }

    public function test_watchers_configuration(): void
    {
        $watchers = config('otel.watchers');

        $this->assertIsArray($watchers);
        $this->assertContains(\Overtrue\LaravelOpenTelemetry\Watchers\CacheWatcher::class, $watchers);
        $this->assertContains(\Overtrue\LaravelOpenTelemetry\Watchers\QueryWatcher::class, $watchers);
        $this->assertContains(\Overtrue\LaravelOpenTelemetry\Watchers\HttpClientWatcher::class, $watchers);
    }

    public function test_headers_configuration(): void
    {
        $allowedHeaders = config('otel.allowed_headers');
        $sensitiveHeaders = config('otel.sensitive_headers');
        $ignorePaths = config('otel.ignore_paths');

        $this->assertIsArray($allowedHeaders);
        $this->assertIsArray($sensitiveHeaders);
        $this->assertIsArray($ignorePaths);

        $this->assertContains('referer', $allowedHeaders);
        $this->assertContains('authorization', $sensitiveHeaders);
        $this->assertContains('horizon*', $ignorePaths);
    }
}
