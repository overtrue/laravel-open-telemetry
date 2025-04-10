<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Traits\InteractWithHttpHeaders;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleTraceMiddleware
{
    use InteractWithHttpHeaders;

    public static function make(): Closure
    {
        return static function (callable $handler): callable {
            return static function (RequestInterface $request, array $options) use ($handler) {
                $span = \Overtrue\LaravelOpenTelemetry\Facades\Measure::span(sprintf('HTTP %s', $request->getMethod()))
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::URL_FULL, sprintf('%s://%s%s', $request->getUri()->getScheme(), $request->getUri()->getHost(), $request->getUri()->getPath()))
                    ->setAttribute(TraceAttributes::URL_PATH, $request->getUri()->getPath())
                    ->setAttribute(TraceAttributes::URL_QUERY, $request->getUri()->getQuery())
                    ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $request->getProtocolVersion())
                    ->setAttribute(TraceAttributes::NETWORK_PEER_ADDRESS, $request->getUri()->getHost())
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->getBody()->getSize())
                    ->setAttribute(TraceAttributes::URL_SCHEME, $request->getUri()->getScheme())
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, $request->getUri()->getPort())
                    ->setAttribute(TraceAttributes::CLIENT_PORT, $request->getHeader('REMOTE_PORT'))
                    ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeader('User-Agent'))
                    ->start();

                static::recordHeaders($span, $request);

                $context = $span->storeInContext(Context::getCurrent());

                foreach (\Overtrue\LaravelOpenTelemetry\Facades\Measure::propagationHeaders($context) as $key => $value) {
                    $request = $request->withHeader($key, $value);
                }

                $promise = $handler($request, $options);
                assert($promise instanceof PromiseInterface);

                return $promise->then(function (ResponseInterface $response) use ($span) {
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());

                    if (($contentLength = $response->getHeader('Content-Length')[0] ?? null) !== null) {
                        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $contentLength);
                    }

                    static::recordHeaders($span, $response);

                    if ($response->getStatusCode() >= 400) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }

                    $span->end();

                    return $response;
                });
            };
        };
    }

    protected static function recordHeaders(SpanInterface $span, RequestInterface|ResponseInterface $http): SpanInterface
    {
        $prefix = match (true) {
            $http instanceof RequestInterface => 'http.request.header.',
            $http instanceof ResponseInterface => 'http.response.header.',
        };

        foreach ($http->getHeaders() as $key => $value) {
            $key = strtolower($key);

            if (! static::headerIsAllowed($key)) {
                continue;
            }

            $value = static::headerIsSensitive($key) ? ['*****'] : $value;

            $span->setAttribute($prefix.$key, $value);
        }

        return $span;
    }
}