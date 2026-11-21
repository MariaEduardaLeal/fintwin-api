<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\DTOs\LoginUserDto;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toDto(): LoginUserDto
    {
        return new LoginUserDto(
            email: strtolower(trim((string) $this->validated('email'))),
            password: (string) $this->validated('password'),
            deviceName: (string) ($this->validated('device_name') ?: 'fintwin-mobile'),
        );
    }
}
