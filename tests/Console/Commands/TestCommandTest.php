<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ScopeInterface;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\StartedSpan;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class TestCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 确保 OpenTelemetry 已启用
        config(['otel.enabled' => true]);

        // Mock Tracer
        $tracer = Mockery::mock(TracerInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $span = Mockery::mock(SpanInterface::class);
        $spanContext = Mockery::mock(SpanContextInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);
        $status = Mockery::mock('status');
        $status->shouldReceive('getCode')->andReturn(StatusCode::STATUS_OK);

        // 设置 span 的期望行为
        $span->shouldReceive('setAttribute')->andReturnSelf();
        $span->shouldReceive('addEvent')->andReturnSelf();
        $span->shouldReceive('setStatus')->andReturnSelf();
        $span->shouldReceive('getContext')->andReturn($spanContext);
        $span->shouldReceive('getAttribute')->andReturn('test_value');
        $span->shouldReceive('end')->andReturnSelf();
        $span->shouldReceive('activate')->andReturn($scope);
        $span->shouldReceive('getStatus')->andReturn($status);

        // 设置 span context 的期望行为
        $spanContext->shouldReceive('getTraceId')->andReturn('test-trace-id');

        // 设置 span builder 的期望行为
        $spanBuilder->shouldReceive('start')->andReturn($span);

        // 设置 tracer 的期望行为
        $tracer->shouldReceive('spanBuilder')->andReturn($spanBuilder);

        // 替换 Measure facade 的 tracer
        Measure::shouldReceive('tracer')->andReturn($tracer);
        Measure::shouldReceive('activeSpan')->andReturn($span);
        Measure::shouldReceive('start')->andReturn(new StartedSpan($span, $scope));
        Measure::shouldReceive('end')->andReturnNull();
    }

    public function test_command_creates_test_span()
    {
        // 执行命令
        $result = Artisan::call('otel:test');

        // 验证命令执行成功
        $this->assertEquals(0, $result);

        // 验证输出包含预期的信息
        $output = Artisan::output();
        $this->assertStringContainsString('Creating test span...', $output);
        $this->assertStringContainsString('Test completed!', $output);
        $this->assertStringContainsString('Trace ID:', $output);
    }

    public function test_command_creates_span_with_correct_attributes()
    {
        // 执行命令
        Artisan::call('otel:test');

        // 获取当前活动的 span
        $span = Measure::activeSpan();

        // 验证 span 属性
        $this->assertEquals('test_value', $span->getAttribute('test.attribute'));
    }

    public function test_command_creates_child_span()
    {
        // 执行命令
        Artisan::call('otel:test');

        // 获取当前活动的 span
        $span = Measure::activeSpan();

        // 验证子 span 属性
        $this->assertEquals('test_value', $span->getAttribute('child.attribute'));
    }

    public function test_command_sets_correct_status()
    {
        // 执行命令
        Artisan::call('otel:test');

        // 获取当前活动的 span
        $span = Measure::activeSpan();

        // 验证状态
        $this->assertEquals(StatusCode::STATUS_OK, $span->getStatus()->getCode());
    }

    public function test_command_outputs_correct_table()
    {
        // 执行命令
        Artisan::call('otel:test');

        // 获取输出
        $output = Artisan::output();

        // 验证表格输出
        $this->assertStringContainsString('Span Name', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('Attributes', $output);
        $this->assertStringContainsString('Test Span', $output);
        $this->assertStringContainsString('Child Operation', $output);
    }

    public function test_command_handles_otel_disabled()
    {
        // 禁用 OpenTelemetry
        config(['otel.enabled' => false]);

        // 清除所有 mock
        Mockery::close();

        // 重新设置 mock，但这次 Measure facade 的所有方法都返回 null
        Measure::shouldReceive('tracer')->andReturnNull();
        Measure::shouldReceive('activeSpan')->andReturnNull();
        Measure::shouldReceive('start')->andReturnNull();
        Measure::shouldReceive('end')->andReturnNull();

        // 执行命令
        $result = Artisan::call('otel:test');

        // 验证命令仍然执行成功
        $this->assertEquals(0, $result);

        // 验证输出不包含 OpenTelemetry 相关信息
        $output = Artisan::output();
        $this->assertStringNotContainsString('Trace ID:', $output);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
