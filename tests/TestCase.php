<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Middlewares\StartTracing;
use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            OpenTelemetryServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        config(['otle.default' => 'http-json']);

        $router->middleware(StartTracing::class)->get('/', function () {
            DB::select('SELECT 1+1');
            sleep(2);
            Http::get('https://httpbin.org/ip');
            Http::get('https://httpbin.org/get');
            Log::log('info', 'Hello, Laravel OpenTelemetry!');
            return 'Hello, Laravel OpenTelemetry!';
        });
    }
}
