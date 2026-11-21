<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\DTOs\RegisterUserDto;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toDto(): RegisterUserDto
    {
        return new RegisterUserDto(
            name: (string) $this->validated('name'),
            email: strtolower(trim((string) $this->validated('email'))),
            password: (string) $this->validated('password'),
            deviceName: (string) ($this->validated('device_name') ?: 'fintwin-mobile'),
        );
    }
}
