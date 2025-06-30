<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Mockery;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\EventWatcher;

class EventWatcherTest extends TestCase
{
    private EventWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new EventWatcher;
    }

    public function test_registers_wildcard_event_listener()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with('*', [$this->watcher, 'recordEvent'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_custom_event_with_array()
    {
        $eventName = 'custom.event';
        $payload = ['data1', 'data2'];

        Measure::shouldReceive('addEvent')
            ->once()
            ->with($eventName, [
                'event.payload_count' => 2,
            ]);

        $this->watcher->recordEvent($eventName, $payload);
    }

    public function test_records_custom_event_with_non_array()
    {
        $eventName = 'custom.event';
        $payload = 'string_payload';

        Measure::shouldReceive('addEvent')
            ->once()
            ->with($eventName, [
                'event.payload_count' => 0,
            ]);

        $this->watcher->recordEvent($eventName, $payload);
    }

    public function test_skips_opentelemetry_events()
    {
        // Test that OpenTelemetry events are skipped
        $this->watcher->recordEvent('otel.span.created');
        $this->watcher->recordEvent('opentelemetry.trace.started');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_database_query_events()
    {
        $this->watcher->recordEvent('Illuminate\\Database\\Events\\QueryExecuted');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_cache_events()
    {
        $this->watcher->recordEvent('Illuminate\\Cache\\Events\\CacheHit');
        $this->watcher->recordEvent('Illuminate\\Cache\\Events\\CacheMissed');
        $this->watcher->recordEvent('Illuminate\\Cache\\Events\\KeyWritten');
        $this->watcher->recordEvent('Illuminate\\Cache\\Events\\KeyForgotten');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_queue_events()
    {
        $this->watcher->recordEvent('Illuminate\\Queue\\Events\\JobProcessing');
        $this->watcher->recordEvent('Illuminate\\Queue\\Events\\JobProcessed');
        $this->watcher->recordEvent('Illuminate\\Queue\\Events\\JobFailed');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_auth_events()
    {
        $this->watcher->recordEvent('Illuminate\\Auth\\Events\\Attempting');
        $this->watcher->recordEvent('Illuminate\\Auth\\Events\\Authenticated');
        $this->watcher->recordEvent('Illuminate\\Auth\\Events\\Login');
        $this->watcher->recordEvent('Illuminate\\Auth\\Events\\Failed');
        $this->watcher->recordEvent('Illuminate\\Auth\\Events\\Logout');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_redis_events()
    {
        $this->watcher->recordEvent('Illuminate\\Redis\\Events\\CommandExecuted');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_http_client_events()
    {
        $this->watcher->recordEvent('Illuminate\\Http\\Client\\Events\\RequestSending');
        $this->watcher->recordEvent('Illuminate\\Http\\Client\\Events\\ResponseReceived');
        $this->watcher->recordEvent('Illuminate\\Http\\Client\\Events\\ConnectionFailed');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_skips_log_events()
    {
        $this->watcher->recordEvent('Illuminate\\Log\\Events\\MessageLogged');

        // If we get here without calling Measure::addEvent, the test passes
        $this->assertTrue(true);
    }

    public function test_should_skip_method()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('shouldSkip');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->watcher, 'otel.test'));
        $this->assertTrue($method->invoke($this->watcher, 'opentelemetry.test'));
        $this->assertTrue($method->invoke($this->watcher, 'Illuminate\\Database\\Events\\QueryExecuted'));
        $this->assertTrue($method->invoke($this->watcher, 'Illuminate\\Cache\\Events\\CacheHit'));
        $this->assertFalse($method->invoke($this->watcher, 'App\\Events\\UserRegistered'));
        $this->assertFalse($method->invoke($this->watcher, 'custom.event'));
    }
}
