<?php

namespace Tests\Feature\Jobs;

use App\Enums\BankAccountType;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Integrations\Pluggy\PluggyClient;
use App\Jobs\SyncPluggyBankTransactionsJob;
use App\Jobs\SyncPluggyCreditInvoicesJob;
use App\Models\Category;
use App\Models\User;
use App\Services\SimulationCacheInvalidator;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncPluggyJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_bank_transactions_job_syncs_accounts_paginated_transactions_and_invalidates_cache(): void
    {
        $user = User::factory()->create();
        $invalidator = app(SimulationCacheInvalidator::class);
        $initialVersion = $invalidator->currentVersion($user->id);

        $pluggy = Mockery::mock(PluggyClient::class);
        $pluggy->shouldReceive('getAccounts')
            ->once()
            ->with('item-bank-1')
            ->andReturn([
                'results' => [
                    [
                        'id' => 'acc-checking-1',
                        'type' => 'BANK',
                        'subtype' => 'CHECKING_ACCOUNT',
                        'name' => 'NuConta',
                        'number' => '12345-6',
                        'currencyCode' => 'BRL',
                        'balance' => 4200.75,
                        'bankData' => ['institutionName' => 'Nubank'],
                    ],
                    [
                        'id' => 'acc-card-ignored',
                        'type' => 'CREDIT',
                    ],
                ],
            ]);

        $pluggy->shouldReceive('getTransactions')
            ->once()
            ->with('acc-checking-1', ['page' => 1, 'pageSize' => 200])
            ->andReturn([
                'totalPages' => 2,
                'results' => [
                    [
                        'id' => 'tx-salary',
                        'description' => 'Salário Mensal',
                        'amount' => 8000.00,
                        'type' => 'CREDIT',
                        'date' => '2026-11-05T09:00:00Z',
                        'status' => 'POSTED',
                    ],
                ],
            ]);

        $pluggy->shouldReceive('getTransactions')
            ->once()
            ->with('acc-checking-1', ['page' => 2, 'pageSize' => 200])
            ->andReturn([
                'totalPages' => 2,
                'results' => [
                    [
                        'id' => 'tx-ifood',
                        'description' => 'iFood Restaurante',
                        'amount' => -68.50,
                        'type' => 'DEBIT',
                        'date' => '2026-11-06T20:00:00Z',
                        'status' => 'POSTED',
                    ],
                ],
            ]);

        $this->app->instance(PluggyClient::class, $pluggy);

        SyncPluggyBankTransactionsJob::dispatchSync($user->id, 'item-bank-1');

        $this->assertDatabaseHas('bank_accounts', [
            'user_id' => $user->id,
            'pluggy_account_id' => 'acc-checking-1',
            'type' => BankAccountType::Checking->value,
            'balance' => 4200.75,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'pluggy_transaction_id' => 'tx-ifood',
            'amount' => 68.50,
            'type' => TransactionType::Expense->value,
            'category_id' => Category::where('slug', 'alimentacao')->value('id'),
        ]);

        $this->assertGreaterThan($initialVersion, $invalidator->currentVersion($user->id));
    }

    public function test_credit_invoices_job_syncs_cards_bills_and_unrolls_future_installments(): void
    {
        $user = User::factory()->create();
        $invalidator = app(SimulationCacheInvalidator::class);
        $initialVersion = $invalidator->currentVersion($user->id);

        $pluggy = Mockery::mock(PluggyClient::class);
        $pluggy->shouldReceive('getAccounts')
            ->once()
            ->with('item-credit-1')
            ->andReturn([
                'results' => [
                    [
                        'id' => 'card-acc-9',
                        'type' => 'CREDIT',
                        'name' => 'Ultravioleta',
                        'number' => '**** **** **** 9876',
                        'creditData' => [
                            'brand' => 'Mastercard',
                            'creditLimit' => 20000.00,
                            'availableCreditLimit' => 17000.00,
                            'balanceCloseDate' => '2026-11-05T00:00:00Z',
                            'balanceDueDate' => '2026-11-12T00:00:00Z',
                        ],
                    ],
                ],
            ]);

        $pluggy->shouldReceive('getCreditCardBills')
            ->once()
            ->with('card-acc-9')
            ->andReturn([
                'results' => [
                    [
                        'id' => 'bill-nov-2026',
                        'closingDate' => '2026-11-05T00:00:00Z',
                        'dueDate' => '2026-11-12T00:00:00Z',
                        'totalAmount' => 1200.00,
                        'minimumPaymentAmount' => 0,
                        'state' => 'OPEN',
                    ],
                ],
            ]);

        $pluggy->shouldReceive('getTransactions')
            ->once()
            ->with('card-acc-9', ['page' => 1, 'pageSize' => 200])
            ->andReturn([
                'totalPages' => 1,
                'results' => [
                    [
                        'id' => 'ctx-tv-1',
                        'description' => 'Smart TV Amazon 1/3',
                        'amount' => 1000.00,
                        'type' => 'DEBIT',
                        'date' => '2026-11-02T15:00:00Z',
                        'creditCardMetadata' => [
                            'billId' => 'bill-nov-2026',
                            'installmentNumber' => 1,
                            'totalInstallments' => 3,
                        ],
                    ],
                    [
                        'id' => 'ctx-payment-ignored',
                        'description' => 'Pagamento Fatura',
                        'amount' => -1000.00,
                        'type' => 'CREDIT',
                        'date' => '2026-11-01T10:00:00Z',
                    ],
                ],
            ]);

        $this->app->instance(PluggyClient::class, $pluggy);

        SyncPluggyCreditInvoicesJob::dispatchSync($user->id, 'item-credit-1');

        $this->assertDatabaseHas('credit_cards', [
            'user_id' => $user->id,
            'pluggy_account_id' => 'card-acc-9',
            'last_four_digits' => '9876',
        ]);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'reference_month' => '2026-11',
            'status' => InvoiceStatus::Open->value,
        ]);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'reference_month' => '2027-01',
            'status' => InvoiceStatus::Future->value,
            'total_amount' => 1000.00,
        ]);

        $this->assertDatabaseCount('credit_transactions', 3);
        $this->assertDatabaseMissing('credit_transactions', ['pluggy_transaction_id' => 'ctx-payment-ignored']);
        $this->assertGreaterThan($initialVersion, $invalidator->currentVersion($user->id));
    }
}
