<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Integrations\Pluggy\PluggyClient;
use App\Integrations\Pluggy\PluggyTransactionPaginator;
use App\Models\Invoice;
use App\Repositories\Contracts\CreditCardRepositoryInterface;
use App\Services\CreditInstallmentUnrollerService;
use App\Services\SimulationCacheInvalidator;
use App\Services\TransactionCategorizerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPluggyCreditInvoicesJob implements ShouldBeUnique, ShouldQueue
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
        return "pluggy-credit:{$this->userId}:{$this->pluggyItemId}";
    }

    public function handle(
        PluggyClient $pluggy,
        PluggyTransactionPaginator $paginator,
        CreditCardRepositoryInterface $creditCards,
        TransactionCategorizerService $categorizer,
        CreditInstallmentUnrollerService $unroller,
        SimulationCacheInvalidator $cacheInvalidator,
    ): void {
        $accountsResponse = $pluggy->getAccounts($this->pluggyItemId);
        $accounts = is_array($accountsResponse['results'] ?? null) ? $accountsResponse['results'] : [];
        $syncedAny = false;

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            if (strtoupper((string) ($account['type'] ?? '')) !== 'CREDIT') {
                continue;
            }

            $pluggyAccountId = (string) ($account['id'] ?? '');

            if ($pluggyAccountId === '') {
                continue;
            }

            $creditData = is_array($account['creditData'] ?? null) ? $account['creditData'] : [];
            $closingDate = $this->parseDate($creditData['balanceCloseDate'] ?? null);
            $dueDate = $this->parseDate($creditData['balanceDueDate'] ?? null);

            $card = $creditCards->upsertCreditCard(
                userId: $this->userId,
                match: ['pluggy_account_id' => $pluggyAccountId],
                attributes: [
                    'name' => (string) ($account['name'] ?? 'Cartão de Crédito'),
                    'brand' => isset($creditData['brand']) ? (string) $creditData['brand'] : null,
                    'last_four_digits' => $this->extractLastFour((string) ($account['number'] ?? '')),
                    'credit_limit' => round((float) ($creditData['creditLimit'] ?? 0), 2),
                    'available_limit' => round((float) ($creditData['availableCreditLimit'] ?? 0), 2),
                    'closing_day' => $closingDate?->day ?? 5,
                    'due_day' => $dueDate?->day ?? 12,
                    'last_synced_at' => now(),
                ],
            );

            $billsById = [];
            $billsByMonth = [];

            $billsResponse = $pluggy->getCreditCardBills($pluggyAccountId);
            $bills = is_array($billsResponse['results'] ?? null) ? $billsResponse['results'] : [];

            foreach ($bills as $bill) {
                if (! is_array($bill)) {
                    continue;
                }

                $billDueDate = $this->parseDate($bill['dueDate'] ?? null) ?? CarbonImmutable::now();
                $billClosingDate = $this->parseDate($bill['closingDate'] ?? null) ?? $billDueDate->subDays(7);
                $referenceMonth = $billDueDate->format('Y-m');
                $totalAmount = round((float) ($bill['totalAmount'] ?? 0), 2);
                $paidAmount = round((float) ($bill['minimumPaymentAmount'] ?? 0), 2);

                $invoice = $creditCards->upsertInvoice(
                    userId: $this->userId,
                    creditCardId: $card->id,
                    referenceMonth: $referenceMonth,
                    attributes: [
                        'pluggy_bill_id' => isset($bill['id']) ? (string) $bill['id'] : null,
                        'closing_date' => $billClosingDate->toDateString(),
                        'due_date' => $billDueDate->toDateString(),
                        'total_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'status' => $this->resolveInvoiceStatus($bill, $billDueDate),
                    ],
                );

                if ($invoice->pluggy_bill_id !== null) {
                    $billsById[$invoice->pluggy_bill_id] = $invoice;
                }

                $billsByMonth[$referenceMonth] = $invoice;
            }

            foreach ($paginator->iterate($pluggy, $pluggyAccountId, $this->fromDate) as $tx) {
                $pluggyTxId = (string) ($tx['id'] ?? '');

                if ($pluggyTxId === '') {
                    continue;
                }

                // Skip invoice payment credits so they are not duplicated as purchases.
                if (strtoupper((string) ($tx['type'] ?? '')) === 'CREDIT') {
                    continue;
                }

                $description = trim((string) ($tx['description'] ?? 'Compra no cartão'));
                $pluggyCategory = isset($tx['category']) && is_string($tx['category']) ? $tx['category'] : null;

                $categoryId = $categorizer->categorize(
                    description: $description,
                    type: TransactionType::Expense,
                    userId: $this->userId,
                    pluggyCategory: $pluggyCategory,
                );

                $matchedInvoice = $this->matchInvoiceForTransaction($tx, $billsById, $billsByMonth);

                $unroller->unrollAndPersist(
                    userId: $this->userId,
                    card: $card,
                    pluggyTransaction: $tx,
                    currentInvoice: $matchedInvoice,
                    categoryId: $categoryId,
                );
            }

            $syncedAny = true;
        }

        if ($syncedAny) {
            $cacheInvalidator->invalidateForUser($this->userId);
        }
    }

    /**
     * @param  array<string, mixed>  $tx
     * @param  array<string, Invoice>  $billsById
     * @param  array<string, Invoice>  $billsByMonth
     */
    private function matchInvoiceForTransaction(array $tx, array $billsById, array $billsByMonth): ?Invoice
    {
        $billId = $tx['creditCardMetadata']['billId'] ?? null;

        if (is_string($billId) && isset($billsById[$billId])) {
            return $billsById[$billId];
        }

        $txDate = $this->parseDate($tx['date'] ?? null);

        if ($txDate !== null && isset($billsByMonth[$txDate->format('Y-m')])) {
            return $billsByMonth[$txDate->format('Y-m')];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    private function resolveInvoiceStatus(array $bill, CarbonImmutable $dueDate): InvoiceStatus
    {
        $rawState = strtoupper((string) ($bill['state'] ?? ''));

        return match ($rawState) {
            'PAID' => InvoiceStatus::Paid,
            'CLOSED' => InvoiceStatus::Closed,
            'OVERDUE' => InvoiceStatus::Overdue,
            'OPEN' => InvoiceStatus::Open,
            default => $dueDate->isFuture() ? InvoiceStatus::Open : InvoiceStatus::Closed,
        };
    }

    private function extractLastFour(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if ($digits === '') {
            return null;
        }

        return substr($digits, -4);
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
