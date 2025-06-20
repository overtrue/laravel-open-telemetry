<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;

// Check if disabled
if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled('laravel') === true) {
    return;
}

// Check if OpenTelemetry extension is loaded
if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Laravel auto-instrumentation', E_USER_WARNING);

    return;
}

// Check if Laravel exists
if (! class_exists(\Illuminate\Foundation\Application::class)) {
    return;
}

// Register Laravel enhancements
require_once __DIR__.'/src/LaravelInstrumentation.php';

\Overtrue\LaravelOpenTelemetry\LaravelInstrumentation::register();
