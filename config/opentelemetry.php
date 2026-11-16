<?php

return [
    'enabled' => filter_var(env('OTEL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'fintwin-api')),
    'service_version' => env('OTEL_SERVICE_VERSION', '0.1.0'),
    'exporter' => env('OTEL_TRACES_EXPORTER', 'otlp'),
    'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
    'export_timeout_ms' => (int) env('OTEL_EXPORTER_OTLP_TIMEOUT', 2000),
];
