<?php

namespace App\Services;

use App\DTOs\OpenFinanceSyncRequestDto;
use App\Events\BankSyncRequestedEvent;

class OpenFinanceSyncService
{
    /**
     * Dispatch the domain event that schedules asynchronous bank and credit card ingestion jobs.
     *
     * @return array{status: string, pluggy_item_id: string, queues: array<int, string>, requested_at: string}
     */
    public function requestSync(OpenFinanceSyncRequestDto $dto): array
    {
        event(new BankSyncRequestedEvent(
            userId: $dto->userId,
            pluggyItemId: $dto->pluggyItemId,
            fromDate: $dto->fromDate,
        ));

        return [
            'status' => 'queued',
            'pluggy_item_id' => $dto->pluggyItemId,
            'queues' => ['open-finance'],
            'requested_at' => now()->toIso8601String(),
        ];
    }
}
