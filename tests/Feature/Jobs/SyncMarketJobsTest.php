<?php

namespace Tests\Feature\Jobs;

use App\Enums\AssetClass;
use App\Enums\DividendStatus;
use App\Enums\DividendType;
use App\Integrations\Brapi\BrapiClient;
use App\Jobs\SyncAssetPricesJob;
use App\Jobs\SyncFutureDividendsJob;
use App\Models\Asset;
use App\Models\DividendSchedule;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncMarketJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_asset_prices_job_updates_tracked_assets_in_configured_batches(): void
    {
        config()->set('integrations.brapi.quote_batch_size', 2);

        $user = User::factory()->create();
        $portfolio = Portfolio::query()->create(['user_id' => $user->id, 'name' => 'Carteira', 'is_primary' => true]);

        $petr4 = Asset::query()->create(['ticker' => 'PETR4', 'name' => 'Petrobras', 'asset_class' => AssetClass::Stock, 'current_price' => 30.0]);
        $mxrf11 = Asset::query()->create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda', 'asset_class' => AssetClass::Fii, 'current_price' => 9.5]);
        $untracked = Asset::query()->create(['ticker' => 'VALE3', 'name' => 'Vale', 'asset_class' => AssetClass::Stock, 'current_price' => 60.0]);

        PortfolioAsset::query()->create(['portfolio_id' => $portfolio->id, 'asset_id' => $petr4->id, 'quantity' => 100, 'average_price' => 28, 'invested_amount' => 2800]);
        PortfolioAsset::query()->create(['portfolio_id' => $portfolio->id, 'asset_id' => $mxrf11->id, 'quantity' => 200, 'average_price' => 9.5, 'invested_amount' => 1900]);

        $brapi = Mockery::mock(BrapiClient::class);
        $brapi->shouldReceive('getQuotes')
            ->once()
            ->with(['MXRF11', 'PETR4'])
            ->andReturn([
                'results' => [
                    ['symbol' => 'MXRF11', 'regularMarketPrice' => 10.25],
                    ['symbol' => 'PETR4', 'regularMarketPrice' => 39.80],
                ],
            ]);

        $this->app->instance(BrapiClient::class, $brapi);

        SyncAssetPricesJob::dispatchSync();

        $this->assertSame('39.8000', $petr4->fresh()->current_price);
        $this->assertSame('10.2500', $mxrf11->fresh()->current_price);
        $this->assertSame('60.0000', $untracked->fresh()->current_price);
    }

    public function test_sync_future_dividends_job_persists_scheduled_and_paid_dividends_idempotently(): void
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::query()->create(['user_id' => $user->id, 'name' => 'Carteira', 'is_primary' => true]);
        $asset = Asset::query()->create(['ticker' => 'MXRF11', 'name' => 'Maxi Renda', 'asset_class' => AssetClass::Fii, 'current_price' => 10.0]);
        PortfolioAsset::query()->create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'quantity' => 300, 'average_price' => 9.8, 'invested_amount' => 2940]);

        $futureDate = CarbonImmutable::today()->addDays(15)->toDateString();
        $pastDate = CarbonImmutable::today()->subDays(15)->toDateString();

        $brapi = Mockery::mock(BrapiClient::class);
        $brapi->shouldReceive('getQuoteWithDividends')
            ->twice()
            ->with('MXRF11')
            ->andReturn([
                'results' => [
                    [
                        'symbol' => 'MXRF11',
                        'dividendsData' => [
                            'cashDividends' => [
                                [
                                    'label' => 'RENDIMENTO',
                                    'rate' => 0.10,
                                    'lastDatePrior' => CarbonImmutable::today()->addDays(5)->toDateString(),
                                    'paymentDate' => $futureDate.'T00:00:00.000Z',
                                ],
                                [
                                    'label' => 'JCP',
                                    'rate' => 0.25,
                                    'lastDatePrior' => CarbonImmutable::today()->subDays(25)->toDateString(),
                                    'paymentDate' => $pastDate.'T00:00:00.000Z',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(BrapiClient::class, $brapi);

        SyncFutureDividendsJob::dispatchSync();
        SyncFutureDividendsJob::dispatchSync();

        $this->assertDatabaseCount('dividends_schedule', 2);

        $scheduled = DividendSchedule::query()
            ->where('asset_id', $asset->id)
            ->where('type', DividendType::Yield->value)
            ->first();
        $paid = DividendSchedule::query()
            ->where('asset_id', $asset->id)
            ->where('type', DividendType::Jcp->value)
            ->first();

        $this->assertNotNull($scheduled);
        $this->assertSame($futureDate, $scheduled->payment_date->toDateString());
        $this->assertSame(DividendStatus::Scheduled, $scheduled->status);

        $this->assertNotNull($paid);
        $this->assertSame($pastDate, $paid->payment_date->toDateString());
        $this->assertSame(DividendStatus::Paid, $paid->status);
    }
}
