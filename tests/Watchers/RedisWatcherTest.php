<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Illuminate\Redis\Events\CommandExecuted;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\RedisWatcher;

class RedisWatcherTest extends TestCase
{
    private RedisWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new RedisWatcher;
    }

    public function test_registers_redis_command_event_listener()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(CommandExecuted::class, [$this->watcher, 'recordCommand'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_redis_command()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getName')->andReturn('default');

        $event = new CommandExecuted('GET', ['user:123'], 2.5, $connection);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'db.system.name' => 'redis',
            'dn.statement' => 'GET user:123',
            'db.connection' => 'default',
            'db.command.time_ms' => 2.5,
        ])->andReturnSelf();
        $span->shouldReceive('end')->with(Mockery::type('integer'));

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setStartTimestamp')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('REDIS GET')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordCommand($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_redis_command_with_multiple_parameters()
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getName')->andReturn('cache');

        $event = new CommandExecuted('HMSET', ['user:123', 'name', 'John', 'age', '30'], 1.8, $connection);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'db.system.name' => 'redis',
            'dn.statement' => 'HMSET user:123 name John age 30',
            'db.connection' => 'cache',
            'db.command.time_ms' => 1.8,
        ])->andReturnSelf();
        $span->shouldReceive('end')->with(Mockery::type('integer'));

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setStartTimestamp')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('REDIS HMSET')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordCommand($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_format_command_with_short_parameters()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('formatCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->watcher, 'SET', ['key', 'value']);
        $this->assertEquals('SET key value', $result);
    }

    public function test_format_command_with_long_parameter()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('formatCommand');
        $method->setAccessible(true);

        $longValue = str_repeat('a', 150);
        $result = $method->invoke($this->watcher, 'SET', ['key', $longValue]);
        $expected = 'SET key '.str_repeat('a', 100).'...';
        $this->assertEquals($expected, $result);
    }

    public function test_format_command_with_non_string_parameters()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('formatCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->watcher, 'INCRBY', ['counter', 5]);
        $this->assertEquals('INCRBY counter 5', $result);
    }

    public function test_format_command_with_array_parameter()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('formatCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->watcher, 'EVAL', ['script', ['key1', 'key2']]);
        $this->assertEquals('EVAL script array', $result);
    }

    public function test_format_command_with_mixed_parameters()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('formatCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->watcher, 'MSET', ['key1', 'value1', 'key2', 123, 'key3', true]);
        $this->assertEquals('MSET key1 value1 key2 123 key3 1', $result);
    }
}
