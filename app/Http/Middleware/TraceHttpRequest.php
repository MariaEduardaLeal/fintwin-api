<?php

namespace App\Http\Middleware;

use App\Observability\OpenTelemetryService;
use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanKind;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TraceHttpRequest
{
    public function __construct(
        private readonly OpenTelemetryService $telemetry,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $span = $this->telemetry->startSpan(
            name: sprintf('%s %s', $request->method(), '/'.ltrim($request->path(), '/')),
            attributes: [
                'http.request.method' => $request->method(),
                'url.path' => '/'.ltrim($request->path(), '/'),
            ],
            kind: SpanKind::KIND_SERVER,
        );

        $scope = $span?->activate();

        try {
            /** @var Response $response */
            $response = $next($request);
            $span?->setAttribute('http.response.status_code', $response->getStatusCode());
            $this->telemetry->finishSpan($span);

            return $response;
        } catch (Throwable $exception) {
            $this->telemetry->finishSpan($span, $exception);

            throw $exception;
        } finally {
            $scope?->detach();
        }
    }
}
