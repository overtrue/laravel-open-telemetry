<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Support;

use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class StartedSpanTest extends TestCase
{
    public function test_creates_started_span_with_span_and_scope()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);

        $startedSpan = new StartedSpan($mockSpan, $mockScope);

        $this->assertInstanceOf(StartedSpan::class, $startedSpan);
        $this->assertSame($mockSpan, $startedSpan->span);
        $this->assertSame($mockScope, $startedSpan->scope);
    }

    public function test_end_detaches_scope_and_ends_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);

        $mockScope->shouldReceive('detach')
            ->once();

        $mockSpan->shouldReceive('end')
            ->once();

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $startedSpan->end();

        // Verify the expectation was met
        $this->assertTrue(true); // Mockery will fail if expectation not met
    }

    public function test_get_span_returns_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $result = $startedSpan->getSpan();

        $this->assertSame($mockSpan, $result);
    }

    public function test_set_attribute_forwards_to_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);

        $mockSpan->shouldReceive('setAttribute')
            ->with('test.key', 'test.value')
            ->once()
            ->andReturnSelf();

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $result = $startedSpan->setAttribute('test.key', 'test.value');

        $this->assertSame($startedSpan, $result);
    }

    public function test_set_attributes_forwards_to_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $attributes = ['key1' => 'value1', 'key2' => 'value2'];

        $mockSpan->shouldReceive('setAttributes')
            ->with($attributes)
            ->once()
            ->andReturnSelf();

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $result = $startedSpan->setAttributes($attributes);

        $this->assertSame($startedSpan, $result);
    }

    public function test_add_event_forwards_to_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $attributes = ['event.key' => 'event.value'];

        $mockSpan->shouldReceive('addEvent')
            ->with('test.event', $attributes, null)
            ->once()
            ->andReturnSelf();

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $result = $startedSpan->addEvent('test.event', $attributes);

        $this->assertSame($startedSpan, $result);
    }

    public function test_record_exception_forwards_to_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $exception = new \Exception('Test exception');
        $attributes = ['exception.key' => 'exception.value'];

        $mockSpan->shouldReceive('recordException')
            ->with($exception, $attributes)
            ->once()
            ->andReturnSelf();

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $result = $startedSpan->recordException($exception, $attributes);

        $this->assertSame($startedSpan, $result);
    }

    public function test_get_scope_returns_scope()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);

        $startedSpan = new StartedSpan($mockSpan, $mockScope);
        $result = $startedSpan->getScope();

        $this->assertSame($mockScope, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
