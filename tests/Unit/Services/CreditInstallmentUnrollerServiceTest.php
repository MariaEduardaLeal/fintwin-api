<?php

namespace Tests\Unit\Services;

use App\Enums\InvoiceStatus;
use App\Models\CreditCard;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CreditInstallmentUnrollerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditInstallmentUnrollerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrolls_single_installment_purchase_without_future_projection(): void
    {
        $user = User::factory()->create();
        $card = $this->createCard($user);
        $openInvoice = Invoice::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $card->id,
            'reference_month' => '2026-11',
            'closing_date' => '2026-11-05',
            'due_date' => '2026-11-12',
            'total_amount' => 150.00,
            'status' => InvoiceStatus::Open,
        ]);

        $unroller = app(CreditInstallmentUnrollerService::class);

        $rows = $unroller->unrollAndPersist($user->id, $card, [
            'id' => 'tx-single-1',
            'description' => 'Padaria Central',
            'amount' => -45.90,
            'date' => '2026-11-02T10:00:00Z',
        ], $openInvoice);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows->first()->is_projected);
        $this->assertSame(1, $rows->first()->installment_number);
        $this->assertSame(1, $rows->first()->total_installments);
        $this->assertSame('2026-11-12', $rows->first()->billing_date->toDateString());
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_unrolls_mid_series_installments_safely_and_recalculates_future_invoices(): void
    {
        $user = User::factory()->create();
        $card = $this->createCard($user);
        $openInvoice = Invoice::query()->create([
            'user_id' => $user->id,
            'credit_card_id' => $card->id,
            'reference_month' => '2026-11',
            'closing_date' => '2026-11-24',
            'due_date' => '2026-11-30',
            'total_amount' => 500.00,
            'status' => InvoiceStatus::Open,
        ]);

        $unroller = app(CreditInstallmentUnrollerService::class);

        $txPayload = [
            'id' => 'tx-notebook-3-6',
            'description' => 'Notebook Pro 3/6',
            'amount' => 500.00,
            'date' => '2026-09-15T12:00:00Z',
            'creditCardMetadata' => [
                'installmentNumber' => 3,
                'totalInstallments' => 6,
            ],
        ];

        $rows = $unroller->unrollAndPersist($user->id, $card, $txPayload, $openInvoice);
        // Re-running must remain completely idempotent.
        $unroller->unrollAndPersist($user->id, $card, $txPayload, $openInvoice);

        $this->assertCount(4, $rows);
        $this->assertDatabaseCount('credit_transactions', 4);

        $persisted = CreditTransaction::query()
            ->where('credit_card_id', $card->id)
            ->orderBy('installment_number')
            ->get();

        $this->assertSame([3, 4, 5, 6], $persisted->pluck('installment_number')->all());
        $this->assertSame([false, true, true, true], $persisted->pluck('is_projected')->all());
        // Day 30 -> Dec 30 -> Jan 30 -> Feb 28 (no month overflow into March).
        $this->assertSame(
            ['2026-11-30', '2026-12-30', '2027-01-30', '2027-02-28'],
            $persisted->map(static fn (CreditTransaction $tx): string => $tx->billing_date->toDateString())->all(),
        );

        // Open invoice status and amount from Pluggy must not be overwritten.
        $openInvoice->refresh();
        $this->assertSame(InvoiceStatus::Open, $openInvoice->status);
        $this->assertSame('500.00', $openInvoice->total_amount);

        // Future invoices created for Dec, Jan, Feb must have recalculated totals = 500.00 each.
        $febInvoice = Invoice::query()
            ->where('credit_card_id', $card->id)
            ->where('reference_month', '2027-02')
            ->first();

        $this->assertNotNull($febInvoice);
        $this->assertSame(InvoiceStatus::Future, $febInvoice->status);
        $this->assertSame('500.00', $febInvoice->total_amount);
    }

    private function createCard(User $user): CreditCard
    {
        return CreditCard::query()->create([
            'user_id' => $user->id,
            'pluggy_account_id' => 'card-acc-1',
            'name' => 'Cartão Principal',
            'closing_day' => 24,
            'due_day' => 30,
            'credit_limit' => 10000.00,
            'available_limit' => 8000.00,
        ]);
    }
}
