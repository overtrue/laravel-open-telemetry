<?php

namespace Tests\Support;

use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class HttpAttributesHelperTest extends TestCase
{
    private $mockSpan;

    private $mockResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSpan = $this->createMock(SpanInterface::class);
        $this->mockResponse = $this->createMock(Response::class);
    }

    public function test_set_request_attributes()
    {
        $request = Request::create('https://example.com/test?foo=bar', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'TestAgent',
            'HTTP_CONTENT_LENGTH' => '123',
            'REMOTE_ADDR' => '127.0.0.1',
            'REMOTE_PORT' => '12345',
        ]);

        // 我们不能精确预测所有属性，因为有些是条件性的
        // 所以我们使用 with() 回调来验证关键属性
        $this->mockSpan->expects($this->once())
            ->method('setAttributes')
            ->with($this->callback(function ($attributes) {
                $this->assertEquals('1.1', $attributes[TraceAttributes::NETWORK_PROTOCOL_VERSION]);
                $this->assertEquals('GET', $attributes[TraceAttributes::HTTP_REQUEST_METHOD]);
                $this->assertEquals('test', $attributes[TraceAttributes::HTTP_ROUTE]);
                $this->assertEquals('https://example.com/test?foo=bar', $attributes[TraceAttributes::URL_FULL]);
                $this->assertEquals('test', $attributes[TraceAttributes::URL_PATH]);
                $this->assertEquals('foo=bar', $attributes[TraceAttributes::URL_QUERY]);
                $this->assertEquals('https', $attributes[TraceAttributes::URL_SCHEME]);
                $this->assertEquals('example.com', $attributes[TraceAttributes::SERVER_ADDRESS]);
                $this->assertEquals('127.0.0.1', $attributes[TraceAttributes::CLIENT_ADDRESS]);
                $this->assertEquals('TestAgent', $attributes[TraceAttributes::USER_AGENT_ORIGINAL]);
                $this->assertEquals(123, $attributes[TraceAttributes::HTTP_REQUEST_BODY_SIZE]);

                return true;
            }));

        HttpAttributesHelper::setRequestAttributes($this->mockSpan, $request);
    }

    public function test_set_response_attributes()
    {
        $this->mockResponse->method('getStatusCode')->willReturn(200);
        $this->mockResponse->method('getContent')->willReturn('test content');

        // 模拟 headers
        $mockHeaders = $this->createMock(\Symfony\Component\HttpFoundation\ResponseHeaderBag::class);
        $mockHeaders->method('get')->with('Content-Length')->willReturn('12');
        $this->mockResponse->headers = $mockHeaders;

        $expectedAttributes = [
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => 200,
            TraceAttributes::HTTP_RESPONSE_BODY_SIZE => 12,
        ];

        $this->mockSpan->expects($this->once())
            ->method('setAttributes')
            ->with($expectedAttributes);

        HttpAttributesHelper::setResponseAttributes($this->mockSpan, $this->mockResponse);
    }

    public function test_set_span_status_from_response_success()
    {
        $this->mockResponse->method('getStatusCode')->willReturn(200);

        $this->mockSpan->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_OK);

        HttpAttributesHelper::setSpanStatusFromResponse($this->mockSpan, $this->mockResponse);
    }

    public function test_set_span_status_from_response_error()
    {
        $this->mockResponse->method('getStatusCode')->willReturn(500);

        $this->mockSpan->expects($this->once())
            ->method('setStatus')
            ->with(StatusCode::STATUS_ERROR, 'HTTP Error');

        HttpAttributesHelper::setSpanStatusFromResponse($this->mockSpan, $this->mockResponse);
    }

    public function test_generate_span_name()
    {
        $request = Request::create('/users', 'POST');
        $this->assertEquals('HTTP POST /users', HttpAttributesHelper::generateSpanName($request));
    }

    public function test_generate_span_name_with_route()
    {
        $request = Request::create('/users/123', 'GET');
        $request->setRouteResolver(function () {
            $route = new \Illuminate\Routing\Route('GET', 'users/{id}', function () {});
            $route->bind(Request::create('/users/123', 'GET'));

            return $route;
        });
        $this->assertEquals('HTTP GET users/{id}', HttpAttributesHelper::generateSpanName($request));
    }

    public function test_extract_carrier_from_headers()
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRACE_ID' => '123456',
            'HTTP_AUTHORIZATION' => 'Bearer token',
        ]);

        $carrier = HttpAttributesHelper::extractCarrierFromHeaders($request);

        // 只检查我们设置的特定头部，因为 Symfony 会自动添加其他头部
        $this->assertEquals('application/json', $carrier['content-type']);
        $this->assertEquals('123456', $carrier['x-trace-id']);
        $this->assertEquals('Bearer token', $carrier['authorization']);
        $this->assertArrayHasKey('host', $carrier); // 确认有 host 头部
    }
}
