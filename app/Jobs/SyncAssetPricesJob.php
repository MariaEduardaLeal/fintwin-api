<?php

namespace App\Jobs;

use App\Integrations\Brapi\BrapiClient;
use App\Models\Asset;
use App\Repositories\Contracts\PortfolioRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAssetPricesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  array<int, string>|null  $tickers
     */
    public function __construct(
        public readonly ?array $tickers = null,
    ) {
        $this->onQueue('market-data');
    }

    public function handle(
        BrapiClient $brapi,
        PortfolioRepositoryInterface $portfolios,
    ): void {
        $assets = $this->tickers !== null
            ? $portfolios->findAssetsByTickers($this->tickers)
            : $portfolios->getAllTrackedAssets();

        if ($assets->isEmpty()) {
            return;
        }

        $batchSize = max(1, (int) config('integrations.brapi.quote_batch_size', 1));
        $assetsByTicker = $assets->keyBy(static fn (Asset $asset): string => strtoupper($asset->ticker));

        foreach ($assets->chunk($batchSize) as $chunk) {
            $chunkTickers = $chunk->pluck('ticker')->all();
            $response = $brapi->getQuotes($chunkTickers);
            $results = is_array($response['results'] ?? null) ? $response['results'] : [];

            foreach ($results as $quote) {
                if (! is_array($quote)) {
                    continue;
                }

                $symbol = strtoupper(trim((string) ($quote['symbol'] ?? '')));
                $price = $quote['regularMarketPrice'] ?? null;

                if ($symbol === '' || ! is_numeric($price) || ! $assetsByTicker->has($symbol)) {
                    continue;
                }

                /** @var Asset $asset */
                $asset = $assetsByTicker->get($symbol);
                $portfolios->updateAssetPrice($asset->id, round((float) $price, 4));
            }
        }
    }
}
