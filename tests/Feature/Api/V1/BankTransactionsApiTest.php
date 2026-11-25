<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BankAccountType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankTransactionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_bank_transaction_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->getJson('/api/v1/bank-accounts/1/transactions')->assertUnauthorized();
        $this->patchJson('/api/v1/transactions/1', ['notes' => 'Almoço'])->assertUnauthorized();
    }

    public function test_categories_endpoint_returns_seeded_spending_types(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('alimentacao', $slugs);
        $this->assertContains('lazer', $slugs);
    }

    public function test_list_account_transactions_filters_and_enforces_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $repo = app(TransactionRepositoryInterface::class);
        $account = $repo->upsertBankAccount($owner->id, ['pluggy_account_id' => 'acc-owner-1'], [
            'pluggy_item_id' => 'item-1',
            'institution_name' => 'Santander',
            'name' => 'Conta Corrente',
            'type' => BankAccountType::Checking,
            'balance' => 834.65,
        ]);

        $foodCategory = Category::query()->where('slug', 'alimentacao')->firstOrFail();
        $leisureCategory = Category::query()->where('slug', 'lazer')->firstOrFail();

        $txFood = $repo->upsertTransaction($owner->id, ['pluggy_transaction_id' => 'tx-1'], [
            'bank_account_id' => $account->id,
            'category_id' => $foodCategory->id,
            'description' => 'Padaria Central',
            'amount' => 35.00,
            'type' => TransactionType::Expense,
            'transaction_date' => '2026-11-20',
            'status' => TransactionStatus::Posted,
        ]);
        $txFood->update(['notes' => 'Café da manhã com cliente']);

        $repo->upsertTransaction($owner->id, ['pluggy_transaction_id' => 'tx-2'], [
            'bank_account_id' => $account->id,
            'category_id' => $leisureCategory->id,
            'description' => 'Cinema Shopping',
            'amount' => 54.00,
            'type' => TransactionType::Expense,
            'transaction_date' => '2026-11-21',
            'status' => TransactionStatus::Posted,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/bank-accounts/{$account->id}/transactions?search=cliente")
            ->assertOk()
            ->assertJsonPath('data.bank_account.institution_name', 'Santander')
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.description', 'Padaria Central')
            ->assertJsonPath('data.transactions.0.notes', 'Café da manhã com cliente');

        $this->getJson("/api/v1/bank-accounts/{$account->id}/transactions?category_id={$leisureCategory->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.description', 'Cinema Shopping');

        Sanctum::actingAs($other);

        $this->getJson("/api/v1/bank-accounts/{$account->id}/transactions")->assertNotFound();
    }

    public function test_update_transaction_saves_reason_and_preserves_manual_category_across_resync(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $repo = app(TransactionRepositoryInterface::class);
        $account = $repo->upsertBankAccount($owner->id, ['pluggy_account_id' => 'acc-owner-2'], [
            'pluggy_item_id' => 'item-2',
            'institution_name' => 'Inter',
            'name' => 'Conta Digital',
            'type' => BankAccountType::Checking,
            'balance' => 54.49,
        ]);

        $autoCategory = Category::query()->where('slug', 'outros')->firstOrFail();
        $manualCategory = Category::query()->where('slug', 'educacao')->firstOrFail();

        $tx = $repo->upsertTransaction($owner->id, ['pluggy_transaction_id' => 'tx-pix-edu'], [
            'bank_account_id' => $account->id,
            'category_id' => $autoCategory->id,
            'description' => 'Pix enviado',
            'amount' => 320.00,
            'type' => TransactionType::Expense,
            'transaction_date' => '2026-11-22',
            'status' => TransactionStatus::Posted,
        ]);

        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/transactions/{$tx->id}", [
            'notes' => 'Tentativa indevida',
        ])->assertNotFound();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/transactions/{$tx->id}", [
            'notes' => 'Curso de inglês mensal',
            'category_id' => $manualCategory->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Curso de inglês mensal')
            ->assertJsonPath('data.is_category_manual', true)
            ->assertJsonPath('data.category.slug', 'educacao');

        // Re-syncing via Pluggy must keep the user's manual category and reason.
        $resynced = $repo->upsertTransaction($owner->id, ['pluggy_transaction_id' => 'tx-pix-edu'], [
            'bank_account_id' => $account->id,
            'category_id' => $autoCategory->id,
            'description' => 'Pix enviado',
            'amount' => 320.00,
            'type' => TransactionType::Expense,
            'transaction_date' => '2026-11-22',
            'status' => TransactionStatus::Posted,
        ]);

        $this->assertSame($manualCategory->id, $resynced->category_id);
        $this->assertTrue($resynced->is_category_manual);
        $this->assertSame('Curso de inglês mensal', $resynced->notes);
    }
}
