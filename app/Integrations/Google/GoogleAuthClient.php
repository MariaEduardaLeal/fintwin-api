<?php

namespace App\Integrations\Google;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Support\BaseApiClient;
use GuzzleHttp\ClientInterface;

class GoogleAuthClient extends BaseApiClient
{
    private const ALLOWED_ISSUERS = [
        'accounts.google.com',
        'https://accounts.google.com',
    ];

    /**
     * @param  array<int, string>  $allowedClientIds
     */
    public function __construct(
        ClientInterface $http,
        private readonly array $allowedClientIds = [],
    ) {
        parent::__construct($http);
    }

    protected function providerName(): string
    {
        return 'google';
    }

    /**
     * Verify a Google ID token via tokeninfo and return normalized identity claims.
     *
     * @return array{google_id: string, email: string, name: string, avatar_url: ?string}
     */
    public function verifyIdToken(string $idToken): array
    {
        if ($this->allowedClientIds === []) {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Missing GOOGLE_CLIENT_ID or GOOGLE_CLIENT_IDS configuration.',
                statusCode: 500,
            );
        }

        if (trim($idToken) === '') {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Google ID token cannot be empty.',
                statusCode: 401,
            );
        }

        try {
            $claims = $this->request('GET', 'tokeninfo', [
                'query' => ['id_token' => $idToken],
            ]);
        } catch (IntegrationException $exception) {
            if ($exception->statusCode === 400) {
                throw new IntegrationException(
                    provider: $this->providerName(),
                    message: 'Invalid Google ID token.',
                    statusCode: 401,
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $sub = isset($claims['sub']) && is_string($claims['sub']) ? trim($claims['sub']) : '';
        $email = isset($claims['email']) && is_string($claims['email']) ? strtolower(trim($claims['email'])) : '';
        $aud = isset($claims['aud']) && is_string($claims['aud']) ? trim($claims['aud']) : '';
        $emailVerified = $claims['email_verified'] ?? false;

        if ($sub === '' || $email === '') {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Google ID token is missing required identity claims.',
                statusCode: 401,
            );
        }

        if ($emailVerified !== true && $emailVerified !== 'true') {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Google account email is not verified.',
                statusCode: 401,
            );
        }

        if (isset($claims['iss']) && ! in_array((string) $claims['iss'], self::ALLOWED_ISSUERS, true)) {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Google ID token issuer is invalid.',
                statusCode: 401,
            );
        }

        if (isset($claims['exp']) && (int) $claims['exp'] <= time()) {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Google ID token has expired.',
                statusCode: 401,
            );
        }

        if ($aud === '' || ! in_array($aud, $this->allowedClientIds, true)) {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Google ID token audience does not match configured client IDs.',
                statusCode: 401,
            );
        }

        $name = isset($claims['name']) && is_string($claims['name']) && trim($claims['name']) !== ''
            ? trim($claims['name'])
            : explode('@', $email)[0];

        $avatarUrl = isset($claims['picture']) && is_string($claims['picture']) && trim($claims['picture']) !== ''
            ? trim($claims['picture'])
            : null;

        return [
            'google_id' => $sub,
            'email' => $email,
            'name' => $name,
            'avatar_url' => $avatarUrl,
        ];
    }
}
