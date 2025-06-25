<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Overtrue\LaravelOpenTelemetry\Support\Measure;
use OpenTelemetry\API\Trace\SpanInterface;

class MeasureRefactorTest extends TestCase
{
    protected function tearDown(): void
    {
        // 确保每个测试后清理状态
        $measure = $this->app->make(Measure::class);
        $measure->reset();

        parent::tearDown();
    }

    public function test_can_create_measure_instance()
    {
        $measure = $this->app->make(Measure::class);

        $this->assertInstanceOf(Measure::class, $measure);
    }

    public function test_can_detect_octane_environment()
    {
        $measure = $this->app->make(Measure::class);

        // 在测试环境中，Octane 不应该被绑定
        $this->assertFalse($measure->isOctane());
    }

    public function test_can_start_and_end_root_span()
    {
        $measure = $this->app->make(Measure::class);

        $span = $measure->startRootSpan('test-root-span', ['test' => 'value']);

        $this->assertInstanceOf(SpanInterface::class, $span);
        $this->assertSame($span, $measure->getRootSpan());

        $measure->endRootSpan();

        $this->assertNull($measure->getRootSpan());
    }

    public function test_can_create_child_spans()
    {
        $measure = $this->app->make(Measure::class);

        // 首先启动根 span
        $measure->startRootSpan('test-root-span');

        // 创建子 span
        $childSpan = $measure->start('child-span');

        $this->assertNotNull($childSpan);

        // 结束子 span
        $childSpan->end();

        // 正确结束根 span
        $measure->endRootSpan();
    }

    public function test_can_get_tracer()
    {
        $measure = $this->app->make(Measure::class);

        $tracer = $measure->tracer();

        $this->assertNotNull($tracer);
    }

    public function test_can_handle_propagation_headers()
    {
        $measure = $this->app->make(Measure::class);

        $headers = $measure->propagationHeaders();

        $this->assertIsArray($headers);

        // 测试提取 context
        $context = $measure->extractContextFromPropagationHeaders($headers);

        $this->assertNotNull($context);
    }
}
