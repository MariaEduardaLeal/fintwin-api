<?php

namespace App\Jobs;

use App\Enums\BankAccountType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Integrations\Pluggy\PluggyClient;
use App\Integrations\Pluggy\PluggyTransactionPaginator;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\SimulationCacheInvalidator;
use App\Services\TransactionCategorizerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPluggyBankTransactionsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $userId,
        public readonly string $pluggyItemId,
        public readonly ?string $fromDate = null,
    ) {
        $this->onQueue('open-finance');
    }

    public function uniqueId(): string
    {
        return "pluggy-bank:{$this->userId}:{$this->pluggyItemId}";
    }

    public function handle(
        PluggyClient $pluggy,
        PluggyTransactionPaginator $paginator,
        TransactionRepositoryInterface $transactions,
        TransactionCategorizerService $categorizer,
        SimulationCacheInvalidator $cacheInvalidator,
    ): void {
        $accountsResponse = $pluggy->getAccounts($this->pluggyItemId);
        $accounts = is_array($accountsResponse['results'] ?? null) ? $accountsResponse['results'] : [];
        $syncedAny = false;

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            if (strtoupper((string) ($account['type'] ?? '')) !== 'BANK') {
                continue;
            }

            $pluggyAccountId = (string) ($account['id'] ?? '');

            if ($pluggyAccountId === '') {
                continue;
            }

            $bankAccount = $transactions->upsertBankAccount(
                userId: $this->userId,
                match: ['pluggy_account_id' => $pluggyAccountId],
                attributes: [
                    'pluggy_item_id' => $this->pluggyItemId,
                    'institution_name' => (string) ($account['bankData']['institutionName'] ?? $account['institution']['name'] ?? 'Instituição Open Finance'),
                    'name' => (string) ($account['name'] ?? 'Conta Bancária'),
                    'type' => $this->resolveAccountType((string) ($account['subtype'] ?? '')),
                    'subtype' => isset($account['subtype']) ? (string) $account['subtype'] : null,
                    'number' => isset($account['number']) ? (string) $account['number'] : null,
                    'currency_code' => strtoupper((string) ($account['currencyCode'] ?? 'BRL')),
                    'balance' => round((float) ($account['balance'] ?? 0), 2),
                    'last_synced_at' => now(),
                ],
            );

            foreach ($paginator->iterate($pluggy, $pluggyAccountId, $this->fromDate) as $tx) {
                $pluggyTxId = (string) ($tx['id'] ?? '');

                if ($pluggyTxId === '') {
                    continue;
                }

                $txType = $this->resolveTransactionType($tx);
                $description = trim((string) ($tx['description'] ?? 'Movimentação bancária'));
                $pluggyCategory = isset($tx['category']) && is_string($tx['category']) ? $tx['category'] : null;

                $categoryId = $categorizer->categorize(
                    description: $description,
                    type: $txType,
                    userId: $this->userId,
                    pluggyCategory: $pluggyCategory,
                );

                $txDate = isset($tx['date']) && is_string($tx['date'])
                    ? CarbonImmutable::parse($tx['date'])->toDateString()
                    : now()->toDateString();

                $status = strtoupper((string) ($tx['status'] ?? 'POSTED')) === 'PENDING'
                    ? TransactionStatus::Pending
                    : TransactionStatus::Posted;

                $transactions->upsertTransaction(
                    userId: $this->userId,
                    match: ['pluggy_transaction_id' => $pluggyTxId],
                    attributes: [
                        'bank_account_id' => $bankAccount->id,
                        'category_id' => $categoryId,
                        'description' => $description,
                        'amount' => round(abs((float) ($tx['amount'] ?? 0)), 2),
                        'type' => $txType,
                        'transaction_date' => $txDate,
                        'status' => $status,
                        'payment_method' => isset($tx['paymentData']['paymentMethod']) ? (string) $tx['paymentData']['paymentMethod'] : null,
                        'metadata' => [
                            'pluggy_category' => $pluggyCategory,
                            'operation_type' => $tx['operationType'] ?? null,
                        ],
                    ],
                );
            }

            $syncedAny = true;
        }

        if ($syncedAny) {
            $cacheInvalidator->invalidateForUser($this->userId);
        }
    }

    private function resolveAccountType(string $subtype): BankAccountType
    {
        return strtoupper($subtype) === 'SAVINGS_ACCOUNT'
            ? BankAccountType::Savings
            : BankAccountType::Checking;
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    private function resolveTransactionType(array $tx): TransactionType
    {
        $rawType = strtoupper((string) ($tx['type'] ?? ''));

        if ($rawType === 'CREDIT') {
            return TransactionType::Income;
        }

        if ($rawType === 'DEBIT') {
            return TransactionType::Expense;
        }

        return ((float) ($tx['amount'] ?? 0)) >= 0
            ? TransactionType::Income
            : TransactionType::Expense;
    }
}
