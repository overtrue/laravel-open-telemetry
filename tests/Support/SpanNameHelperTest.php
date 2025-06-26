<?php

namespace Tests\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class SpanNameHelperTest extends TestCase
{
    public function test_http_span_name()
    {
        $request = Request::create('/api/users', 'GET');
        $this->assertEquals('HTTP GET /api/users', SpanNameHelper::http($request));

        $requestWithRoute = Request::create('/users/123', 'GET');
        $route = new \Illuminate\Routing\Route('GET', 'users/{id}', fn () => new Response);
        $route->bind($requestWithRoute);
        $requestWithRoute->setRouteResolver(fn () => $route);
        $this->assertEquals('HTTP GET users/{id}', SpanNameHelper::http($requestWithRoute));
    }

    public function test_http_client_span_name()
    {
        $spanName = SpanNameHelper::httpClient('POST', 'https://api.example.com/users');

        $this->assertEquals('HTTP POST api.example.com/users', $spanName);
    }

    public function test_database_span_name_with_table()
    {
        $spanName = SpanNameHelper::database('SELECT', 'users');

        $this->assertEquals('DB SELECT users', $spanName);
    }

    public function test_database_span_name_without_table()
    {
        $spanName = SpanNameHelper::database('INSERT');

        $this->assertEquals('DB INSERT', $spanName);
    }

    public function test_redis_span_name()
    {
        $spanName = SpanNameHelper::redis('get');

        $this->assertEquals('REDIS GET', $spanName);
    }

    public function test_queue_span_name_with_job_class()
    {
        $spanName = SpanNameHelper::queue('processing', 'App\\Jobs\\SendEmailJob');

        $this->assertEquals('QUEUE PROCESSING SendEmailJob', $spanName);
    }

    public function test_queue_span_name_without_job_class()
    {
        $spanName = SpanNameHelper::queue('queued');

        $this->assertEquals('QUEUE QUEUED', $spanName);
    }

    public function test_auth_span_name()
    {
        $spanName = SpanNameHelper::auth('login');

        $this->assertEquals('AUTH LOGIN', $spanName);
    }

    public function test_cache_span_name_with_key()
    {
        $spanName = SpanNameHelper::cache('get', 'user:123');

        $this->assertEquals('CACHE GET user:123', $spanName);
    }

    public function test_cache_span_name_with_long_key()
    {
        $longKey = str_repeat('very_long_cache_key_', 10); // 190 characters
        $spanName = SpanNameHelper::cache('set', $longKey);

        $this->assertEquals('CACHE SET '.substr($longKey, 0, 47).'...', $spanName);
    }

    public function test_cache_span_name_without_key()
    {
        $spanName = SpanNameHelper::cache('flush');

        $this->assertEquals('CACHE FLUSH', $spanName);
    }

    public function test_event_span_name()
    {
        $spanName = SpanNameHelper::event('Illuminate\\Auth\\Events\\Login');

        $this->assertEquals('EVENT Auth\\Events\\Login', $spanName);
    }

    public function test_event_span_name_with_app_events()
    {
        $spanName = SpanNameHelper::event('App\\Events\\OrderCreated');

        $this->assertEquals('EVENT OrderCreated', $spanName);
    }

    public function test_exception_span_name()
    {
        $spanName = SpanNameHelper::exception('Illuminate\\Database\\QueryException');

        $this->assertEquals('EXCEPTION QueryException', $spanName);
    }

    public function test_command_span_name()
    {
        $spanName = SpanNameHelper::command('make:controller');

        $this->assertEquals('COMMAND make:controller', $spanName);
    }
}
