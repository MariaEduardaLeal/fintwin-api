<?php

namespace App\Repositories\Eloquent;

use App\Models\DividendSchedule;
use App\Models\PortfolioAsset;
use App\Repositories\Contracts\DividendScheduleRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentDividendScheduleRepository implements DividendScheduleRepositoryInterface
{
    public function upsertDividend(int $assetId, array $match, array $attributes): DividendSchedule
    {
        return DividendSchedule::query()->updateOrCreate(
            array_merge($match, ['asset_id' => $assetId]),
            $attributes,
        );
    }

    public function getUpcomingDividendsForUser(int $userId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $holdings = PortfolioAsset::query()
            ->with(['asset.dividendsSchedule' => static function ($query) use ($from, $to): void {
                $query->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
                    ->orderBy('payment_date');
            }])
            ->where('quantity', '>', 0)
            ->whereHas('portfolio', static fn ($query) => $query->where('user_id', $userId))
            ->get();

        $results = collect();

        foreach ($holdings as $holding) {
            if ($holding->asset === null) {
                continue;
            }

            $quantity = (float) $holding->quantity;

            foreach ($holding->asset->dividendsSchedule as $dividend) {
                $amountPerShare = (float) $dividend->amount_per_share;

                $results->push([
                    'ticker' => $holding->asset->ticker,
                    'type' => $dividend->type->value,
                    'payment_date' => $dividend->payment_date->toDateString(),
                    'amount_per_share' => $amountPerShare,
                    'quantity' => $quantity,
                    'estimated_total' => round($quantity * $amountPerShare, 2),
                    'status' => $dividend->status->value,
                ]);
            }
        }

        return $results->sortBy('payment_date')->values();
    }
}
