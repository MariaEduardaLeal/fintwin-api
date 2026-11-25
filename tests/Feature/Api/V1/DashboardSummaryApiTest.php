<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BankAccountType;
use App\Enums\InvoiceStatus;
use App\Models\User;
use App\Repositories\Contracts\CreditCardRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }

    public function test_dashboard_summary_returns_synced_accounts_cards_and_totals(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $now = CarbonImmutable::now();
        $currentMonth = $now->format('Y-m');

        $transactions = app(TransactionRepositoryInterface::class);
        $transactions->upsertBankAccount($user->id, ['pluggy_account_id' => 'acc-santander-1'], [
            'pluggy_item_id' => 'item-1',
            'institution_name' => 'Santander',
            'name' => 'Conta Corrente',
            'type' => BankAccountType::Checking,
            'currency_code' => 'BRL',
            'balance' => 834.65,
            'last_synced_at' => $now,
        ]);

        $transactions->upsertBankAccount($user->id, ['pluggy_account_id' => 'acc-nubank-1'], [
            'pluggy_item_id' => 'item-2',
            'institution_name' => 'Nubank',
            'name' => 'NuConta',
            'type' => BankAccountType::Savings,
            'currency_code' => 'BRL',
            'balance' => 258.79,
            'last_synced_at' => $now,
        ]);

        $creditCards = app(CreditCardRepositoryInterface::class);
        $card = $creditCards->upsertCreditCard($user->id, ['pluggy_account_id' => 'card-1'], [
            'name' => 'Gold',
            'brand' => 'MASTERCARD',
            'last_four_digits' => '6911',
            'credit_limit' => 11570.00,
            'available_limit' => 7288.31,
            'closing_day' => 5,
            'due_day' => 12,
            'last_synced_at' => $now,
        ]);

        $invoice = $creditCards->upsertInvoice($user->id, $card->id, $currentMonth, [
            'pluggy_bill_id' => 'bill-current',
            'closing_date' => $now->startOfMonth()->addDays(4)->toDateString(),
            'due_date' => $now->startOfMonth()->addDays(11)->toDateString(),
            'total_amount' => 4281.69,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::Open,
        ]);

        $creditCards->upsertCreditTransactionInstallment([
            'user_id' => $user->id,
            'pluggy_transaction_id' => 'ctx-1',
            'installment_number' => 1,
        ], [
            'credit_card_id' => $card->id,
            'invoice_id' => $invoice->id,
            'description' => 'Compra Parcelada',
            'amount' => 500.00,
            'purchase_date' => $now->toDateString(),
            'billing_date' => $now->toDateString(),
            'total_installments' => 3,
        ]);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonPath('data.total_liquid_balance', 1093.44)
            ->assertJsonPath('data.total_credit_limit', 11570)
            ->assertJsonPath('data.total_credit_used', 4281.69)
            ->assertJsonPath('data.current_month_invoices_total', 4281.69)
            ->assertJsonPath('data.current_month_installments', 500)
            ->assertJsonPath('data.accounts_count', 2)
            ->assertJsonPath('data.credit_cards_count', 1)
            ->assertJsonCount(2, 'data.bank_accounts')
            ->assertJsonCount(1, 'data.credit_cards')
            ->assertJsonPath('data.credit_cards.0.last_four_digits', '6911');
    }
}
