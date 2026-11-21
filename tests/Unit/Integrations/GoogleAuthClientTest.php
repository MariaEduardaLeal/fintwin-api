<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Google\GoogleAuthClient;
use App\Integrations\Support\GuzzleClientFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class GoogleAuthClientTest extends TestCase
{
    public function test_verifies_valid_google_id_token_and_normalizes_claims(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sub' => 'google-sub-123',
                'email' => 'Duda@Example.com',
                'email_verified' => 'true',
                'name' => 'Maria Eduarda',
                'picture' => 'https://lh3.googleusercontent.com/avatar.png',
                'iss' => 'https://accounts.google.com',
                'aud' => 'mobile-client-id.apps.googleusercontent.com',
                'exp' => (string) (time() + 3600),
            ], JSON_THROW_ON_ERROR)),
        ]);

        $http = (new GuzzleClientFactory)->make('https://oauth2.googleapis.com', handlerStack: HandlerStack::create($mock), maxRetries: 0);
        $client = new GoogleAuthClient($http, ['mobile-client-id.apps.googleusercontent.com']);

        $claims = $client->verifyIdToken('valid-id-token');

        $this->assertSame('google-sub-123', $claims['google_id']);
        $this->assertSame('duda@example.com', $claims['email']);
        $this->assertSame('Maria Eduarda', $claims['name']);
        $this->assertSame('https://lh3.googleusercontent.com/avatar.png', $claims['avatar_url']);
    }

    public function test_rejects_token_with_mismatched_audience(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sub' => 'google-sub-123',
                'email' => 'duda@example.com',
                'email_verified' => true,
                'iss' => 'https://accounts.google.com',
                'aud' => 'wrong-client-id',
                'exp' => time() + 3600,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $http = (new GuzzleClientFactory)->make('https://oauth2.googleapis.com', handlerStack: HandlerStack::create($mock), maxRetries: 0);
        $client = new GoogleAuthClient($http, ['expected-client-id']);

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('audience does not match');

        $client->verifyIdToken('token-wrong-aud');
    }

    public function test_rejects_unverified_email_and_expired_token(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'sub' => 'google-sub-123',
                'email' => 'duda@example.com',
                'email_verified' => 'false',
                'aud' => 'expected-client-id',
                'exp' => time() + 3600,
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'sub' => 'google-sub-123',
                'email' => 'duda@example.com',
                'email_verified' => 'true',
                'aud' => 'expected-client-id',
                'exp' => time() - 10,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $http = (new GuzzleClientFactory)->make('https://oauth2.googleapis.com', handlerStack: HandlerStack::create($mock), maxRetries: 0);
        $client = new GoogleAuthClient($http, ['expected-client-id']);

        try {
            $client->verifyIdToken('unverified');
            $this->fail('Expected unverified email to throw IntegrationException');
        } catch (IntegrationException $exception) {
            $this->assertStringContainsString('not verified', $exception->getMessage());
        }

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('expired');
        $client->verifyIdToken('expired');
    }

    public function test_rejects_when_client_ids_are_not_configured(): void
    {
        $http = (new GuzzleClientFactory)->make('https://oauth2.googleapis.com', handlerStack: HandlerStack::create(new MockHandler), maxRetries: 0);
        $client = new GoogleAuthClient($http, []);

        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('Missing GOOGLE_CLIENT_ID');

        $client->verifyIdToken('any-token');
    }
}
