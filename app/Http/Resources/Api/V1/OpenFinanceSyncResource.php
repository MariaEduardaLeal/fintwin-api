<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpenFinanceSyncResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{status: string, pluggy_item_id: string, queues: array<int, string>, requested_at: string} $payload */
        $payload = $this->resource;

        return [
            'status' => $payload['status'],
            'pluggy_item_id' => $payload['pluggy_item_id'],
            'queues' => $payload['queues'],
            'requested_at' => $payload['requested_at'],
        ];
    }
}
