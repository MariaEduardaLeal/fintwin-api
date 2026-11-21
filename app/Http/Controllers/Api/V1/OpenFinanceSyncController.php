<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOpenFinanceSyncRequest;
use App\Http\Resources\Api\V1\OpenFinanceSyncResource;
use App\Services\OpenFinanceSyncService;
use Illuminate\Http\JsonResponse;

class OpenFinanceSyncController extends Controller
{
    public function __construct(
        private readonly OpenFinanceSyncService $openFinanceSyncService,
    ) {}

    public function __invoke(RequestOpenFinanceSyncRequest $request): JsonResponse
    {
        $payload = $this->openFinanceSyncService->requestSync($request->toDto());

        return (new OpenFinanceSyncResource($payload))
            ->response()
            ->setStatusCode(202);
    }
}
