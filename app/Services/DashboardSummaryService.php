<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\Contracts\CreditCardRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Carbon\CarbonImmutable;

class DashboardSummaryService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly CreditCardRepositoryInterface $creditCards,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSummary(User $user): array
    {
        $now = CarbonImmutable::now();
        $currentMonth = $now->format('Y-m');

        $accounts = $this->transactions->getUserAccounts($user->id);
        $cards = $this->creditCards->getUserCardsWithInvoices($user->id);
        $totalLiquidBalance = round($this->transactions->getTotalLiquidBalance($user->id), 2);

        $installmentsByMonth = $this->creditCards->getMonthlyCommittedInstallments(
            userId: $user->id,
            from: $now->startOfMonth(),
            to: $now->endOfMonth(),
        );
        $currentMonthInstallments = round((float) ($installmentsByMonth->get($currentMonth, 0.0)), 2);

        $totalCreditLimit = 0.0;
        $totalCreditUsed = 0.0;
        $currentMonthInvoicesTotal = 0.0;
        $syncTimestamps = [];

        $mappedAccounts = $accounts->map(function (BankAccount $account) use (&$syncTimestamps): array {
            if ($account->last_synced_at !== null) {
                $syncTimestamps[] = $account->last_synced_at->getTimestamp();
            }

            return [
                'id' => $account->id,
                'institution_name' => $account->institution_name,
                'name' => $account->name,
                'type' => $account->type->value,
                'currency_code' => $account->currency_code,
                'balance' => round((float) $account->balance, 2),
                'last_synced_at' => $account->last_synced_at?->toIso8601String(),
            ];
        })->values()->all();

        $mappedCards = $cards->map(function (CreditCard $card) use (
            $currentMonth,
            &$totalCreditLimit,
            &$totalCreditUsed,
            &$currentMonthInvoicesTotal,
            &$syncTimestamps,
        ): array {
            if ($card->last_synced_at !== null) {
                $syncTimestamps[] = $card->last_synced_at->getTimestamp();
            }

            /** @var Invoice|null $currentInvoice */
            $currentInvoice = $card->invoices->firstWhere('reference_month', $currentMonth)
                ?? $card->invoices->last(static fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Open)
                ?? $card->invoices->last();

            $currentInvoiceAmount = round((float) ($currentInvoice?->total_amount ?? 0), 2);
            $creditLimit = round((float) $card->credit_limit, 2);
            $availableLimit = round((float) $card->available_limit, 2);

            $totalCreditLimit += $creditLimit;
            $totalCreditUsed += $creditLimit > 0
                ? max(0.0, round($creditLimit - $availableLimit, 2))
                : $currentInvoiceAmount;
            $currentMonthInvoicesTotal += $currentInvoiceAmount;

            return [
                'id' => $card->id,
                'name' => $card->name,
                'brand' => $card->brand,
                'last_four_digits' => $card->last_four_digits,
                'credit_limit' => $creditLimit,
                'available_limit' => $availableLimit,
                'current_invoice_amount' => $currentInvoiceAmount,
                'current_invoice_due_date' => $currentInvoice?->due_date?->toDateString(),
                'last_synced_at' => $card->last_synced_at?->toIso8601String(),
            ];
        })->values()->all();

        $lastSyncedAt = $syncTimestamps !== []
            ? CarbonImmutable::createFromTimestamp(max($syncTimestamps))->toIso8601String()
            : null;

        return [
            'total_liquid_balance' => $totalLiquidBalance,
            'total_credit_limit' => round($totalCreditLimit, 2),
            'total_credit_used' => round($totalCreditUsed, 2),
            'current_month_invoices_total' => round($currentMonthInvoicesTotal, 2),
            'current_month_installments' => $currentMonthInstallments,
            'accounts_count' => $accounts->count(),
            'credit_cards_count' => $cards->count(),
            'last_synced_at' => $lastSyncedAt,
            'bank_accounts' => $mappedAccounts,
            'credit_cards' => $mappedCards,
        ];
    }
}
