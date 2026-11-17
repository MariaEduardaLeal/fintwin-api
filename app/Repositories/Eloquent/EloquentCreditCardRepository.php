<?php

namespace App\Repositories\Eloquent;

use App\Models\CreditCard;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Repositories\Contracts\CreditCardRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentCreditCardRepository implements CreditCardRepositoryInterface
{
    public function getUserCardsWithInvoices(int $userId): Collection
    {
        return CreditCard::query()
            ->with(['invoices' => static fn ($query) => $query->orderBy('reference_month')])
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();
    }

    public function upsertCreditCard(int $userId, array $match, array $attributes): CreditCard
    {
        return CreditCard::query()->updateOrCreate(
            array_merge($match, ['user_id' => $userId]),
            $attributes,
        );
    }

    public function upsertInvoice(int $userId, int $creditCardId, string $referenceMonth, array $attributes): Invoice
    {
        return Invoice::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'credit_card_id' => $creditCardId,
                'reference_month' => $referenceMonth,
            ],
            $attributes,
        );
    }

    public function upsertCreditTransactionInstallment(array $match, array $attributes): CreditTransaction
    {
        return CreditTransaction::query()->updateOrCreate($match, $attributes);
    }

    public function getMonthlyCommittedInstallments(int $userId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = CreditTransaction::query()
            ->select(['billing_date', 'amount'])
            ->where('user_id', $userId)
            ->whereBetween('billing_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('billing_date')
            ->get();

        return $rows
            ->groupBy(static fn (CreditTransaction $tx): string => $tx->billing_date->format('Y-m'))
            ->map(static fn (Collection $items): float => round((float) $items->sum('amount'), 2));
    }
}
