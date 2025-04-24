<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

class StartedSpan
{
    public function __construct(public SpanInterface $span, public ScopeInterface $scope) {}
}
