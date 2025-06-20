<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
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
}
