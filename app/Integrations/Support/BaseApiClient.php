<?php

namespace App\Integrations\Support;

use App\Integrations\Exceptions\IntegrationException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

abstract class BaseApiClient
{
    public function __construct(
        protected readonly ClientInterface $http,
    ) {}

    abstract protected function providerName(): string;

    /**
     * Execute an HTTP request and decode its JSON body into an associative array.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $normalizedUri = ltrim($uri, '/');

        try {
            $response = $this->http->request($method, $normalizedUri, $options);
        } catch (BadResponseException $exception) {
            $status = $exception->getResponse()->getStatusCode();

            throw new IntegrationException(
                provider: $this->providerName(),
                message: "HTTP request failed with status {$status}.",
                statusCode: $status,
                previous: $exception,
            );
        } catch (GuzzleException $exception) {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Transport error while communicating with upstream API.',
                previous: $exception,
            );
        }

        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Invalid JSON payload returned by upstream API.',
                statusCode: $response->getStatusCode(),
                previous: $exception,
            );
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }
}
