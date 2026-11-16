<?php

namespace App\Observability;

use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Throwable;

class OpenTelemetryService
{
    private ?TracerProvider $provider = null;

    public function __construct(
        private readonly bool $enabled,
        private readonly string $serviceName,
        private readonly string $serviceVersion,
        private readonly string $endpoint,
        private readonly int $exportTimeoutMs = 2000,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function tracer(): TracerInterface
    {
        if (! $this->enabled) {
            return NoopTracer::getInstance();
        }

        if ($this->provider === null) {
            $tracesEndpoint = rtrim($this->endpoint, '/').'/v1/traces';
            $timeoutSeconds = max(0.5, $this->exportTimeoutMs / 1000);

            $transport = (new OtlpHttpTransportFactory)->create(
                endpoint: $tracesEndpoint,
                contentType: ContentTypes::PROTOBUF,
                timeout: $timeoutSeconds,
                retryDelay: 50,
                maxRetries: 1,
            );

            $exporter = new SpanExporter($transport);
            $resource = ResourceInfo::create(Attributes::create([
                'service.name' => $this->serviceName,
                'service.version' => $this->serviceVersion,
            ]));

            $this->provider = new TracerProvider(
                spanProcessors: new SimpleSpanProcessor($exporter),
                resource: $resource,
            );
        }

        return $this->provider->getTracer($this->serviceName, $this->serviceVersion);
    }

    /**
     * @param  array<string, scalar|array<scalar>|null>  $attributes
     */
    public function startSpan(string $name, array $attributes = [], int $kind = SpanKind::KIND_INTERNAL): ?SpanInterface
    {
        if (! $this->enabled) {
            return null;
        }

        try {
            $builder = $this->tracer()->spanBuilder($name)->setSpanKind($kind);

            foreach ($attributes as $key => $value) {
                if ($value !== null) {
                    $builder->setAttribute($key, $value);
                }
            }

            return $builder->startSpan();
        } catch (Throwable) {
            return null;
        }
    }

    public function finishSpan(?SpanInterface $span, ?Throwable $exception = null): void
    {
        if ($span === null) {
            return;
        }

        try {
            if ($exception !== null) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            $span->end();
        } catch (Throwable) {
            // Never allow telemetry failures to break application execution.
        }
    }

    /**
     * Record a completed database query span using its observed elapsed time in milliseconds.
     */
    public function recordDatabaseQuery(string $connectionName, string $sql, float $timeMs): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $nowNs = (int) (microtime(true) * 1_000_000_000);
            $startNs = max(0, $nowNs - (int) ($timeMs * 1_000_000));

            $span = $this->tracer()
                ->spanBuilder("db.query {$connectionName}")
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->setStartTimestamp($startNs)
                ->setAttribute('db.system', $connectionName)
                ->setAttribute('db.statement', $sql)
                ->setAttribute('db.duration_ms', $timeMs)
                ->startSpan();

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end($nowNs);
        } catch (Throwable) {
            // Never allow telemetry failures to break database execution.
        }
    }
}
