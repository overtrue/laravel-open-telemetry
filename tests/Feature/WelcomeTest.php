<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Feature;

use Overtrue\LaravelOpenTelemetry\Tests\TestCase;

class WelcomeTest extends TestCase
{
    public function test_view_welcome()
    {
        $response = $this->getJson('/');

        $response->dd();

        $response->assertStatus(200);
    }
}
