<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PluggyConnectTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{access_token: string} $payload */
        $payload = $this->resource;

        return [
            'access_token' => $payload['access_token'],
        ];
    }
}
