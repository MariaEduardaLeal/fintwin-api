<?php

namespace App\DTOs;

readonly class OpenFinanceSyncRequestDto
{
    public function __construct(
        public int $userId,
        public string $pluggyItemId,
        public ?string $fromDate = null,
    ) {}
}
