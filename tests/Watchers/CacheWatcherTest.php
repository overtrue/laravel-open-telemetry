<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Mockery;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\CacheWatcher;

class CacheWatcherTest extends TestCase
{
    private CacheWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new CacheWatcher;
    }

    public function test_registers_cache_event_listeners()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(CacheHit::class, Mockery::type('callable'))->once();
        $events->shouldReceive('listen')->with(CacheMissed::class, Mockery::type('callable'))->once();
        $events->shouldReceive('listen')->with(KeyWritten::class, Mockery::type('callable'))->once();
        $events->shouldReceive('listen')->with(KeyForgotten::class, Mockery::type('callable'))->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_cache_hit_event()
    {
        $event = new CacheHit('redis', 'test-key', 'test-value', ['store' => 'redis']);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('cache.hit', [
                'cache.key' => 'test-key',
                'cache.store' => 'redis',
            ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('recordEvent');
        $method->setAccessible(true);
        $method->invoke($this->watcher, 'cache.hit', $event);
    }

    public function test_records_cache_miss_event()
    {
        $event = new CacheMissed('file', 'test-key', ['store' => 'file']);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('cache.miss', [
                'cache.key' => 'test-key',
                'cache.store' => 'file',
            ]);

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('recordEvent');
        $method->setAccessible(true);
        $method->invoke($this->watcher, 'cache.miss', $event);
    }

    public function test_records_cache_set_event_with_ttl()
    {
        $event = new KeyWritten('redis', 'test-key', 'test-value', 3600, ['store' => 'redis']);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('cache.set', [
                'cache.key' => 'test-key',
                'cache.store' => 'redis',
                'cache.ttl' => 3600,
            ]);

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('recordEvent');
        $method->setAccessible(true);
        $method->invoke($this->watcher, 'cache.set', $event);
    }

    public function test_records_cache_forget_event()
    {
        $event = new KeyForgotten('database', 'test-key', ['store' => 'database']);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('cache.forget', [
                'cache.key' => 'test-key',
                'cache.store' => 'database',
            ]);

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('recordEvent');
        $method->setAccessible(true);
        $method->invoke($this->watcher, 'cache.forget', $event);
    }

    public function test_handles_missing_store_name()
    {
        $event = new CacheHit(null, 'test-key', 'test-value', ['store' => 'redis']);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('cache.hit', [
                'cache.key' => 'test-key',
                'cache.store' => null,
            ]);

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('recordEvent');
        $method->setAccessible(true);
        $method->invoke($this->watcher, 'cache.hit', $event);
    }

    public function test_get_store_name_method()
    {
        $event = new CacheHit('redis', 'test-key', 'test-value', ['store' => 'redis']);

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('getStoreName');
        $method->setAccessible(true);
        $result = $method->invoke($this->watcher, $event);

        $this->assertEquals('redis', $result);
    }

    public function test_get_store_name_returns_null_when_missing()
    {
        $event = new CacheHit(null, 'test-key', 'test-value', ['store' => 'redis']);

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('getStoreName');
        $method->setAccessible(true);
        $result = $method->invoke($this->watcher, $event);

        $this->assertNull($result);
    }
}
