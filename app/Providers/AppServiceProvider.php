<?php

namespace App\Providers;

use App\Integrations\Brapi\BrapiClient;
use App\Integrations\DeepSeek\DeepSeekClient;
use App\Integrations\Google\GoogleAuthClient;
use App\Integrations\Pluggy\PluggyClient;
use App\Integrations\Support\GuzzleClientFactory;
use App\Observability\OpenTelemetryService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, SpanInterface>
     */
    private array $activeJobSpans = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GuzzleClientFactory::class);

        $this->app->singleton(PluggyClient::class, function ($app): PluggyClient {
            /** @var GuzzleClientFactory $factory */
            $factory = $app->make(GuzzleClientFactory::class);
            $config = config('integrations.pluggy');

            return new PluggyClient(
                http: $factory->make(
                    baseUri: (string) $config['base_url'],
                    timeout: (int) $config['timeout'],
                    connectTimeout: (int) $config['connect_timeout'],
                ),
                cache: Cache::store(),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
                apiKeyTtl: (int) $config['api_key_ttl'],
            );
        });

        $this->app->singleton(DeepSeekClient::class, function ($app): DeepSeekClient {
            /** @var GuzzleClientFactory $factory */
            $factory = $app->make(GuzzleClientFactory::class);
            $config = config('integrations.deepseek');

            return new DeepSeekClient(
                http: $factory->make(
                    baseUri: (string) $config['base_url'],
                    timeout: (int) $config['timeout'],
                    connectTimeout: (int) $config['connect_timeout'],
                ),
                apiKey: (string) ($config['api_key'] ?? ''),
                defaultModel: (string) ($config['model'] ?? 'deepseek-chat'),
            );
        });

        $this->app->singleton(BrapiClient::class, function ($app): BrapiClient {
            /** @var GuzzleClientFactory $factory */
            $factory = $app->make(GuzzleClientFactory::class);
            $config = config('integrations.brapi');

            return new BrapiClient(
                http: $factory->make(
                    baseUri: (string) $config['base_url'],
                    timeout: (int) $config['timeout'],
                    connectTimeout: (int) $config['connect_timeout'],
                ),
                apiToken: (string) ($config['api_token'] ?? ''),
            );
        });

        $this->app->singleton(GoogleAuthClient::class, function ($app): GoogleAuthClient {
            /** @var GuzzleClientFactory $factory */
            $factory = $app->make(GuzzleClientFactory::class);
            $config = config('integrations.google');

            return new GoogleAuthClient(
                http: $factory->make(
                    baseUri: (string) $config['tokeninfo_base_url'],
                    timeout: (int) $config['timeout'],
                    connectTimeout: (int) $config['connect_timeout'],
                ),
                allowedClientIds: (array) ($config['client_ids'] ?? []),
            );
        });

        $this->app->singleton(OpenTelemetryService::class, function (): OpenTelemetryService {
            $config = config('opentelemetry');

            return new OpenTelemetryService(
                enabled: (bool) ($config['enabled'] ?? false),
                serviceName: (string) ($config['service_name'] ?? 'fintwin-api'),
                serviceVersion: (string) ($config['service_version'] ?? '0.1.0'),
                endpoint: (string) ($config['endpoint'] ?? 'http://otel-collector:4318'),
                exportTimeoutMs: (int) ($config['export_timeout_ms'] ?? 2000),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $telemetry = $this->app->make(OpenTelemetryService::class);

        if (! $telemetry->isEnabled()) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($telemetry): void {
            $telemetry->recordDatabaseQuery(
                connectionName: $query->connectionName,
                sql: $query->sql,
                timeMs: $query->time,
            );
        });

        Queue::before(function (JobProcessing $event) use ($telemetry): void {
            $jobId = (string) ($event->job->getJobId() ?: spl_object_id($event->job));
            $span = $telemetry->startSpan(
                name: 'queue.job '.$event->job->resolveName(),
                attributes: [
                    'messaging.system' => 'redis',
                    'messaging.destination.name' => $event->job->getQueue(),
                    'messaging.message.id' => $jobId,
                ],
                kind: SpanKind::KIND_CONSUMER,
            );

            if ($span !== null) {
                $this->activeJobSpans[$jobId] = $span;
            }
        });

        Queue::after(function (JobProcessed $event) use ($telemetry): void {
            $jobId = (string) ($event->job->getJobId() ?: spl_object_id($event->job));
            $span = $this->activeJobSpans[$jobId] ?? null;
            unset($this->activeJobSpans[$jobId]);

            $telemetry->finishSpan($span);
        });

        Queue::failing(function (JobFailed $event) use ($telemetry): void {
            $jobId = (string) ($event->job->getJobId() ?: spl_object_id($event->job));
            $span = $this->activeJobSpans[$jobId] ?? null;
            unset($this->activeJobSpans[$jobId]);

            $telemetry->finishSpan($span, $event->exception);
        });
    }
}
