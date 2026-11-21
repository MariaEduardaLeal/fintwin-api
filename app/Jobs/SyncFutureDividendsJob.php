<?php

namespace App\Jobs;

use App\Enums\DividendStatus;
use App\Enums\DividendType;
use App\Integrations\Brapi\BrapiClient;
use App\Repositories\Contracts\DividendScheduleRepositoryInterface;
use App\Repositories\Contracts\PortfolioRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SyncFutureDividendsJob implements ShouldQueue
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
        DividendScheduleRepositoryInterface $dividends,
    ): void {
        $assets = $this->tickers !== null
            ? $portfolios->findAssetsByTickers($this->tickers)
            : $portfolios->getAllTrackedAssets();

        if ($assets->isEmpty()) {
            return;
        }

        $today = CarbonImmutable::today();

        foreach ($assets as $asset) {
            $response = $brapi->getQuoteWithDividends($asset->ticker);
            $results = is_array($response['results'] ?? null) ? $response['results'] : [];
            $firstResult = is_array($results[0] ?? null) ? $results[0] : [];
            $cashDividends = is_array($firstResult['dividendsData']['cashDividends'] ?? null)
                ? $firstResult['dividendsData']['cashDividends']
                : [];

            foreach ($cashDividends as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $paymentDateRaw = $item['paymentDate'] ?? null;
                $rate = $item['rate'] ?? null;

                if (! is_string($paymentDateRaw) || trim($paymentDateRaw) === '' || ! is_numeric($rate) || (float) $rate <= 0) {
                    continue;
                }

                $paymentDate = CarbonImmutable::parse($paymentDateRaw)->startOfDay();
                $exDate = is_string($item['lastDatePrior'] ?? null) && trim($item['lastDatePrior']) !== ''
                    ? CarbonImmutable::parse($item['lastDatePrior'])->toDateString()
                    : null;

                $type = $this->mapDividendType((string) ($item['label'] ?? 'DIVIDENDO'));
                $amountPerShare = round((float) $rate, 6);
                $status = $paymentDate->greaterThanOrEqualTo($today)
                    ? DividendStatus::Scheduled
                    : DividendStatus::Paid;

                $dividends->upsertDividend(
                    assetId: $asset->id,
                    match: [
                        'type' => $type->value,
                        'payment_date' => $paymentDate->toDateString(),
                        'amount_per_share' => $amountPerShare,
                    ],
                    attributes: [
                        'ex_date' => $exDate,
                        'status' => $status,
                        'source' => 'brapi',
                    ],
                );
            }
        }
    }

    private function mapDividendType(string $label): DividendType
    {
        $normalized = Str::ascii(Str::upper(trim($label)));

        return match (true) {
            str_contains($normalized, 'JCP') || str_contains($normalized, 'JSCP') || str_contains($normalized, 'JUROS') => DividendType::Jcp,
            str_contains($normalized, 'REND') => DividendType::Yield,
            str_contains($normalized, 'AMORT') => DividendType::Amortization,
            default => DividendType::Dividend,
        };
    }
}
