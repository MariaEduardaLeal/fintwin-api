<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\GoogleLoginRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use App\Integrations\Exceptions\IntegrationException;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->toDto());

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->toDto());

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(200);
    }

    public function google(GoogleLoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginWithGoogle($request->toDto());
        } catch (IntegrationException $exception) {
            $status = match ($exception->statusCode) {
                400, 401 => 401,
                500 => 500,
                default => 503,
            };

            return response()->json([
                'message' => $status === 401
                    ? 'Token do Google inválido ou expirado.'
                    : 'Autenticação com Google indisponível no momento.',
            ], $status);
        }

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(200);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return response()->json(null, 204);
    }
}
