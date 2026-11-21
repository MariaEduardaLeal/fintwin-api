<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreatePluggyConnectTokenRequest;
use App\Http\Resources\Api\V1\PluggyConnectTokenResource;
use App\Integrations\Exceptions\IntegrationException;
use App\Models\User;
use App\Services\PluggyConnectService;
use Illuminate\Http\JsonResponse;

class PluggyConnectController extends Controller
{
    public function __construct(
        private readonly PluggyConnectService $pluggyConnectService,
    ) {}

    public function __invoke(CreatePluggyConnectTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $payload = $this->pluggyConnectService->createToken(
                user: $user,
                itemId: $request->validated('item_id'),
            );
        } catch (IntegrationException $exception) {
            return response()->json([
                'message' => 'Falha ao gerar token de conexão Open Finance.',
            ], $exception->statusCode === 401 ? 502 : 503);
        }

        return (new PluggyConnectTokenResource($payload))
            ->response()
            ->setStatusCode(200);
    }
}
