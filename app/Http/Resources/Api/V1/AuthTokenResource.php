<?php

namespace App\Http\Resources\Api\V1;

use App\DTOs\AuthTokenDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuthTokenDto
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuthTokenDto $dto */
        $dto = $this->resource;

        return [
            'token' => $dto->token,
            'token_type' => 'Bearer',
            'is_new_user' => $dto->isNewUser,
            'user' => [
                'id' => $dto->user->id,
                'name' => $dto->user->name,
                'email' => $dto->user->email,
                'avatar_url' => $dto->user->avatar_url,
                'has_google_linked' => $dto->user->google_id !== null,
            ],
        ];
    }
}
