<?php

namespace App\Models;

use App\Enums\RiskProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'monthly_income',
    'monthly_fixed_expenses',
    'emergency_fund_target',
    'risk_profile',
    'investment_horizon_months',
    'ai_strictness_level',
    'financial_goals',
])]
class UserFinancialProfile extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_income' => 'decimal:2',
            'monthly_fixed_expenses' => 'decimal:2',
            'emergency_fund_target' => 'decimal:2',
            'risk_profile' => RiskProfile::class,
            'investment_horizon_months' => 'integer',
            'financial_goals' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
