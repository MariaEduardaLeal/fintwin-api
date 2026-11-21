<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\DTOs\GoogleLoginDto;
use Illuminate\Foundation\Http\FormRequest;

class GoogleLoginRequest extends FormRequest
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
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toDto(): GoogleLoginDto
    {
        return new GoogleLoginDto(
            idToken: (string) $this->validated('id_token'),
            deviceName: (string) ($this->validated('device_name') ?: 'fintwin-mobile'),
        );
    }
}
