<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

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

        $this->assertArrayHasKey('response_trace_header_name', $config);
        $this->assertArrayHasKey('watchers', $config);
        $this->assertArrayHasKey('allowed_headers', $config);
        $this->assertArrayHasKey('sensitive_headers', $config);
        $this->assertArrayHasKey('ignore_paths', $config);
    }

    public function test_response_trace_header_name_is_configurable()
    {
        $config = include __DIR__.'/../config/otel.php';

        $this->assertEquals('X-Trace-Id', $config['response_trace_header_name']);
    }

    public function test_watchers_contains_expected_classes()
    {
        $config = include __DIR__.'/../config/otel.php';

        $expectedWatchers = [
            \Overtrue\LaravelOpenTelemetry\Watchers\ExceptionWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\AuthenticateWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\EventWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\QueueWatcher::class,
            \Overtrue\LaravelOpenTelemetry\Watchers\RedisWatcher::class,
        ];

        $this->assertEquals($expectedWatchers, $config['watchers']);
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
        // Test response trace header name from env
        $originalValue = $_ENV['OTEL_RESPONSE_TRACE_HEADER_NAME'] ?? null;
        $_ENV['OTEL_RESPONSE_TRACE_HEADER_NAME'] = 'Custom-Trace-Header';

        // Re-evaluate the config
        $config = include __DIR__.'/../config/otel.php';

        $this->assertEquals('Custom-Trace-Header', $config['response_trace_header_name']);

        // Restore original value
        if ($originalValue !== null) {
            $_ENV['OTEL_RESPONSE_TRACE_HEADER_NAME'] = $originalValue;
        } else {
            unset($_ENV['OTEL_RESPONSE_TRACE_HEADER_NAME']);
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
}
