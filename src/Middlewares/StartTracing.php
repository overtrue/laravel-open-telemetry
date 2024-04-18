<?php

namespace Overtrue\LaravelOpenTelemetry\Middlewares;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Contrib\Propagation\ServerTiming\ServerTimingPropagator;
use OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\ResponsePropagationSetter;
use Overtrue\LaravelOpenTelemetry\TracerManager;
use Symfony\Component\HttpFoundation\Response;

class StartTracing
{
    public function handle(Request $request, Closure $next, ?string $name = null)
    {
        $name = $name ?? config('otle.default');

        /** @var \Overtrue\LaravelOpenTelemetry\Tracer $tracer */
        $tracer = app(TracerManager::class)->driver($name);

        $tracer->start(app());

        $this->registerWatchers($name);

        $span = $this->startRequestSpan($request);

        $request->attributes->set(SpanInterface::class, $span);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $scope = Measure::activeScope();

        if (! $scope) {
            return;
        }

        $scope->detach();
        $span = Measure::activeSpan();

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

    protected function startRequestSpan(Request $request): SpanInterface
    {
        return Measure::span(sprintf('%s:%s', $request?->method() ?? 'unknown', $request->url()))
            ->setAttributes([
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
            ])->start();
    }
}
