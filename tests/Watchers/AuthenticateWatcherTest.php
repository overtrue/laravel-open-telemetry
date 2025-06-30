<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Mockery;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\AuthenticateWatcher;

class AuthenticateWatcherTest extends TestCase
{
    private AuthenticateWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new AuthenticateWatcher;
    }

    public function test_registers_auth_event_listeners()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(Attempting::class, [$this->watcher, 'recordAttempting'])->once();
        $events->shouldReceive('listen')->with(Authenticated::class, [$this->watcher, 'recordAuthenticated'])->once();
        $events->shouldReceive('listen')->with(Login::class, [$this->watcher, 'recordLogin'])->once();
        $events->shouldReceive('listen')->with(Failed::class, [$this->watcher, 'recordFailed'])->once();
        $events->shouldReceive('listen')->with(Logout::class, [$this->watcher, 'recordLogout'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_attempting_event()
    {
        $event = new Attempting('web', ['email' => 'test@example.com'], false);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'auth.guard' => 'web',
            'auth.credentials.count' => 1,
            'auth.remember' => false,
        ])->andReturnSelf();
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('AUTH ATTEMPTING')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordAttempting($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_authenticated_event()
    {
        $user = Mockery::mock();
        $user->shouldReceive('getAuthIdentifier')->andReturn(123);

        $event = new Authenticated('web', $user);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'auth.guard' => 'web',
            TraceAttributes::ENDUSER_ID => 123,
            'auth.user.type' => get_class($user),
        ])->andReturnSelf();
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('AUTH AUTHENTICATED')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordAuthenticated($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_login_event()
    {
        $user = Mockery::mock();
        $user->shouldReceive('getAuthIdentifier')->andReturn(456);

        $event = new Login('web', $user, true);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'auth.guard' => 'web',
            TraceAttributes::ENDUSER_ID => 456,
            'auth.user.type' => get_class($user),
            'auth.remember' => true,
        ])->andReturnSelf();
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('AUTH LOGIN')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordLogin($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_failed_event()
    {
        $event = new Failed('web', null, ['email' => 'test@example.com']);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'auth.guard' => 'web',
            'auth.credentials.count' => 1,
            TraceAttributes::ENDUSER_ID => null,
        ])->andReturnSelf();
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('AUTH FAILED')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordFailed($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }

    public function test_records_logout_event()
    {
        $user = Mockery::mock();
        $user->shouldReceive('getAuthIdentifier')->andReturn(111);

        $event = new Logout('web', $user);

        $span = Mockery::mock(SpanInterface::class);
        $span->shouldReceive('setAttributes')->with([
            'auth.guard' => 'web',
            TraceAttributes::ENDUSER_ID => 111,
            'auth.user.type' => get_class($user),
        ])->andReturnSelf();
        $span->shouldReceive('end');

        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $spanBuilder->shouldReceive('setSpanKind')->andReturnSelf();
        $spanBuilder->shouldReceive('setParent')->andReturnSelf();
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);

        $tracer = Mockery::mock(TracerInterface::class);
        $tracer->shouldReceive('spanBuilder')->with('AUTH LOGOUT')->andReturn($spanBuilder);

        Measure::shouldReceive('tracer')->andReturn($tracer);

        $this->watcher->recordLogout($event);

        // Assert that the test executed successfully
        $this->assertTrue(true);
    }
}
