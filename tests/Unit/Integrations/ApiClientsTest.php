<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Brapi\BrapiClient;
use App\Integrations\DeepSeek\DeepSeekClient;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Pluggy\PluggyClient;
use App\Integrations\Support\GuzzleClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\TestCase;

class ApiClientsTest extends TestCase
{
    public function test_pluggy_authenticates_caches_api_key_and_fetches_accounts(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['apiKey' => 'pluggy-jwt-123'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['results' => [['id' => 'acc-1']]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['results' => [['id' => 'acc-2']]], JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $http = (new GuzzleClientFactory)->make('https://api.pluggy.ai', handlerStack: $stack, maxRetries: 0);
        $cache = new CacheRepository(new ArrayStore);

        $client = new PluggyClient($http, $cache, 'client-id', 'client-secret', 6000);

        $first = $client->getAccounts('item-1');
        $second = $client->getAccounts('item-2');

        $this->assertSame('acc-1', $first['results'][0]['id']);
        $this->assertSame('acc-2', $second['results'][0]['id']);
        $this->assertCount(3, $history);
        $this->assertSame('POST', $history[0]['request']->getMethod());
        $this->assertSame('/auth', $history[0]['request']->getUri()->getPath());
        $this->assertSame('pluggy-jwt-123', $history[1]['request']->getHeaderLine('X-API-KEY'));
        $this->assertSame('pluggy-jwt-123', $history[2]['request']->getHeaderLine('X-API-KEY'));
    }

    public function test_pluggy_throws_exception_when_credentials_are_missing(): void
    {
        $http = (new GuzzleClientFactory)->make('https://api.pluggy.ai', handlerStack: HandlerStack::create(new MockHandler), maxRetries: 0);
        $client = new PluggyClient($http, new CacheRepository(new ArrayStore), '', '');

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('[pluggy] Missing PLUGGY_CLIENT_ID or PLUGGY_CLIENT_SECRET configuration.');

        $client->getAccounts('item-1');
    }

    public function test_deepseek_sends_bearer_token_and_chat_payload(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => '{"approved":true}']]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $http = (new GuzzleClientFactory)->make('https://api.deepseek.com', handlerStack: $stack, maxRetries: 0);
        $client = new DeepSeekClient($http, 'ds-secret-key', 'deepseek-chat');

        $result = $client->chatCompletion([
            ['role' => 'user', 'content' => 'Posso comprar um notebook?'],
        ]);

        $this->assertSame('{"approved":true}', $result['choices'][0]['message']['content']);
        $this->assertCount(1, $history);
        $this->assertSame('Bearer ds-secret-key', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('/chat/completions', $history[0]['request']->getUri()->getPath());
    }

    public function test_deepseek_throws_exception_when_api_key_is_missing(): void
    {
        $http = (new GuzzleClientFactory)->make('https://api.deepseek.com', handlerStack: HandlerStack::create(new MockHandler), maxRetries: 0);
        $client = new DeepSeekClient($http, '');

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('[deepseek] Missing DEEPSEEK_API_KEY configuration.');

        $client->chatCompletion([['role' => 'user', 'content' => 'Olá']]);
    }

    public function test_brapi_requests_quotes_with_dividends_and_retries_transient_503(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(503, [], 'Service Unavailable'),
            new Response(200, [], json_encode(['results' => [['symbol' => 'PETR4', 'regularMarketPrice' => 38.5]]], JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $http = (new GuzzleClientFactory)->make('https://brapi.dev/api', handlerStack: $stack, maxRetries: 2);
        $client = new BrapiClient($http, 'brapi-token-xyz');

        $response = $client->getQuoteWithDividends('petr4');

        $this->assertSame(38.5, $response['results'][0]['regularMarketPrice']);
        $this->assertSame(0, $mock->count());
        $this->assertCount(1, $history);
        $this->assertSame('/api/quote/PETR4', $history[0]['request']->getUri()->getPath());
        $this->assertStringContainsString('dividends=true', $history[0]['request']->getUri()->getQuery());
        $this->assertSame('Bearer brapi-token-xyz', $history[0]['request']->getHeaderLine('Authorization'));
    }
}
