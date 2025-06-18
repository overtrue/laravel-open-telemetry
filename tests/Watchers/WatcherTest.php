<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\Watcher;

class WatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 确保 OpenTelemetry 已启用
        config(['otel.enabled' => true]);
    }

    public function test_watcher_interface()
    {
        $watcher = new class implements Watcher
        {
            public function register(Application $app)
            {
                // 测试实现
            }
        };

        $this->assertInstanceOf(Watcher::class, $watcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
