<?php

namespace App\Services;

use App\DTOs\OpenFinanceSyncRequestDto;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PluggyWebhookService
{
    /**
     * @var array<int, string>
     */
    private const SYNCABLE_EVENTS = [
        'item/created',
        'item/updated',
        'transactions/created',
        'transactions/updated',
    ];

    public function __construct(
        private readonly OpenFinanceSyncService $openFinanceSyncService,
    ) {}

    /**
     * Process an incoming Pluggy webhook event and dispatch async sync when applicable.
     *
     * @param  array<string, mixed>  $payload
     * @return array{received: bool, dispatched: bool}
     */
    public function handle(array $payload): array
    {
        $event = (string) ($payload['event'] ?? '');
        $eventId = isset($payload['eventId']) ? (string) $payload['eventId'] : null;
        $rawItemId = $payload['itemId'] ?? null;
        $itemId = is_string($rawItemId) ? trim($rawItemId) : '';

        Log::info('Pluggy webhook received.', [
            'event' => $event,
            'event_id' => $eventId,
            'item_id' => $itemId !== '' ? $itemId : null,
        ]);

        if ($event === 'item/error') {
            Log::warning('Pluggy item error webhook received.', [
                'event_id' => $eventId,
                'item_id' => $itemId !== '' ? $itemId : null,
                'error' => $payload['error'] ?? null,
            ]);

            return [
                'received' => true,
                'dispatched' => false,
            ];
        }

        if (in_array($event, self::SYNCABLE_EVENTS, true) && $itemId !== '') {
            $userId = $this->resolveUserId($payload['clientUserId'] ?? null, $itemId);

            if ($userId !== null) {
                $this->openFinanceSyncService->requestSync(new OpenFinanceSyncRequestDto(
                    userId: $userId,
                    pluggyItemId: $itemId,
                ));

                return [
                    'received' => true,
                    'dispatched' => true,
                ];
            }
        }

        return [
            'received' => true,
            'dispatched' => false,
        ];
    }

    private function resolveUserId(mixed $rawClientUserId, string $itemId): ?int
    {
        if (is_scalar($rawClientUserId)) {
            $candidate = trim((string) $rawClientUserId);

            if ($candidate !== '' && ctype_digit($candidate)) {
                $userId = (int) $candidate;

                if (User::query()->whereKey($userId)->exists()) {
                    return $userId;
                }
            }
        }

        $fallbackUserId = BankAccount::query()
            ->where('pluggy_item_id', $itemId)
            ->value('user_id');

        return is_numeric($fallbackUserId) ? (int) $fallbackUserId : null;
    }
}
