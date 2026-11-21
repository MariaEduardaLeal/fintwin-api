<?php

namespace App\Services;

use App\Integrations\Pluggy\PluggyClient;
use App\Models\User;

class PluggyConnectService
{
    public function __construct(
        private readonly PluggyClient $pluggy,
    ) {}

    /**
     * @return array{access_token: string}
     */
    public function createToken(User $user, ?string $itemId = null): array
    {
        $response = $this->pluggy->createConnectToken(
            itemId: $itemId,
            clientUserId: (string) $user->id,
        );

        return [
            'access_token' => (string) ($response['accessToken'] ?? ''),
        ];
    }
}
