<?php

namespace Tests\Feature\Repositories;

use App\Enums\AssetClass;
use App\Enums\BankAccountType;
use App\Enums\DividendStatus;
use App\Enums\DividendType;
use App\Enums\InvoiceStatus;
use App\Enums\RiskProfile;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Portfolio;
use App\Models\User;
use App\Repositories\Contracts\CreditCardRepositoryInterface;
use App\Repositories\Contracts\DividendScheduleRepositoryInterface;
use App\Repositories\Contracts\PortfolioRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_repository_upserts_and_loads_financial_profile(): void
    {
        $user = User::factory()->create();
        $repo = app(UserRepositoryInterface::class);

        $repo->upsertFinancialProfile($user->id, [
            'monthly_income' => 12500.00,
            'monthly_fixed_expenses' => 4500.00,
            'emergency_fund_target' => 30000.00,
            'risk_profile' => RiskProfile::Moderate,
            'investment_horizon_months' => 48,
            'ai_strictness_level' => 'balanced',
            'financial_goals' => ['reserva', 'aposentadoria'],
        ]);

        $loaded = $repo->findWithFinancialProfile($user->id);

        $this->assertNotNull($loaded);
        $this->assertNotNull($loaded->financialProfile);
        $this->assertSame('12500.00', $loaded->financialProfile->monthly_income);
        $this->assertSame(RiskProfile::Moderate, $loaded->financialProfile->risk_profile);
        $this->assertSame(['reserva', 'aposentadoria'], $loaded->financialProfile->financial_goals);
    }

    public function test_transaction_repository_computes_liquid_balance_and_monthly_cash_flow(): void
    {
        $user = User::factory()->create();
        $repo = app(TransactionRepositoryInterface::class);

        $checking = $repo->upsertBankAccount($user->id, ['pluggy_account_id' => 'acc-chk-1'], [
            'institution_name' => 'Nubank',
            'name' => 'Conta Corrente',
            'type' => BankAccountType::Checking,
            'balance' => 3500.50,
        ]);

        $repo->upsertBankAccount($user->id, ['pluggy_account_id' => 'acc-sav-1'], [
            'institution_name' => 'Itau',
            'name' => 'Poupanca',
            'type' => BankAccountType::Savings,
            'balance' => 1499.50,
        ]);

        $repo->upsertBankAccount($user->id, ['pluggy_account_id' => 'acc-inv-1'], [
            'institution_name' => 'XP',
            'name' => 'Corretora',
            'type' => BankAccountType::Investment,
            'balance' => 50000.00,
        ]);

        $repo->upsertTransaction($user->id, ['pluggy_transaction_id' => 'tx-1'], [
            'bank_account_id' => $checking->id,
            'description' => 'Salario',
            'amount' => 10000.00,
            'type' => TransactionType::Income,
            'transaction_date' => '2026-11-05',
            'status' => TransactionStatus::Posted,
        ]);

        $repo->upsertTransaction($user->id, ['pluggy_transaction_id' => 'tx-2'], [
            'bank_account_id' => $checking->id,
            'description' => 'Aluguel',
            'amount' => 3200.00,
            'type' => TransactionType::Expense,
            'transaction_date' => '2026-11-10',
            'status' => TransactionStatus::Posted,
        ]);

        $this->assertSame(5000.0, $repo->getTotalLiquidBalance($user->id));

        $summary = $repo->getMonthlyCashFlowSummary(
            $user->id,
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-30'),
        );

        $this->assertSame(['income' => 10000.0, 'expense' => 3200.0, 'net' => 6800.0], $summary->get('2026-11'));
    }

    public function test_credit_card_repository_tracks_unrolled_future_installments(): void
    {
        $user = User::factory()->create();
        $repo = app(CreditCardRepositoryInterface::class);

        $card = $repo->upsertCreditCard($user->id, ['pluggy_account_id' => 'card-1'], [
            'name' => 'Nubank Ultravioleta',
            'brand' => 'Mastercard',
            'last_four_digits' => '4242',
            'credit_limit' => 15000.00,
            'available_limit' => 12000.00,
            'closing_day' => 5,
            'due_day' => 12,
        ]);

        $novInvoice = $repo->upsertInvoice($user->id, $card->id, '2026-11', [
            'closing_date' => '2026-11-05',
            'due_date' => '2026-11-12',
            'total_amount' => 1000.00,
            'status' => InvoiceStatus::Open,
        ]);

        foreach ([1 => '2026-11-12', 2 => '2026-12-12', 3 => '2027-01-12'] as $installment => $billingDate) {
            $repo->upsertCreditTransactionInstallment(
                [
                    'credit_card_id' => $card->id,
                    'pluggy_transaction_id' => 'pluggy-purchase-99',
                    'installment_number' => $installment,
                ],
                [
                    'user_id' => $user->id,
                    'invoice_id' => $installment === 1 ? $novInvoice->id : null,
                    'installment_group_id' => 'group-99',
                    'description' => "Notebook ({$installment}/3)",
                    'amount' => 1000.00,
                    'total_purchase_amount' => 3000.00,
                    'total_installments' => 3,
                    'purchase_date' => '2026-11-01',
                    'billing_date' => $billingDate,
                    'is_projected' => $installment > 1,
                ],
            );
        }

        $commitments = $repo->getMonthlyCommittedInstallments(
            $user->id,
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2027-02-28'),
        );

        $this->assertSame(1000.0, $commitments->get('2026-11'));
        $this->assertSame(1000.0, $commitments->get('2026-12'));
        $this->assertSame(1000.0, $commitments->get('2027-01'));
        $this->assertNull($commitments->get('2027-02'));
    }

    public function test_portfolio_and_dividend_repositories_return_upcoming_dividends(): void
    {
        $user = User::factory()->create();
        $portfolioRepo = app(PortfolioRepositoryInterface::class);
        $dividendRepo = app(DividendScheduleRepositoryInterface::class);

        $portfolio = Portfolio::query()->create([
            'user_id' => $user->id,
            'name' => 'Carteira Principal',
            'is_primary' => true,
        ]);

        $asset = $portfolioRepo->upsertAssetByTicker('mxrf11', [
            'name' => 'Maxi Renda FII',
            'asset_class' => AssetClass::Fii,
            'current_price' => 10.15,
        ]);

        $portfolioRepo->syncPortfolioAsset($portfolio->id, $asset->id, [
            'quantity' => 500,
            'average_price' => 9.80,
            'invested_amount' => 4900.00,
        ]);

        $dividendRepo->upsertDividend(
            $asset->id,
            [
                'type' => DividendType::Yield->value,
                'payment_date' => '2026-12-15',
                'amount_per_share' => 0.10,
            ],
            [
                'ex_date' => '2026-11-30',
                'status' => DividendStatus::Scheduled,
                'source' => 'brapi',
            ],
        );

        $this->assertSame(['MXRF11'], $portfolioRepo->getAllTrackedTickers());

        $upcoming = $dividendRepo->getUpcomingDividendsForUser(
            $user->id,
            CarbonImmutable::parse('2026-12-01'),
            CarbonImmutable::parse('2026-12-31'),
        );

        $this->assertCount(1, $upcoming);
        $this->assertSame('MXRF11', $upcoming->first()['ticker']);
        $this->assertSame(50.0, $upcoming->first()['estimated_total']);
    }

    public function test_category_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseCount('categories', 11);
    }
}
