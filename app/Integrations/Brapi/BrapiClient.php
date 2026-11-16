<?php

namespace App\Integrations\Brapi;

use App\Integrations\Support\BaseApiClient;
use GuzzleHttp\ClientInterface;

class BrapiClient extends BaseApiClient
{
    public function __construct(
        ClientInterface $http,
        private readonly string $apiToken = '',
    ) {
        parent::__construct($http);
    }

    protected function providerName(): string
    {
        return 'brapi';
    }

    /**
     * Fetch market quotes for one or more B3/stock tickers.
     *
     * @param  array<int, string>  $tickers
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getQuotes(array $tickers, array $params = []): array
    {
        $joinedTickers = implode(',', array_map(
            static fn (string $ticker): string => strtoupper(trim($ticker)),
            $tickers,
        ));

        $options = ['query' => $params];

        if ($this->apiToken !== '') {
            $options['headers'] = [
                'Authorization' => "Bearer {$this->apiToken}",
            ];
        }

        return $this->request('GET', "quote/{$joinedTickers}", $options);
    }

    /**
     * Fetch quote along with dividends and fundamental data for a single ticker.
     *
     * @return array<string, mixed>
     */
    public function getQuoteWithDividends(string $ticker): array
    {
        return $this->getQuotes([$ticker], [
            'dividends' => 'true',
            'fundamental' => 'true',
        ]);
    }
}
