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
}
