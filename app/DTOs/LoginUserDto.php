<?php

namespace App\DTOs;

readonly class LoginUserDto
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName = 'fintwin-mobile',
    ) {}
}
