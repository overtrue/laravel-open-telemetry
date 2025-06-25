<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HttpAttributesHelper
{
    /**
     * Check if request path should be ignored
     */
    public static function shouldIgnoreRequest(Request $request): bool
    {
        $ignorePaths = config('otel.ignore_paths', []);
        $path = $request->path();

        foreach ($ignorePaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set HTTP request attributes
     */
    public static function setRequestAttributes(SpanInterface $span, Request $request): void
    {
        $attributes = [
            TraceAttributes::NETWORK_PROTOCOL_VERSION => str_replace('HTTP/', '', $request->getProtocolVersion()),
            TraceAttributes::HTTP_REQUEST_METHOD => $request->method(),
            TraceAttributes::HTTP_ROUTE => self::getRouteUri($request),
            TraceAttributes::URL_FULL => $request->fullUrl(),
            TraceAttributes::URL_PATH => $request->path(),
            TraceAttributes::URL_QUERY => $request->getQueryString() ?: '',
            TraceAttributes::URL_SCHEME => $request->getScheme(),
            TraceAttributes::SERVER_ADDRESS => $request->getHost(),
            TraceAttributes::CLIENT_ADDRESS => $request->ip(),
            TraceAttributes::USER_AGENT_ORIGINAL => $request->userAgent() ?? '',
        ];

        // Add request body size
        if ($contentLength = $request->header('Content-Length')) {
            $attributes[TraceAttributes::HTTP_REQUEST_BODY_SIZE] = (int) $contentLength;
        } elseif ($request->getContent()) {
            $attributes[TraceAttributes::HTTP_REQUEST_BODY_SIZE] = strlen($request->getContent());
        }

        // Add client port (if available)
        if ($clientPort = $request->header('X-Forwarded-Port') ?: $request->server('REMOTE_PORT')) {
            $attributes[TraceAttributes::CLIENT_PORT] = (int) $clientPort;
        }

        $span->setAttributes($attributes);

        // Add request headers based on configuration
        self::setRequestHeaders($span, $request);
    }

    /**
     * Set request headers as span attributes based on allowed/sensitive configuration
     */
    public static function setRequestHeaders(SpanInterface $span, Request $request): void
    {
        $allowedHeaders = config('otel.allowed_headers', []);
        $sensitiveHeaders = config('otel.sensitive_headers', []);

        if (empty($allowedHeaders)) {
            return;
        }

        $headers = $request->headers->all();

        foreach ($headers as $name => $values) {
            $headerName = strtolower($name);
            $headerValue = is_array($values) ? implode(', ', $values) : (string) $values;

            // Check if header is allowed
            if (self::isHeaderAllowed($headerName, $allowedHeaders)) {
                $attributeName = 'http.request.header.' . str_replace('-', '_', $headerName);

                // Check if header is sensitive
                if (self::isHeaderSensitive($headerName, $sensitiveHeaders)) {
                    $span->setAttribute($attributeName, '***');
                } else {
                    $span->setAttribute($attributeName, $headerValue);
                }
            }
        }
    }

    /**
     * Set response headers as span attributes based on allowed/sensitive configuration
     */
    public static function setResponseHeaders(SpanInterface $span, Response $response): void
    {
        $allowedHeaders = config('otel.allowed_headers', []);
        $sensitiveHeaders = config('otel.sensitive_headers', []);

        if (empty($allowedHeaders)) {
            return;
        }

        $headers = $response->headers->all();

        foreach ($headers as $name => $values) {
            $headerName = strtolower($name);
            $headerValue = is_array($values) ? implode(', ', $values) : (string) $values;

            // Check if header is allowed
            if (self::isHeaderAllowed($headerName, $allowedHeaders)) {
                $attributeName = 'http.response.header.' . str_replace('-', '_', $headerName);

                // Check if header is sensitive
                if (self::isHeaderSensitive($headerName, $sensitiveHeaders)) {
                    $span->setAttribute($attributeName, '***');
                } else {
                    $span->setAttribute($attributeName, $headerValue);
                }
            }
        }
    }

    /**
     * Check if header is allowed based on patterns
     */
    private static function isHeaderAllowed(string $headerName, array $allowedHeaders): bool
    {
        foreach ($allowedHeaders as $pattern) {
            if (fnmatch(strtolower($pattern), $headerName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if header is sensitive based on patterns
     */
    private static function isHeaderSensitive(string $headerName, array $sensitiveHeaders): bool
    {
        foreach ($sensitiveHeaders as $pattern) {
            if (fnmatch(strtolower($pattern), $headerName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set HTTP response attributes
     */
    public static function setResponseAttributes(SpanInterface $span, Response $response): void
    {
        $attributes = [
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),
        ];

        // Prefer Content-Length header, otherwise calculate actual content length
        if ($contentLength = $response->headers->get('Content-Length')) {
            $attributes[TraceAttributes::HTTP_RESPONSE_BODY_SIZE] = (int) $contentLength;
        } else {
            $attributes[TraceAttributes::HTTP_RESPONSE_BODY_SIZE] = strlen($response->getContent());
        }

        $span->setAttributes($attributes);

        // Add response headers based on configuration
        self::setResponseHeaders($span, $response);
    }

    /**
     * Set span status based on response status code
     */
    public static function setSpanStatusFromResponse(SpanInterface $span, Response $response): void
    {
        if ($response->getStatusCode() >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP Error');
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }
    }

    /**
     * Set complete HTTP request and response attributes
     */
    public static function setHttpAttributes(SpanInterface $span, Request $request, ?Response $response = null): void
    {
        self::setRequestAttributes($span, $request);

        if ($response) {
            self::setResponseAttributes($span, $response);
            self::setSpanStatusFromResponse($span, $response);
        }
    }

    /**
     * Generate span name
     */
    public static function generateSpanName(Request $request): string
    {
        return SpanNameHelper::http($request);
    }

    /**
     * Get route URI
     */
    public static function getRouteUri(Request $request): string
    {
        try {
            /** @var Route $route */
            $route = $request->route();
            if ($route) {
                $uri = $route->uri();
                return $uri === '/' ? '' : $uri;
            }
        } catch (Throwable $throwable) {
            // If route doesn't exist, simply return path
        }

        return $request->path();
    }

    /**
     * Add Trace ID to response headers
     */
    public static function addTraceIdToResponse(SpanInterface $span, Response $response): void
    {
        $traceId = $span->getContext()->getTraceId();
        if ($traceId) {
            $headerName = config('otel.middleware.trace_id.header_name', 'X-Trace-Id');
            $response->headers->set($headerName, $traceId);
        }
    }

    /**
     * Extract trace context carrier from HTTP headers
     */
    public static function extractCarrierFromHeaders(Request $request): array
    {
        $carrier = [];
        foreach ($request->headers->all() as $name => $values) {
            $carrier[strtolower($name)] = $values[0] ?? '';
        }
        return $carrier;
    }
}
