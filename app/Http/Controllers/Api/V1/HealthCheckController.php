<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HealthCheckResource;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $dto = $this->healthCheckService->check();
        $statusCode = $dto->status === 'ok' ? 200 : 503;

        return (new HealthCheckResource($dto))
            ->response()
            ->setStatusCode($statusCode);
    }
}
