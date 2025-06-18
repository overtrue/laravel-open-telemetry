<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Traits;

use Mockery;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Traits\InteractWithEventTimestamp;
use ReflectionClass;

class InteractWithEventTimestampTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 确保 OpenTelemetry 已启用
        config(['otel.enabled' => true]);
    }

    public function test_get_event_start_timestamp_ns()
    {
        $trait = $this->getObjectForTrait(InteractWithEventTimestamp::class);

        // 使用反射调用受保护的方法
        $reflection = new ReflectionClass($trait);
        $method = $reflection->getMethod('getEventStartTimestampNs');
        $method->setAccessible(true);

        // 测试时间戳计算
        $timeMs = 100.0; // 100毫秒前
        $timestamp = $method->invoke($trait, $timeMs);

        // 验证时间戳格式
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function test_get_event_start_timestamp_ns_with_zero_time()
    {
        $trait = $this->getObjectForTrait(InteractWithEventTimestamp::class);

        // 使用反射调用受保护的方法
        $reflection = new ReflectionClass($trait);
        $method = $reflection->getMethod('getEventStartTimestampNs');
        $method->setAccessible(true);

        // 测试零时间
        $timeMs = 0.0;
        $timestamp = $method->invoke($trait, $timeMs);

        // 验证时间戳
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    public function test_get_event_start_timestamp_ns_with_large_time()
    {
        $trait = $this->getObjectForTrait(InteractWithEventTimestamp::class);

        // 使用反射调用受保护的方法
        $reflection = new ReflectionClass($trait);
        $method = $reflection->getMethod('getEventStartTimestampNs');
        $method->setAccessible(true);

        // 测试大时间值
        $timeMs = 1000.0; // 1秒前
        $timestamp = $method->invoke($trait, $timeMs);

        // 验证时间戳
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
