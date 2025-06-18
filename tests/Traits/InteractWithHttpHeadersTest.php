<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Traits;

use Illuminate\Http\Request;
use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Traits\InteractWithHttpHeaders;
use ReflectionClass;

class InteractWithHttpHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 确保 OpenTelemetry 已启用
        config(['otel.enabled' => true]);

        // 重置静态属性
        $this->resetStaticProperties();
    }

    public function test_normalize_headers()
    {
        $trait = $this->getObjectForTrait(InteractWithHttpHeaders::class);

        // 使用反射调用受保护的方法
        $reflection = new ReflectionClass($trait);
        $method = $reflection->getMethod('normalizeHeaders');
        $method->setAccessible(true);

        // 测试空数组
        $result = $method->invoke($trait, []);
        $this->assertEquals([], $result);

        // 测试混合大小写数组
        $headers = ['Content-Type', 'AUTHORIZATION', 'x-Custom-Header'];
        $result = $method->invoke($trait, $headers);
        $expected = ['content-type', 'authorization', 'x-custom-header'];
        $this->assertEquals($expected, $result);
    }

    public function test_header_is_allowed()
    {
        // 设置允许的头部
        $this->setStaticProperty('allowedHeaders', ['content-type', 'x-custom']);

        // 测试允许的头部
        $this->assertTrue($this->getObjectForTrait(InteractWithHttpHeaders::class)::headerIsAllowed('content-type'));
        $this->assertTrue($this->getObjectForTrait(InteractWithHttpHeaders::class)::headerIsAllowed('x-custom'));

        // 测试不允许的头部
        $this->assertFalse($this->getObjectForTrait(InteractWithHttpHeaders::class)::headerIsAllowed('authorization'));
    }

    public function test_header_is_sensitive()
    {
        // 设置敏感头部
        $this->setStaticProperty('sensitiveHeaders', ['authorization', 'x-api-key']);

        // 测试敏感头部
        $this->assertTrue($this->getObjectForTrait(InteractWithHttpHeaders::class)::headerIsSensitive('authorization'));
        $this->assertTrue($this->getObjectForTrait(InteractWithHttpHeaders::class)::headerIsSensitive('x-api-key'));

        // 测试非敏感头部
        $this->assertFalse($this->getObjectForTrait(InteractWithHttpHeaders::class)::headerIsSensitive('content-type'));
    }

    public function test_record_headers()
    {
        // 设置允许的头部和敏感头部
        $this->setStaticProperty('allowedHeaders', ['content-type', 'authorization']);
        $this->setStaticProperty('sensitiveHeaders', ['authorization']);

        $trait = $this->getObjectForTrait(InteractWithHttpHeaders::class);

        // 创建请求
        $request = Request::create('https://example.com', 'GET');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Authorization', 'Bearer token');

        // Mock span
        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttribute')
            ->with('http.request.header.content-type', 'application/json')
            ->once();
        $span->shouldReceive('setAttribute')
            ->with('http.request.header.authorization', '*****')
            ->once();

        // 使用反射调用受保护的方法
        $reflection = new ReflectionClass($trait);
        $method = $reflection->getMethod('recordHeaders');
        $method->setAccessible(true);

        $result = $method->invoke($trait, $span, $request);
        $this->assertSame($span, $result);
    }

    protected function tearDown(): void
    {
        $this->resetStaticProperties();
        Mockery::close();
        parent::tearDown();
    }

    private function resetStaticProperties(): void
    {
        $this->setStaticProperty('allowedHeaders', []);
        $this->setStaticProperty('sensitiveHeaders', []);
    }

    private function setStaticProperty(string $property, array $value): void
    {
        $traitClass = $this->getObjectForTrait(InteractWithHttpHeaders::class);
        $reflection = new ReflectionClass($traitClass);
        $staticProperty = $reflection->getProperty($property);
        $staticProperty->setAccessible(true);
        $staticProperty->setValue(null, $value);
    }
}
