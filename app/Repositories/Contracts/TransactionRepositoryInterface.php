<?php

namespace App\Repositories\Contracts;

use App\Models\BankAccount;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface TransactionRepositoryInterface
{
    /**
     * @return Collection<int, BankAccount>
     */
    public function getUserAccounts(int $userId): Collection;

    public function getTotalLiquidBalance(int $userId): float;

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function upsertBankAccount(int $userId, array $match, array $attributes): BankAccount;

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $attributes
     */
    public function upsertTransaction(int $userId, array $match, array $attributes): Transaction;

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactionsInPeriod(int $userId, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * @return Collection<string, array{income: float, expense: float, net: float}>
     */
    public function getMonthlyCashFlowSummary(int $userId, CarbonInterface $from, CarbonInterface $to): Collection;
}
