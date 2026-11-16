<?php

namespace App\Integrations\DeepSeek;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Support\BaseApiClient;
use GuzzleHttp\ClientInterface;

class DeepSeekClient extends BaseApiClient
{
    public function __construct(
        ClientInterface $http,
        private readonly string $apiKey,
        private readonly string $defaultModel = 'deepseek-chat',
    ) {
        parent::__construct($http);
    }

    protected function providerName(): string
    {
        return 'deepseek';
    }

    /**
     * Send a chat completion request to DeepSeek.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chatCompletion(array $messages, array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new IntegrationException(
                provider: $this->providerName(),
                message: 'Missing DEEPSEEK_API_KEY configuration.',
            );
        }

        $payload = array_merge([
            'model' => $this->defaultModel,
            'messages' => $messages,
            'temperature' => 0.2,
        ], $options);

        return $this->request('POST', 'chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
            ],
            'json' => $payload,
        ]);
    }
}
