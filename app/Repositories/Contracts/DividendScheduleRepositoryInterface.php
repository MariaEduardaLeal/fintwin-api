<?php

namespace App\Repositories\Contracts;

use App\Models\DividendSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface DividendScheduleRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function upsertDividend(int $assetId, array $match, array $attributes): DividendSchedule;

    /**
     * @return Collection<int, array{
     *     ticker: string,
     *     type: string,
     *     payment_date: string,
     *     amount_per_share: float,
     *     quantity: float,
     *     estimated_total: float,
     *     status: string
     * }>
     */
    public function getUpcomingDividendsForUser(int $userId, CarbonInterface $from, CarbonInterface $to): Collection;
}
