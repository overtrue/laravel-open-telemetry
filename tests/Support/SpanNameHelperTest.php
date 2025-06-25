<?php

namespace Tests\Support;

use Illuminate\Http\Request;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Illuminate\Http\Response;

class SpanNameHelperTest extends TestCase
{
    public function testHttpSpanName()
    {
        $request = Request::create('/api/users', 'GET');
        $this->assertEquals('HTTP GET /api/users', SpanNameHelper::http($request));

        $requestWithRoute = Request::create('/users/123', 'GET');
        $route = new \Illuminate\Routing\Route('GET', 'users/{id}', fn () => new Response());
        $route->bind($requestWithRoute);
        $requestWithRoute->setRouteResolver(fn () => $route);
        $this->assertEquals('HTTP GET users/{id}', SpanNameHelper::http($requestWithRoute));
    }

    public function testHttpClientSpanName()
    {
        $spanName = SpanNameHelper::httpClient('POST', 'https://api.example.com/users');

        $this->assertEquals('HTTP POST api.example.com/users', $spanName);
    }

    public function testDatabaseSpanNameWithTable()
    {
        $spanName = SpanNameHelper::database('SELECT', 'users');

        $this->assertEquals('DB SELECT users', $spanName);
    }

    public function testDatabaseSpanNameWithoutTable()
    {
        $spanName = SpanNameHelper::database('INSERT');

        $this->assertEquals('DB INSERT', $spanName);
    }

    public function testRedisSpanName()
    {
        $spanName = SpanNameHelper::redis('get');

        $this->assertEquals('REDIS GET', $spanName);
    }

    public function testQueueSpanNameWithJobClass()
    {
        $spanName = SpanNameHelper::queue('processing', 'App\\Jobs\\SendEmailJob');

        $this->assertEquals('QUEUE PROCESSING SendEmailJob', $spanName);
    }

    public function testQueueSpanNameWithoutJobClass()
    {
        $spanName = SpanNameHelper::queue('queued');

        $this->assertEquals('QUEUE QUEUED', $spanName);
    }

    public function testAuthSpanName()
    {
        $spanName = SpanNameHelper::auth('login');

        $this->assertEquals('AUTH LOGIN', $spanName);
    }

    public function testCacheSpanNameWithKey()
    {
        $spanName = SpanNameHelper::cache('get', 'user:123');

        $this->assertEquals('CACHE GET user:123', $spanName);
    }

    public function testCacheSpanNameWithLongKey()
    {
        $longKey = str_repeat('very_long_cache_key_', 10); // 190 characters
        $spanName = SpanNameHelper::cache('set', $longKey);

        $this->assertEquals('CACHE SET ' . substr($longKey, 0, 47) . '...', $spanName);
    }

    public function testCacheSpanNameWithoutKey()
    {
        $spanName = SpanNameHelper::cache('flush');

        $this->assertEquals('CACHE FLUSH', $spanName);
    }

    public function testEventSpanName()
    {
        $spanName = SpanNameHelper::event('Illuminate\\Auth\\Events\\Login');

        $this->assertEquals('EVENT Auth\\Events\\Login', $spanName);
    }

    public function testEventSpanNameWithAppEvents()
    {
        $spanName = SpanNameHelper::event('App\\Events\\OrderCreated');

        $this->assertEquals('EVENT OrderCreated', $spanName);
    }

    public function testExceptionSpanName()
    {
        $spanName = SpanNameHelper::exception('Illuminate\\Database\\QueryException');

        $this->assertEquals('EXCEPTION QueryException', $spanName);
    }

    public function testCommandSpanName()
    {
        $spanName = SpanNameHelper::command('make:controller');

        $this->assertEquals('COMMAND make:controller', $spanName);
    }
}
