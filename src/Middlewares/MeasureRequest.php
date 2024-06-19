<?php

namespace Overtrue\LaravelOpenTelemetry\Middlewares;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Traits\InteractWithHttpHeaders;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MeasureRequest
{
    use InteractWithHttpHeaders;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function handle(Request $request, Closure $next, ?string $name = null)
    {
        static::$allowedHeaders = $this->normalizeHeaders(config('otel.allowed_headers', []));

        static::$sensitiveHeaders = array_merge(
            $this->normalizeHeaders(config('otel.sensitive_headers', [])),
            $this->defaultSensitiveHeaders
        );

        $span = Measure::activeSpan()->setAttributes($this->getRequestSpanAttributes($request));

        try {
            $response = $next($request);

            $this->recordHeaders($span, $request);

            // Add trace id to response header if configured.
            if ($traceIdHeaderName = config('otel.response_trace_header_name')) {
                $response->headers->set($traceIdHeaderName, Measure::traceId());
            }

            return $response;
        } catch (Throwable $exception) {
            $span->recordException($exception)
                ->setStatus(StatusCode::STATUS_ERROR);

            throw $exception;
        }
    }

    protected function recordHeaders(SpanInterface $span, Request|Response $http): SpanInterface
    {
        $prefix = match (true) {
            $http instanceof Request => 'http.request.header.',
            $http instanceof Response => 'http.response.header.',
        };

        foreach ($http->headers->all() as $key => $value) {
            $key = strtolower($key);

            if (! static::headerIsAllowed($key)) {
                continue;
            }

            $value = static::headerIsSensitive($key) ? ['*****'] : $value;

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $span->setAttribute($prefix.$key, $value);
        }

        return $span;
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

    public function getRequestSpanAttributes(Request $request): array
    {
        return [
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
            TraceAttributes::HTTP_FLAVOR => $request->server('SERVER_PROTOCOL'),
        ];
    }
}
