<?php

namespace App\Repositories\Contracts;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioAsset;

interface PortfolioRepositoryInterface
{
    public function getUserPrimaryPortfolioWithAssets(int $userId): ?Portfolio;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertAssetByTicker(string $ticker, array $attributes): Asset;

    public function updateAssetPrice(int $assetId, float $price): void;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncPortfolioAsset(int $portfolioId, int $assetId, array $attributes): PortfolioAsset;

    /**
     * @return array<int, string>
     */
    public function getAllTrackedTickers(): array;
}
