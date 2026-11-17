<?php

namespace App\Models;

use App\Enums\DividendStatus;
use App\Enums\DividendType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'type',
    'amount_per_share',
    'ex_date',
    'payment_date',
    'status',
    'source',
])]
class DividendSchedule extends Model
{
    use HasFactory;

    protected $table = 'dividends_schedule';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DividendType::class,
            'amount_per_share' => 'decimal:6',
            'ex_date' => 'date',
            'payment_date' => 'date',
            'status' => DividendStatus::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
