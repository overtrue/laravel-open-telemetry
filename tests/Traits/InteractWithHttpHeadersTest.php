<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Traits;

use Mockery;
use OpenTelemetry\API\Trace\SpanInterface;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Traits\InteractWithHttpHeaders;

class InteractWithHttpHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set default config for headers
        config([
            'otel.allowed_headers' => ['content-type', 'user-agent', 'x-*'],
            'otel.sensitive_headers' => ['authorization', 'x-api-key', '*-token']
        ]);
    }

    public function test_normalize_headers()
    {
        $traitInstance = $this->getTraitInstance();

        $headers = [
            'Content-Type' => 'application/json',
            'USER-AGENT' => ['Mozilla/5.0', 'Chrome/91.0'],
            'X-Custom' => 'value'
        ];

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($traitInstance);
        $method = $reflection->getMethod('normalizeHeaders');
        $method->setAccessible(true);

        $result = $method->invoke($traitInstance, $headers);

        $expected = [
            'content-type' => 'application/json',
            'user-agent' => 'Mozilla/5.0, Chrome/91.0',
            'x-custom' => 'value'
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_header_is_allowed()
    {
        $traitInstance = $this->getTraitInstance();
        $reflection = new \ReflectionClass($traitInstance);
        $method = $reflection->getMethod('headerIsAllowed');
        $method->setAccessible(true);

        $allowedHeaders = ['content-type', 'user-agent', 'x-*'];

        $this->assertTrue($method->invoke($traitInstance, 'content-type', $allowedHeaders));
        $this->assertTrue($method->invoke($traitInstance, 'x-custom', $allowedHeaders));
        $this->assertFalse($method->invoke($traitInstance, 'authorization', $allowedHeaders));
    }

    public function test_header_is_sensitive()
    {
        $traitInstance = $this->getTraitInstance();
        $reflection = new \ReflectionClass($traitInstance);
        $method = $reflection->getMethod('headerIsSensitive');
        $method->setAccessible(true);

        $sensitiveHeaders = ['authorization', 'x-api-key', '*-token'];

        $this->assertTrue($method->invoke($traitInstance, 'authorization', $sensitiveHeaders));
        $this->assertTrue($method->invoke($traitInstance, 'csrf-token', $sensitiveHeaders));
        $this->assertFalse($method->invoke($traitInstance, 'content-type', $sensitiveHeaders));
    }

    public function test_record_headers()
    {
        $traitInstance = $this->getTraitInstance();
        $mockSpan = Mockery::mock(SpanInterface::class);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer secret-token',
            'X-Custom' => 'custom-value',
            'Blocked-Header' => 'blocked-value'
        ];

        // Expect setAttribute calls for allowed headers
        $mockSpan->shouldReceive('setAttribute')
            ->with('http.request.header.content-type', 'application/json')
            ->once();

        $mockSpan->shouldReceive('setAttribute')
            ->with('http.request.header.x-custom', 'custom-value')
            ->once();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($traitInstance);
        $method = $reflection->getMethod('recordHeaders');
        $method->setAccessible(true);

        $method->invoke($traitInstance, $mockSpan, $headers);

        // Verify the expectation was met
        $this->assertTrue(true); // Mockery will fail if expectation not met
    }

    protected function getTraitInstance()
    {
        return new class() {
            use InteractWithHttpHeaders;
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
