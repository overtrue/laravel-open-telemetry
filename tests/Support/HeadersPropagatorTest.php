<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Support;

use Illuminate\Http\Request;
use Overtrue\LaravelOpenTelemetry\Support\HeadersPropagator;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class HeadersPropagatorTest extends TestCase
{
    public function testKeysReturnsHeaderKeysFromRequest()
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'Content-Type' => 'application/json',
            'X-Test-Header' => 'test-value',
        ]);

        $propagator = HeadersPropagator::instance();

        $this->assertEquals(
            $request->headers->keys(),
            $propagator->keys($request)
        );
    }

    public function testGetReturnsHeaderValueFromRequest()
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'x-test-header' => 'test-value',
        ]);

        $propagator = HeadersPropagator::instance();

        $this->assertEquals(
            $request->headers->get('x-test-header'),
            $propagator->get($request, 'x-test-header')
        );
    }

    public function testGetReturnsNullForNonExistentHeader()
    {
        $request = Request::create('/test', 'GET');

        $propagator = HeadersPropagator::instance();

        $this->assertNull(
            $propagator->get($request, 'Non-Existent-Header')
        );
    }
}
