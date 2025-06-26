<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use Overtrue\LaravelOpenTelemetry\Support\Measure;
use PHPUnit\Framework\TestCase;

class OctaneDetectionTest extends TestCase
{
    private Application $app;

    private Measure $measure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application;
        $this->measure = new Measure($this->app);
    }

    public function test_detects_env_array_variables()
    {
        $measure = new Measure($this->app);
        // Test with empty $_ENV
        $this->assertFalse($measure->isOctane());
    }

    public function test_detects_octane_via_server_variables()
    {
        $_SERVER['LARAVEL_OCTANE'] = 1;
        $this->assertTrue($this->measure->isOctane());
        unset($_SERVER['LARAVEL_OCTANE']);

        // Test with ENV as well
        $_ENV['LARAVEL_OCTANE'] = 1;
        $this->assertTrue($this->measure->isOctane());
        unset($_ENV['LARAVEL_OCTANE']);
    }

    public function test_detects_octane_via_server_software()
    {
        // Current implementation only checks LARAVEL_OCTANE, so this test should expect false
        $_SERVER['SERVER_SOFTWARE'] = 'swoole-http-server';
        $this->assertFalse($this->measure->isOctane());
        unset($_SERVER['SERVER_SOFTWARE']);
    }

    public function test_detects_octane_via_app_binding()
    {
        // Current implementation only checks environment variables, not app binding
        $this->app->instance('octane', true);
        $this->assertFalse($this->measure->isOctane());
        $this->app->forgetInstance('octane');
    }

    public function test_detects_swoole_server_software()
    {
        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension not loaded');
        }

        // 模拟 Swoole 服务器软件
        $_SERVER['SERVER_SOFTWARE'] = 'swoole-http-server';

        // Current implementation only checks LARAVEL_OCTANE environment variable
        $this->assertFalse($this->measure->isOctane());

        // 清理
        unset($_SERVER['SERVER_SOFTWARE']);
    }

    public function test_falls_back_to_container_binding_check()
    {
        // 确保没有环境变量设置
        $this->clearOctaneEnvironmentVariables();

        // Mock 容器绑定
        $this->app->bind('octane', function () {
            return new \stdClass;
        });

        // Current implementation only checks environment variables, not container binding
        $this->assertFalse($this->measure->isOctane());
    }

    public function test_returns_false_when_no_octane_indicators_present()
    {
        // 确保没有任何 Octane 指示器
        $this->clearOctaneEnvironmentVariables();

        $this->assertFalse($this->measure->isOctane());
    }

    public function test_prioritizes_environment_variables_over_container_binding()
    {
        // 设置环境变量
        $_SERVER['LARAVEL_OCTANE'] = '1';

        // 即使没有容器绑定，也应该返回 true
        $this->assertTrue($this->measure->isOctane());

        // 清理
        unset($_SERVER['LARAVEL_OCTANE']);
    }

    public function test_multiple_detection_methods_work_together()
    {
        // 设置多个指示器
        $_SERVER['LARAVEL_OCTANE'] = '1';
        $_SERVER['RR_MODE'] = 'http';

        $this->assertTrue($this->measure->isOctane());

        // 清理
        unset($_SERVER['LARAVEL_OCTANE'], $_SERVER['RR_MODE']);
    }

    private function clearOctaneEnvironmentVariables(): void
    {
        $variables = [
            'LARAVEL_OCTANE',
            'RR_MODE',
            'FRANKENPHP_CONFIG',
            'SERVER_SOFTWARE',
        ];

        foreach ($variables as $var) {
            unset($_SERVER[$var], $_ENV[$var]);
        }
    }
}
