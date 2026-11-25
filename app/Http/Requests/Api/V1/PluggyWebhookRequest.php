<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PluggyWebhookRequest extends FormRequest
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
            'event' => ['required', 'string', 'max:100'],
            'eventId' => ['nullable', 'string', 'max:255'],
            'clientId' => ['nullable', 'string', 'max:255'],
            'itemId' => ['nullable', 'string', 'max:255'],
            'clientUserId' => ['nullable'],
            'error' => ['nullable'],
        ];
    }
}
