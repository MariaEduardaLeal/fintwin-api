<?php

namespace App\DTOs;

readonly class GoogleLoginDto
{
    public function __construct(
        public string $idToken,
        public string $deviceName = 'fintwin-mobile',
    ) {}
}
