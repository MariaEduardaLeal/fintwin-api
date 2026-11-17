<?php

namespace App\Repositories\Eloquent;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;
use App\Repositories\Contracts\PortfolioRepositoryInterface;

class EloquentPortfolioRepository implements PortfolioRepositoryInterface
{
    public function getUserPrimaryPortfolioWithAssets(int $userId): ?Portfolio
    {
        return Portfolio::query()
            ->with(['portfolioAssets.asset'])
            ->where('user_id', $userId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    public function upsertAssetByTicker(string $ticker, array $attributes): Asset
    {
        return Asset::query()->updateOrCreate(
            ['ticker' => strtoupper(trim($ticker))],
            $attributes,
        );
    }

    public function updateAssetPrice(int $assetId, float $price): void
    {
        Asset::query()
            ->whereKey($assetId)
            ->update([
                'current_price' => $price,
                'price_updated_at' => now(),
            ]);
    }

    public function syncPortfolioAsset(int $portfolioId, int $assetId, array $attributes): PortfolioAsset
    {
        return PortfolioAsset::query()->updateOrCreate(
            [
                'portfolio_id' => $portfolioId,
                'asset_id' => $assetId,
            ],
            $attributes,
        );
    }

    public function getAllTrackedTickers(): array
    {
        return Asset::query()
            ->whereHas('portfolioAssets', static fn ($query) => $query->where('quantity', '>', 0))
            ->orderBy('ticker')
            ->pluck('ticker')
            ->all();
    }
}
