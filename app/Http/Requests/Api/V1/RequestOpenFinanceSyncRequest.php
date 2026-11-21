<?php

namespace App\Http\Requests\Api\V1;

use App\DTOs\OpenFinanceSyncRequestDto;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RequestOpenFinanceSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'pluggy_item_id' => ['required', 'string', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function toDto(): OpenFinanceSyncRequestDto
    {
        /** @var User $user */
        $user = $this->user();

        return new OpenFinanceSyncRequestDto(
            userId: $user->id,
            pluggyItemId: (string) $this->validated('pluggy_item_id'),
            fromDate: $this->validated('from_date'),
        );
    }
}
