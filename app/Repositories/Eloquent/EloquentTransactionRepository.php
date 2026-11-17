<?php

namespace App\Repositories\Eloquent;

use App\Enums\BankAccountType;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function getUserAccounts(int $userId): Collection
    {
        return BankAccount::query()
            ->where('user_id', $userId)
            ->orderBy('institution_name')
            ->get();
    }

    public function getTotalLiquidBalance(int $userId): float
    {
        return (float) BankAccount::query()
            ->where('user_id', $userId)
            ->whereIn('type', [BankAccountType::Checking->value, BankAccountType::Savings->value])
            ->sum('balance');
    }

    public function upsertBankAccount(int $userId, array $match, array $attributes): BankAccount
    {
        return BankAccount::query()->updateOrCreate(
            array_merge($match, ['user_id' => $userId]),
            $attributes,
        );
    }

    public function upsertTransaction(int $userId, array $match, array $attributes): Transaction
    {
        return Transaction::query()->updateOrCreate(
            array_merge($match, ['user_id' => $userId]),
            $attributes,
        );
    }

    public function getTransactionsInPeriod(int $userId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Transaction::query()
            ->with(['category', 'bankAccount'])
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    public function getMonthlyCashFlowSummary(int $userId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = Transaction::query()
            ->select(['transaction_date', 'type', 'amount'])
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('transaction_date')
            ->get();

        return $rows
            ->groupBy(static fn (Transaction $tx): string => $tx->transaction_date->format('Y-m'))
            ->map(static function (Collection $monthTransactions): array {
                $income = (float) $monthTransactions
                    ->filter(static fn (Transaction $tx): bool => $tx->type === TransactionType::Income)
                    ->sum('amount');

                $expense = (float) $monthTransactions
                    ->filter(static fn (Transaction $tx): bool => $tx->type === TransactionType::Expense)
                    ->sum('amount');

                return [
                    'income' => round($income, 2),
                    'expense' => round($expense, 2),
                    'net' => round($income - $expense, 2),
                ];
            });
    }
}
