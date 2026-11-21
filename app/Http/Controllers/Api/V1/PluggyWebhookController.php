<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PluggyWebhookRequest;
use App\Services\PluggyWebhookService;
use Illuminate\Http\JsonResponse;

class PluggyWebhookController extends Controller
{
    public function __construct(
        private readonly PluggyWebhookService $pluggyWebhookService,
    ) {}

    public function __invoke(PluggyWebhookRequest $request): JsonResponse
    {
        $configuredSecret = config('integrations.pluggy.webhook_secret');

        if (is_string($configuredSecret) && $configuredSecret !== '') {
            $bearer = $request->bearerToken();
            $header = $request->header('X-Pluggy-Webhook-Secret');

            $bearerMatches = is_string($bearer) && hash_equals($configuredSecret, $bearer);
            $headerMatches = is_string($header) && hash_equals($configuredSecret, $header);

            if (! $bearerMatches && ! $headerMatches) {
                return response()->json([
                    'message' => 'Unauthorized webhook request.',
                ], 401);
            }
        }

        $configuredClientId = config('integrations.pluggy.client_id');

        if (is_string($configuredClientId) && $configuredClientId !== '') {
            $incomingClientId = $request->validated('clientId');

            if (! is_string($incomingClientId) || ! hash_equals($configuredClientId, $incomingClientId)) {
                return response()->json([
                    'message' => 'Invalid Pluggy clientId.',
                ], 403);
            }
        }

        $this->pluggyWebhookService->handle($request->validated());

        return response()->json([
            'received' => true,
        ], 200);
    }
}
