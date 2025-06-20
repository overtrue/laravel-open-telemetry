<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Support;

use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Support\SpanBuilder;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class SpanBuilderTest extends TestCase
{
    public function test_creates_span_builder_with_underlying_builder()
    {
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);

        $spanBuilder = new SpanBuilder($mockBuilder);

        $this->assertInstanceOf(SpanBuilder::class, $spanBuilder);
    }

    public function test_set_span_kind()
    {
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('setSpanKind')
            ->with(SpanKind::KIND_CLIENT)
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->setSpanKind(SpanKind::KIND_CLIENT);

        $this->assertSame($spanBuilder, $result);
    }

    public function test_set_attribute()
    {
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('setAttribute')
            ->with('test.key', 'test.value')
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->setAttribute('test.key', 'test.value');

        $this->assertSame($spanBuilder, $result);
    }

    public function test_set_attributes()
    {
        $attributes = ['key1' => 'value1', 'key2' => 'value2'];

        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('setAttributes')
            ->with($attributes)
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->setAttributes($attributes);

        $this->assertSame($spanBuilder, $result);
    }

    public function test_set_parent()
    {
        $context = Context::getCurrent();

        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('setParent')
            ->with($context)
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->setParent($context);

        $this->assertSame($spanBuilder, $result);
    }

    public function test_set_parent_with_null()
    {
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('setParent')
            ->with(null)
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->setParent(null);

        $this->assertSame($spanBuilder, $result);
    }

    public function test_add_link()
    {
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockSpanContext = Mockery::mock('OpenTelemetry\API\Trace\SpanContextInterface');
        $attributes = ['link.key' => 'link.value'];

        $mockSpan->shouldReceive('getContext')
            ->once()
            ->andReturn($mockSpanContext);

        $mockBuilder->shouldReceive('addLink')
            ->with($mockSpanContext, $attributes)
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->addLink($mockSpan, $attributes);

        $this->assertSame($spanBuilder, $result);
    }

    public function test_set_start_timestamp()
    {
        $timestamp = 1234567890;

        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('setStartTimestamp')
            ->with($timestamp)
            ->once()
            ->andReturnSelf();

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->setStartTimestamp($timestamp);

        $this->assertSame($spanBuilder, $result);
    }

    public function test_start_creates_started_span()
    {
        $mockSpan = Mockery::mock(SpanInterface::class);
        $mockScope = Mockery::mock(ScopeInterface::class);

        $mockSpan->shouldReceive('activate')
            ->once()
            ->andReturn($mockScope);

        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);
        $mockBuilder->shouldReceive('startSpan')
            ->once()
            ->andReturn($mockSpan);

        $spanBuilder = new SpanBuilder($mockBuilder);
        $result = $spanBuilder->start();

        $this->assertInstanceOf(StartedSpan::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
