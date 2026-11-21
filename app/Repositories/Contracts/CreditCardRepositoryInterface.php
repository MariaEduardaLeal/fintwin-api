<?php

namespace App\Repositories\Contracts;

use App\Models\CreditCard;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CreditCardRepositoryInterface
{
    /**
     * @return Collection<int, CreditCard>
     */
    public function getUserCardsWithInvoices(int $userId): Collection;

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function upsertCreditCard(int $userId, array $match, array $attributes): CreditCard;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertInvoice(int $userId, int $creditCardId, string $referenceMonth, array $attributes): Invoice;

    public function findOrCreateFutureInvoice(
        int $userId,
        int $creditCardId,
        string $referenceMonth,
        CarbonInterface $closingDate,
        CarbonInterface $dueDate,
    ): Invoice;

    public function recalculateFutureInvoicesTotal(int $creditCardId): void;

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function upsertCreditTransactionInstallment(array $match, array $attributes): CreditTransaction;

    /**
     * Sum future or current installment commitments grouped by billing month (YYYY-MM).
     *
     * @return Collection<string, float>
     */
    public function getMonthlyCommittedInstallments(int $userId, CarbonInterface $from, CarbonInterface $to): Collection;
}
