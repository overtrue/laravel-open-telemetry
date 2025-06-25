<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class HttpClientWatcher extends Watcher
{
    /**
     * @var array<string, SpanInterface>
     */
    protected array $spans = [];

    public function register(Application $app): void
    {
        $app['events']->listen(RequestSending::class, [$this, 'recordRequest']);
        $app['events']->listen(ConnectionFailed::class, [$this, 'recordConnectionFailed']);
        $app['events']->listen(ResponseReceived::class, [$this, 'recordResponse']);
    }

    public function recordRequest(RequestSending $request): void
    {
        $parsedUrl = collect(parse_url($request->request->url()) ?: []);
        $processedUrl = $parsedUrl->get('scheme', 'http').'://'.$parsedUrl->get('host').$parsedUrl->get('path', '');

        if ($parsedUrl->has('query')) {
            $processedUrl .= '?'.$parsedUrl->get('query');
        }

        $tracer = Measure::tracer();
        $span = $tracer->spanBuilder(SpanNameHelper::httpClient($request->request->method(), $processedUrl))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes([
                TraceAttributes::HTTP_REQUEST_METHOD => $request->request->method(),
                TraceAttributes::URL_FULL => $processedUrl,
                TraceAttributes::URL_PATH => $parsedUrl['path'] ?? '',
                TraceAttributes::URL_SCHEME => $parsedUrl['scheme'] ?? '',
                TraceAttributes::SERVER_ADDRESS => $parsedUrl['host'] ?? '',
                TraceAttributes::SERVER_PORT => $parsedUrl['port'] ?? '',
            ])
            ->startSpan();

        // Add request headers based on configuration
        $this->setRequestHeaders($span, $request->request);

        $this->spans[$this->createRequestComparisonHash($request->request)] = $span;
    }

    public function recordConnectionFailed(ConnectionFailed $request): void
    {
        $requestHash = $this->createRequestComparisonHash($request->request);

        $span = $this->spans[$requestHash] ?? null;
        if ($span === null) {
            return;
        }

        $span->setStatus(StatusCode::STATUS_ERROR, 'Connection failed');
        $span->end();

        unset($this->spans[$requestHash]);
    }

    public function recordResponse(ResponseReceived $request): void
    {
        $requestHash = $this->createRequestComparisonHash($request->request);

        $span = $this->spans[$requestHash] ?? null;
        if ($span === null) {
            return;
        }

        $span->setAttributes([
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $request->response->status(),
            TraceAttributes::HTTP_RESPONSE_BODY_SIZE => $request->response->header('Content-Length'),
        ]);

        // Add response headers based on configuration
        $this->setResponseHeaders($span, $request->response);

        $this->maybeRecordError($span, $request->response);
        $span->end();

        unset($this->spans[$requestHash]);
    }

    /**
     * Set request headers as span attributes based on allowed/sensitive configuration
     */
    private function setRequestHeaders(SpanInterface $span, Request $request): void
    {
        $allowedHeaders = config('otel.allowed_headers', []);
        $sensitiveHeaders = config('otel.sensitive_headers', []);

        if (empty($allowedHeaders)) {
            return;
        }

        $headers = $request->headers();

        foreach ($headers as $name => $values) {
            $headerName = strtolower($name);
            $headerValue = is_array($values) ? implode(', ', $values) : (string) $values;

            // Check if header is allowed
            if ($this->isHeaderAllowed($headerName, $allowedHeaders)) {
                $attributeName = 'http.request.header.' . str_replace('-', '_', $headerName);

                // Check if header is sensitive
                if ($this->isHeaderSensitive($headerName, $sensitiveHeaders)) {
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
    private function setResponseHeaders(SpanInterface $span, Response $response): void
    {
        $allowedHeaders = config('otel.allowed_headers', []);
        $sensitiveHeaders = config('otel.sensitive_headers', []);

        if (empty($allowedHeaders)) {
            return;
        }

        $headers = $response->headers();

        foreach ($headers as $name => $values) {
            $headerName = strtolower($name);
            $headerValue = is_array($values) ? implode(', ', $values) : (string) $values;

            // Check if header is allowed
            if ($this->isHeaderAllowed($headerName, $allowedHeaders)) {
                $attributeName = 'http.response.header.' . str_replace('-', '_', $headerName);

                // Check if header is sensitive
                if ($this->isHeaderSensitive($headerName, $sensitiveHeaders)) {
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
    private function isHeaderAllowed(string $headerName, array $allowedHeaders): bool
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
    private function isHeaderSensitive(string $headerName, array $sensitiveHeaders): bool
    {
        foreach ($sensitiveHeaders as $pattern) {
            if (fnmatch(strtolower($pattern), $headerName)) {
                return true;
            }
        }

        return false;
    }

    private function createRequestComparisonHash(Request $request): string
    {
        return sha1($request->method().'|'.$request->url().'|'.$request->body());
    }

    private function maybeRecordError(SpanInterface $span, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        // HTTP status code 3xx is not really error
        if ($response->redirect()) {
            return;
        }

        $span->setStatus(
            StatusCode::STATUS_ERROR,
            HttpResponse::$statusTexts[$response->status()] ?? (string) $response->status()
        );
    }
}
