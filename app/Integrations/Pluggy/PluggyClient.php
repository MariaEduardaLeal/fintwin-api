<?php

namespace App\Integrations\Pluggy;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Support\BaseApiClient;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class PluggyClient extends BaseApiClient
{
    private const API_KEY_CACHE_KEY = 'integrations:pluggy:api_key';

    public function __construct(
        ClientInterface $http,
        private readonly CacheRepository $cache,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly int $apiKeyTtl = 6000,
    ) {
        parent::__construct($http);
    }

    protected function providerName(): string
    {
        return 'pluggy';
    }

    public function getApiKey(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            $this->cache->forget(self::API_KEY_CACHE_KEY);
        }

        return $this->cache->remember(
            self::API_KEY_CACHE_KEY,
            $this->apiKeyTtl,
            fn (): string => $this->authenticate(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createConnectToken(
        ?string $itemId = null,
        ?string $clientUserId = null,
        ?string $webhookUrl = null,
    ): array {
        $payload = [];

        if ($itemId !== null && $itemId !== '') {
            $payload['itemId'] = $itemId;
        }

        $options = array_filter([
            'clientUserId' => $clientUserId,
            'webhookUrl' => $webhookUrl,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        if ($options !== []) {
            $payload['options'] = $options;
        }

        return $this->authorizedRequest('POST', 'connect_token', [
            'json' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccounts(string $itemId): array
    {
        return $this->authorizedRequest('GET', 'accounts', [
            'query' => ['itemId' => $itemId],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTransactions(string $accountId, array $filters = []): array
    {
        return $this->authorizedRequest('GET', 'transactions', [
            'query' => array_merge(['accountId' => $accountId], $filters),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCreditCardBills(string $accountId): array
    {
        return $this->authorizedRequest('GET', 'bills', [
            'query' => ['accountId' => $accountId],
        ]);
    }

    private function authenticate(): string
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Missing PLUGGY_CLIENT_ID or PLUGGY_CLIENT_SECRET configuration.',
            );
        }

        $response = $this->request('POST', 'auth', [
            'json' => [
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ],
        ]);

        $apiKey = $response['apiKey'] ?? null;

        if (! is_string($apiKey) || $apiKey === '') {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Authentication response did not include a valid apiKey.',
            );
        }

        return $apiKey;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function authorizedRequest(string $method, string $uri, array $options = []): array
    {
        $headers = array_merge($options['headers'] ?? [], [
            'X-API-KEY' => $this->getApiKey(),
        ]);

        $options['headers'] = $headers;

        return $this->request($method, $uri, $options);
    }
}
