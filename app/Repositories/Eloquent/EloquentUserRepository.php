<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\UserFinancialProfile;
use App\Repositories\Contracts\UserRepositoryInterface;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $userId): ?User
    {
        return User::query()->find($userId);
    }

    public function findWithFinancialProfile(int $userId): ?User
    {
        return User::query()
            ->with('financialProfile')
            ->find($userId);
    }

    public function upsertFinancialProfile(int $userId, array $attributes): UserFinancialProfile
    {
        return UserFinancialProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            $attributes,
        );
    }
}
