<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 初始化 OpenTelemetry TracerProvider 用于测试
        $this->initializeOpenTelemetry();
    }

    /**
     * 初始化 OpenTelemetry 用于测试
     */
    protected function initializeOpenTelemetry(): void
    {
        // 创建一个简单的 TracerProvider 用于测试
        $tracerProvider = \OpenTelemetry\SDK\Trace\TracerProvider::builder()->build();

        // 使用 Sdk::builder 来正确设置全局提供者
        \OpenTelemetry\SDK\Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->buildAndRegisterGlobal();
    }

    protected function getPackageProviders($app): array
    {
        return [
            OpenTelemetryServiceProvider::class,
        ];
    }

    /**
     * Get an object that uses the given trait.
     */
    protected function getObjectForTrait(string $traitName): object
    {
        return $this->getObjectForTraitName($traitName);
    }

    /**
     * Get an object that uses the given trait.
     */
    protected function getObjectForTraitName(string $traitName): object
    {
        return new class($traitName)
        {
            use \Overtrue\LaravelOpenTelemetry\Traits\InteractWithHttpHeaders;

            private string $traitName;

            public function __construct(string $traitName)
            {
                $this->traitName = $traitName;
            }
        };
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('otel.enabled', true);
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup the application environment for testing
        config()->set('database.default', 'testing');
    }
}
