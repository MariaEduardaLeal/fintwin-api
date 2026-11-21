<?php

namespace App\Models;

use App\Enums\AssetClass;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticker',
    'name',
    'asset_class',
    'currency',
    'sector',
    'current_price',
    'price_updated_at',
    'metadata',
])]
class Asset extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asset_class' => AssetClass::class,
            'current_price' => 'decimal:4',
            'price_updated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function portfolioAssets(): HasMany
    {
        return $this->hasMany(PortfolioAsset::class);
    }

    public function portfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class, 'portfolio_assets')
            ->using(PortfolioAsset::class)
            ->withPivot(['quantity', 'average_price', 'invested_amount'])
            ->withTimestamps();
    }

    public function dividendsSchedule(): HasMany
    {
        return $this->hasMany(DividendSchedule::class);
    }
}
