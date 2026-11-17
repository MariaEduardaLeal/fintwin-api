<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\UserFinancialProfile;

interface UserRepositoryInterface
{
    public function findById(int $userId): ?User;

    public function findWithFinancialProfile(int $userId): ?User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertFinancialProfile(int $userId, array $attributes): UserFinancialProfile;
}
