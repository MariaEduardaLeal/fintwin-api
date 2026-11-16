<?php

namespace App\Http\Resources\Api\V1;

use App\DTOs\HealthCheckDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HealthCheckDto
 */
class HealthCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HealthCheckDto $dto */
        $dto = $this->resource;

        return [
            'status' => $dto->status,
            'service' => $dto->service,
            'environment' => $dto->environment,
            'services' => $dto->services,
            'timestamp' => $dto->timestamp,
        ];
    }
}
