<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Support;

use Mockery;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\Span;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanBuilder;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class MeasureTest extends TestCase
{
    public function test_new_span_builder()
    {
        $measure = Mockery::mock(\Overtrue\LaravelOpenTelemetry\Support\Measure::class)->makePartial();
        $mockBuilder = Mockery::mock(SpanBuilderInterface::class);

        $measure->shouldReceive('tracer->spanBuilder')->withAnyArgs()->andReturn($mockBuilder)->once();

        Measure::swap($measure);

        $this->assertInstanceOf(
            SpanBuilder::class,
            Measure::span('test')
        );
    }

    public function test_start_span()
    {
        $measure = Mockery::mock(\Overtrue\LaravelOpenTelemetry\Support\Measure::class)->makePartial();
        $mockBuilder = Mockery::mock(SpanBuilder::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $mockSpan = Mockery::mock(SpanInterface::class);

        $measure->shouldReceive('span')->withAnyArgs()->andReturn($mockBuilder)->once();
        $mockBuilder->shouldReceive('start')->andReturn($mockSpan)->once();
        $mockSpan->shouldReceive('activate')->andReturn($mockScope)->once();

        Measure::swap($measure);

        $this->assertInstanceOf(
            StartedSpan::class,
            Measure::start('test')
        );
    }

    public function test_end_span()
    {
        $measure = Mockery::mock(\Overtrue\LaravelOpenTelemetry\Support\Measure::class)->makePartial();
        $mockBuilder = Mockery::mock(SpanBuilder::class);
        $mockScope = Mockery::mock(ScopeInterface::class);
        $mockSpan = Mockery::mock(SpanInterface::class);

        $measure->shouldReceive('span')->andReturn($mockBuilder)->once();
        $mockBuilder->shouldReceive('start')->andReturn($mockSpan)->once();
        $mockSpan->shouldReceive('activate')->andReturn($mockScope)->once();

        $mockSpan->shouldReceive('end')->andReturn($mockScope)->once();
        $mockScope->shouldReceive('detach')->once();

        Measure::swap($measure);

        Measure::start('test');
        Measure::end();
    }

    public function test_get_tracer()
    {
        $this->app->instance(TracerInterface::class, Mockery::mock(TracerInterface::class));

        $this->assertInstanceOf(
            TracerInterface::class,
            Measure::tracer()
        );
    }

    public function test_get_current_span()
    {
        $this->assertSame(Span::getCurrent(), Measure::activeSpan());
    }

    public function test_get_active_scope()
    {
        $this->assertSame(Context::storage()->scope(), Measure::activeScope());
    }

    public function test_get_trace_id()
    {
        $traceId = (new RandomIdGenerator)->generateTraceId();
        $measure = Mockery::mock(\Overtrue\LaravelOpenTelemetry\Support\Measure::class)->makePartial();
        $measure->shouldReceive('activeSpan->getContext->getTraceId')->andReturn($traceId);

        Measure::swap($measure);

        $this->assertSame($traceId, Measure::traceId());
    }

    public function test_get_propagator()
    {
        $this->assertSame(Globals::propagator(), Measure::propagator());
    }

    public function test_propagation_headers()
    {
        $context = Context::getCurrent();
        $measure = Mockery::mock(\Overtrue\LaravelOpenTelemetry\Support\Measure::class)->makePartial();
        $measure->shouldReceive('propagator->inject')->once();

        Measure::swap($measure);

        $this->assertSame(
            [],
            Measure::propagationHeaders($context)
        );
    }

    public function test_extract_context_from_propagation_headers()
    {
        $headers = [
            'traceparent' => '00-1234567890abcdef1234567890abcdef-1234567890abcdef-01',
            'tracestate' => 'key1=value1,key2=value2',
        ];
        $context = Context::getCurrent();
        $measure = Mockery::mock(\Overtrue\LaravelOpenTelemetry\Support\Measure::class)->makePartial();
        $measure->shouldReceive('propagator->extract')->with($headers)->andReturn($context)->once();

        Measure::swap($measure);

        $this->assertSame(
            $context,
            Measure::extractContextFromPropagationHeaders($headers)
        );
    }
}
