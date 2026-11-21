<?php

namespace App\Services;

use App\DTOs\AuthTokenDto;
use App\DTOs\GoogleLoginDto;
use App\DTOs\LoginUserDto;
use App\DTOs\RegisterUserDto;
use App\Enums\RiskProfile;
use App\Integrations\Google\GoogleAuthClient;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly GoogleAuthClient $googleAuth,
    ) {}

    public function register(RegisterUserDto $dto): AuthTokenDto
    {
        $user = DB::transaction(function () use ($dto): User {
            $user = $this->users->create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password,
            ]);

            $this->ensureDefaultFinancialProfile($user->id);

            return $user;
        });

        $token = $user->createToken($dto->deviceName)->plainTextToken;

        return new AuthTokenDto(
            user: $user,
            token: $token,
            isNewUser: true,
        );
    }

    public function login(LoginUserDto $dto): AuthTokenDto
    {
        $user = $this->users->findByEmail($dto->email);

        if ($user === null || $user->password === null || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken($dto->deviceName)->plainTextToken;

        return new AuthTokenDto(
            user: $user,
            token: $token,
            isNewUser: false,
        );
    }

    public function loginWithGoogle(GoogleLoginDto $dto): AuthTokenDto
    {
        $claims = $this->googleAuth->verifyIdToken($dto->idToken);

        $isNewUser = false;

        $user = DB::transaction(function () use ($claims, &$isNewUser): User {
            $user = $this->users->findByGoogleId($claims['google_id']);

            if ($user !== null) {
                return $this->users->update($user, [
                    'avatar_url' => $claims['avatar_url'] ?? $user->avatar_url,
                ]);
            }

            $existingByEmail = $this->users->findByEmail($claims['email']);

            if ($existingByEmail !== null) {
                return $this->users->update($existingByEmail, [
                    'google_id' => $claims['google_id'],
                    'avatar_url' => $claims['avatar_url'] ?? $existingByEmail->avatar_url,
                    'email_verified_at' => $existingByEmail->email_verified_at ?? now(),
                ]);
            }

            $isNewUser = true;

            $user = $this->users->create([
                'name' => $claims['name'],
                'email' => $claims['email'],
                'google_id' => $claims['google_id'],
                'avatar_url' => $claims['avatar_url'],
                'email_verified_at' => now(),
                'password' => null,
            ]);

            $this->ensureDefaultFinancialProfile($user->id);

            return $user;
        });

        $token = $user->createToken($dto->deviceName)->plainTextToken;

        return new AuthTokenDto(
            user: $user,
            token: $token,
            isNewUser: $isNewUser,
        );
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function ensureDefaultFinancialProfile(int $userId): void
    {
        $this->users->upsertFinancialProfile($userId, [
            'monthly_income' => 0,
            'monthly_fixed_expenses' => 0,
            'emergency_fund_target' => 0,
            'risk_profile' => RiskProfile::Moderate,
            'investment_horizon_months' => 60,
            'ai_strictness_level' => 'balanced',
        ]);
    }
}
