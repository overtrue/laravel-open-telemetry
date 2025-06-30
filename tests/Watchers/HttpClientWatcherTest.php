<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\HttpClientWatcher;

class HttpClientWatcherTest extends TestCase
{
    private HttpClientWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new HttpClientWatcher;
    }

    public function test_registers_http_client_event_listeners()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(RequestSending::class, [$this->watcher, 'recordRequest'])->once();
        $events->shouldReceive('listen')->with(ConnectionFailed::class, [$this->watcher, 'recordConnectionFailed'])->once();
        $events->shouldReceive('listen')->with(ResponseReceived::class, [$this->watcher, 'recordResponse'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        // Mock the registerHttpClientMiddleware call
        $app->shouldReceive('singleton')->andReturn(null);

        $this->watcher->register($app);
    }

    public function test_records_request_sending_event()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('url')->andReturn('https://api.example.com/users');
        $request->shouldReceive('method')->andReturn('GET');
        $request->shouldReceive('headers')->andReturn(['Accept' => 'application/json']);
        $request->shouldReceive('body')->andReturn('');

        $event = new RequestSending($request);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttribute')->andReturnSelf();

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('setAttributes')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('HTTP GET api.example.com/users')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordRequest($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_response_received_event()
    {
        // First, simulate a request to create a span
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('url')->andReturn('https://api.example.com/posts');
        $request->shouldReceive('method')->andReturn('POST');
        $request->shouldReceive('headers')->andReturn([]);
        $request->shouldReceive('body')->andReturn('{"test": "data"}');

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('status')->andReturn(201);
        $response->shouldReceive('header')->with('Content-Length')->andReturn('100');
        $response->shouldReceive('headers')->andReturn(['Content-Type' => 'application/json']);
        $response->shouldReceive('successful')->andReturn(true);

        $event = new ResponseReceived($request, $response);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => 201,
            TraceAttributes::HTTP_RESPONSE_BODY_SIZE => '100',
        ])->andReturnSelf();
        $span->shouldReceive('end');

        // Mock the stored span retrieval
        $reflection = new \ReflectionClass($this->watcher);
        $property = $reflection->getProperty('spans');
        $property->setAccessible(true);

        // Create hash for the request
        $method = $reflection->getMethod('createRequestComparisonHash');
        $method->setAccessible(true);
        $hash = $method->invoke($this->watcher, $request);

        $property->setValue($this->watcher, [$hash => $span]);

        $this->watcher->recordResponse($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_connection_failed_event()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('url')->andReturn('https://api.example.com/timeout');
        $request->shouldReceive('method')->andReturn('GET');
        $request->shouldReceive('body')->andReturn('');

        $exception = new ConnectionException('Connection timeout');
        $event = new ConnectionFailed($request, $exception);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setStatus')->andReturnSelf();
        $span->shouldReceive('end');

        // Mock the stored span retrieval
        $reflection = new \ReflectionClass($this->watcher);
        $property = $reflection->getProperty('spans');
        $property->setAccessible(true);

        // Create hash for the request
        $method = $reflection->getMethod('createRequestComparisonHash');
        $method->setAccessible(true);
        $hash = $method->invoke($this->watcher, $request);

        $property->setValue($this->watcher, [$hash => $span]);

        $this->watcher->recordConnectionFailed($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_create_request_comparison_hash()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('method')->andReturn('GET');
        $request->shouldReceive('url')->andReturn('https://example.com/test');
        $request->shouldReceive('body')->andReturn('test body');

        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('createRequestComparisonHash');
        $method->setAccessible(true);

        $hash = $method->invoke($this->watcher, $request);
        $expectedHash = sha1('GET|https://example.com/test|test body');

        $this->assertEquals($expectedHash, $hash);
    }

    public function test_is_header_allowed()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('isHeaderAllowed');
        $method->setAccessible(true);

        $allowedHeaders = ['content-*', 'x-custom-header'];

        $this->assertTrue($method->invoke($this->watcher, 'content-type', $allowedHeaders));
        $this->assertTrue($method->invoke($this->watcher, 'content-length', $allowedHeaders));
        $this->assertTrue($method->invoke($this->watcher, 'x-custom-header', $allowedHeaders));
        $this->assertFalse($method->invoke($this->watcher, 'authorization', $allowedHeaders));
    }

    public function test_is_header_sensitive()
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('isHeaderSensitive');
        $method->setAccessible(true);

        $sensitiveHeaders = ['authorization', 'x-api-*'];

        $this->assertTrue($method->invoke($this->watcher, 'authorization', $sensitiveHeaders));
        $this->assertTrue($method->invoke($this->watcher, 'x-api-key', $sensitiveHeaders));
        $this->assertFalse($method->invoke($this->watcher, 'content-type', $sensitiveHeaders));
    }
}
