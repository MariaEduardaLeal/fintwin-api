<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Repositories\Contracts\CreditCardRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CreditInstallmentUnrollerService
{
    public function __construct(
        private readonly CreditCardRepositoryInterface $creditCards,
    ) {}

    /**
     * Unroll a credit card purchase into current and projected future monthly installments.
     *
     * @param  array<string, mixed>  $pluggyTransaction
     * @return Collection<int, CreditTransaction>
     */
    public function unrollAndPersist(
        int $userId,
        CreditCard $card,
        array $pluggyTransaction,
        ?Invoice $currentInvoice = null,
        ?int $categoryId = null,
    ): Collection {
        $pluggyTxId = (string) ($pluggyTransaction['id'] ?? '');
        $description = trim((string) ($pluggyTransaction['description'] ?? 'Compra no cartão'));
        $installmentAmount = round(abs((float) ($pluggyTransaction['amount'] ?? 0)), 2);

        $purchaseDate = $this->parseDate($pluggyTransaction['date'] ?? null) ?? CarbonImmutable::now();
        [$currentInstallment, $totalInstallments] = $this->resolveInstallmentPlan($pluggyTransaction, $description);

        $totalPurchaseAmount = round($installmentAmount * $totalInstallments, 2);
        $baseBillingDate = $this->resolveBaseBillingDate($card, $purchaseDate, $currentInvoice);
        $installmentGroupId = $this->deterministicGroupId($card->id, $pluggyTxId, $description, $purchaseDate);

        $persisted = collect();

        for ($installment = $currentInstallment; $installment <= $totalInstallments; $installment++) {
            $offsetMonths = $installment - $currentInstallment;
            $billingDate = $baseBillingDate->addMonthsNoOverflow($offsetMonths);
            $referenceMonth = $billingDate->format('Y-m');
            $isProjected = $offsetMonths > 0;

            $invoice = $isProjected
                ? $this->creditCards->findOrCreateFutureInvoice(
                    userId: $userId,
                    creditCardId: $card->id,
                    referenceMonth: $referenceMonth,
                    closingDate: $this->closingDateForBilling($card, $billingDate),
                    dueDate: $billingDate,
                )
                : $currentInvoice;

            $installmentDescription = $totalInstallments > 1
                ? sprintf('%s (%d/%d)', $this->stripInstallmentSuffix($description), $installment, $totalInstallments)
                : $description;

            $record = $this->creditCards->upsertCreditTransactionInstallment(
                [
                    'credit_card_id' => $card->id,
                    'pluggy_transaction_id' => $pluggyTxId !== '' ? $pluggyTxId : $installmentGroupId,
                    'installment_number' => $installment,
                ],
                [
                    'user_id' => $userId,
                    'invoice_id' => $invoice?->id,
                    'category_id' => $categoryId,
                    'installment_group_id' => $installmentGroupId,
                    'description' => $installmentDescription,
                    'amount' => $installmentAmount,
                    'total_purchase_amount' => $totalPurchaseAmount,
                    'total_installments' => $totalInstallments,
                    'purchase_date' => $purchaseDate->toDateString(),
                    'billing_date' => $billingDate->toDateString(),
                    'is_projected' => $isProjected,
                ],
            );

            $persisted->push($record);
        }

        if ($totalInstallments > $currentInstallment) {
            $this->creditCards->recalculateFutureInvoicesTotal($card->id);
        }

        return $persisted;
    }

    /**
     * @param  array<string, mixed>  $pluggyTransaction
     * @return array{0: int, 1: int}
     */
    private function resolveInstallmentPlan(array $pluggyTransaction, string $description): array
    {
        $metadata = is_array($pluggyTransaction['creditCardMetadata'] ?? null)
            ? $pluggyTransaction['creditCardMetadata']
            : [];

        $current = (int) ($metadata['installmentNumber'] ?? 0);
        $total = (int) ($metadata['totalInstallments'] ?? 0);

        if ($current >= 1 && $total >= $current && $total <= 72) {
            return [$current, $total];
        }

        if (preg_match('/\b(\d{1,2})\s*\/\s*(\d{1,2})\b/', $description, $matches) === 1) {
            $parsedCurrent = (int) $matches[1];
            $parsedTotal = (int) $matches[2];

            if ($parsedCurrent >= 1 && $parsedTotal >= $parsedCurrent && $parsedTotal <= 72) {
                return [$parsedCurrent, $parsedTotal];
            }
        }

        return [1, 1];
    }

    private function resolveBaseBillingDate(
        CreditCard $card,
        CarbonImmutable $purchaseDate,
        ?Invoice $currentInvoice,
    ): CarbonImmutable {
        if ($currentInvoice !== null) {
            return CarbonImmutable::parse($currentInvoice->due_date->toDateString());
        }

        $closingDay = max(1, min(28, (int) $card->closing_day));
        $dueDay = max(1, min(28, (int) $card->due_day));

        $monthAnchor = $purchaseDate->day > $closingDay
            ? $purchaseDate->startOfMonth()->addMonth()
            : $purchaseDate->startOfMonth();

        if ($dueDay < $closingDay) {
            $monthAnchor = $monthAnchor->addMonth();
        }

        return $monthAnchor->setDay($dueDay);
    }

    private function closingDateForBilling(CreditCard $card, CarbonImmutable $billingDate): CarbonImmutable
    {
        $closingDay = max(1, min(28, (int) $card->closing_day));
        $dueDay = max(1, min(28, (int) $card->due_day));

        $closingMonth = $dueDay < $closingDay
            ? $billingDate->startOfMonth()->subMonth()
            : $billingDate->startOfMonth();

        return $closingMonth->setDay($closingDay);
    }

    private function deterministicGroupId(
        int $cardId,
        string $pluggyTxId,
        string $description,
        CarbonImmutable $purchaseDate,
    ): string {
        $hash = md5("card:{$cardId}:tx:{$pluggyTxId}:{$description}:{$purchaseDate->toDateString()}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    private function stripInstallmentSuffix(string $description): string
    {
        return trim((string) preg_replace('/\s*\(?\b\d{1,2}\s*\/\s*\d{1,2}\)?\s*$/', '', $description));
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
