<?php

namespace App\Integrations\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleClientFactory
{
    /**
     * Build a preconfigured Guzzle client with JSON defaults and transient retry handling.
     *
     * @param  array<string, string>  $defaultHeaders
     */
    public function make(
        string $baseUri,
        int $timeout = 15,
        int $connectTimeout = 5,
        array $defaultHeaders = [],
        ?HandlerStack $handlerStack = null,
        int $maxRetries = 2,
    ): Client {
        $stack = $handlerStack ?? HandlerStack::create();

        if ($maxRetries > 0) {
            $stack->push(Middleware::retry(
                $this->retryDecider($maxRetries),
                static fn (int $retries): int => 100 * (2 ** ($retries - 1)),
            ));
        }

        return new Client([
            'base_uri' => rtrim($baseUri, '/').'/',
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'handler' => $stack,
            'headers' => array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], $defaultHeaders),
        ]);
    }

    /**
     * @return callable(int, RequestInterface, ?ResponseInterface, ?\Throwable): bool
     */
    private function retryDecider(int $maxRetries): callable
    {
        return static function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null,
        ) use ($maxRetries): bool {
            if ($retries >= $maxRetries) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                return true;
            }

            if ($response === null) {
                return false;
            }

            $status = $response->getStatusCode();

            return $status === 429 || $status >= 500;
        };
    }
}
