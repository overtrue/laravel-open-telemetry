<?php

namespace Overtrue\LaravelOpenTelemetry\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator;
use OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\ResponsePropagationSetter;
use Overtrue\LaravelOpenTelemetry\TracerManager;
use Symfony\Component\HttpFoundation\Response;

class StartTracing
{
    public function handle(Request $request, Closure $next, ?string $tracer = null)
    {
        $tracer = $tracer ?? config('otle.default');

        $this->registerTracer($tracer);
        $this->registerWatchers($tracer);

        $span = $this->startRequestSpan($request);

        $request->attributes->set(SpanInterface::class, $span);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $scope = Context::storage()->scope();

        if (! $scope) {
            return;
        }

        $scope->detach();
        $span = Span::fromContext($scope->context());

        $span->setStatus(StatusCode::STATUS_OK);

        if ($response->getStatusCode() >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR);
        }

        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
        $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $response->headers->get('Content-Length'));

        // Propagate server-timing header to response, if ServerTimingPropagator is present
        if (class_exists('OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator')) {
            $prop = new ServerTimingPropagator();
            $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
        }

        // Propagate traceresponse header to response, if TraceResponsePropagator is present
        if (class_exists('OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator')) {
            $prop = new TraceResponsePropagator();
            $prop->inject($response, ResponsePropagationSetter::instance(), $scope->context());
        }

        $span->end();
    }

    protected static function httpHostName(Request $request): string
    {
        if (method_exists($request, 'host')) {
            return $request->host();
        }
        if (method_exists($request, 'getHost')) {
            return $request->getHost();
        }

        return '';
    }

    protected function registerTracer(string $name): void
    {
        /** @var \OpenTelemetry\SDK\Trace\TracerProviderInterface $tracer */
        $tracer = app(TracerManager::class)->driver($name);

        Sdk::builder()
            ->setTracerProvider($tracer)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }

    protected function startRequestSpan(Request $request): SpanInterface
    {
        return Measure::start(sprintf('%s:%s', $request?->method() ?? 'unknown', $request->url()), [
            TraceAttributes::URL_FULL => $request->fullUrl(),
            TraceAttributes::HTTP_REQUEST_METHOD => $request->method(),
            TraceAttributes::HTTP_REQUEST_BODY_SIZE => $request->header('Content-Length'),
            TraceAttributes::URL_SCHEME => $request->getScheme(),
            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::NETWORK_PEER_ADDRESS => $request->ip(),
            TraceAttributes::URL_PATH => $request->path(),
            TraceAttributes::HTTP_ROUTE => $request->getUri(),
            TraceAttributes::SERVER_ADDRESS => self::httpHostName($request),
            TraceAttributes::SERVER_PORT => $request->getPort(),
            TraceAttributes::CLIENT_PORT => $request->server('REMOTE_PORT'),
            TraceAttributes::USER_AGENT_ORIGINAL => $request->userAgent(),
        ]);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function registerWatchers(string $name): void
    {
        $watchers = config("otle.{$name}.watchers", config('otle.global.watchers', []));

        foreach ($watchers as $watcher) {
            app($watcher)->register(app());
        }
    }
}
