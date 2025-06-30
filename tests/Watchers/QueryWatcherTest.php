<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\QueryWatcher;

class QueryWatcherTest extends TestCase
{
    private QueryWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new QueryWatcher;

        // Mock Measure to prevent EventWatcher from interfering
        Measure::shouldReceive('addEvent')->andReturn();
    }

    public function test_registers_query_event_listener()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(QueryExecuted::class, [$this->watcher, 'recordQuery'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_select_query()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('mysql');
        $connection->shouldReceive('getDatabaseName')->andReturn('test_db');
        $connection->shouldReceive('getName')->andReturn('mysql');

        $event = new QueryExecuted(
            'SELECT * FROM users WHERE id = ?',
            [1],
            15.5,
            $connection
        );
        $event->connectionName = 'mysql';

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            TraceAttributes::DB_SYSTEM => 'mysql',
            TraceAttributes::DB_NAME => 'test_db',
            TraceAttributes::DB_STATEMENT => 'SELECT * FROM users WHERE id = ?',
            'db.connection' => 'mysql',
            'db.query.time_ms' => 15.5,
        ])->andReturnSelf();
        $span->shouldReceive('end')->with(Mockery::type('integer'));
        $span->shouldReceive('getContext->getSpanId')->andReturn('span123');
        $span->shouldReceive('getContext->getTraceId')->andReturn('trace456');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setStartTimestamp')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('DB SELECT users')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordQuery($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_get_operation_name()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('getOperationName');
        $method->setAccessible(true);

        $this->assertEquals('SELECT', $method->invoke($this->watcher, 'SELECT * FROM users'));
        $this->assertEquals('INSERT', $method->invoke($this->watcher, 'INSERT INTO posts VALUES (1, 2)'));
        $this->assertEquals('UPDATE', $method->invoke($this->watcher, 'UPDATE users SET name = ?'));
        $this->assertEquals('DELETE', $method->invoke($this->watcher, 'DELETE FROM posts WHERE id = 1'));
        $this->assertEquals('CREATE', $method->invoke($this->watcher, 'CREATE TABLE test (id INT)'));
        $this->assertEquals('ALTER', $method->invoke($this->watcher, 'ALTER TABLE users ADD COLUMN email VARCHAR(255)'));
        $this->assertEquals('DROP', $method->invoke($this->watcher, 'DROP TABLE old_table'));
        $this->assertEquals('TRUNCATE', $method->invoke($this->watcher, 'TRUNCATE TABLE logs'));
        $this->assertEquals('QUERY', $method->invoke($this->watcher, 'SHOW TABLES'));
        $this->assertEquals('QUERY', $method->invoke($this->watcher, 'EXPLAIN SELECT * FROM users'));
    }

    public function test_extract_table_name()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('extractTableName');
        $method->setAccessible(true);

        $this->assertEquals('users', $method->invoke($this->watcher, 'SELECT * FROM users'));
        $this->assertEquals('posts', $method->invoke($this->watcher, 'INSERT INTO posts VALUES (1, 2)'));
        $this->assertEquals('users', $method->invoke($this->watcher, 'UPDATE users SET name = ?'));
        $this->assertEquals('posts', $method->invoke($this->watcher, 'DELETE FROM posts WHERE id = 1'));
        $this->assertEquals('users', $method->invoke($this->watcher, 'SELECT u.* FROM users u JOIN posts p ON u.id = p.user_id'));
        $this->assertEquals('test', $method->invoke($this->watcher, 'CREATE TABLE test (id INT)'));
        $this->assertEquals('users', $method->invoke($this->watcher, 'ALTER TABLE users ADD COLUMN email VARCHAR(255)'));
        $this->assertEquals('old_table', $method->invoke($this->watcher, 'DROP TABLE old_table'));
        $this->assertEquals('logs', $method->invoke($this->watcher, 'TRUNCATE TABLE logs'));
        $this->assertNull($method->invoke($this->watcher, 'SHOW TABLES'));
    }

    public function test_extract_table_name_with_quotes()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('extractTableName');
        $method->setAccessible(true);

        $this->assertEquals('users', $method->invoke($this->watcher, 'SELECT * FROM `users`'));
        $this->assertEquals('posts', $method->invoke($this->watcher, 'INSERT INTO "posts" VALUES (1, 2)'));
        $this->assertEquals('users', $method->invoke($this->watcher, "UPDATE 'users' SET name = ?"));
    }
}
