<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'credit_card_id',
    'invoice_id',
    'category_id',
    'pluggy_transaction_id',
    'installment_group_id',
    'description',
    'amount',
    'total_purchase_amount',
    'installment_number',
    'total_installments',
    'purchase_date',
    'billing_date',
    'is_projected',
])]
class CreditTransaction extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_purchase_amount' => 'decimal:2',
            'installment_number' => 'integer',
            'total_installments' => 'integer',
            'purchase_date' => 'date',
            'billing_date' => 'date',
            'is_projected' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
