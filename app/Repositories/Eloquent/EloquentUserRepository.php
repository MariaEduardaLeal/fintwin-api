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

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', strtolower(trim($email)))
            ->first();
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::query()
            ->where('google_id', $googleId)
            ->first();
    }

    public function findWithFinancialProfile(int $userId): ?User
    {
        return User::query()
            ->with('financialProfile')
            ->find($userId);
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        return $user->refresh();
    }

    public function upsertFinancialProfile(int $userId, array $attributes): UserFinancialProfile
    {
        return UserFinancialProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            $attributes,
        );
    }
}
