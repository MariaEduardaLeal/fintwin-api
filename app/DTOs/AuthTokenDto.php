<?php

namespace App\DTOs;

use App\Models\User;

readonly class AuthTokenDto
{
    public function __construct(
        public User $user,
        public string $token,
        public bool $isNewUser = false,
    ) {}
}
