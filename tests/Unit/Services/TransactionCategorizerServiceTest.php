<?php

namespace Tests\Unit\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Services\TransactionCategorizerService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCategorizerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_categorizes_brazilian_merchants_and_income_by_regex_rules(): void
    {
        $categorizer = new TransactionCategorizerService;

        $ifoodId = $categorizer->categorize('IFOOD *IFOOD.COM BR', TransactionType::Expense);
        $uberId = $categorizer->categorize('UBER *TRIP HELP.UBER.COM', TransactionType::Expense);
        $rentId = $categorizer->categorize('Pagamento Aluguel Apto', TransactionType::Expense);
        $netflixId = $categorizer->categorize('NETFLIX ENTRETENIMENTO', TransactionType::Expense);
        $salaryId = $categorizer->categorize('Folha de Salário Mensal', TransactionType::Income);
        $dividendId = $categorizer->categorize('JCP PETR4', TransactionType::Income);
        $fallbackId = $categorizer->categorize('Estabelecimento Desconhecido XYZ', TransactionType::Expense);

        $this->assertSame(Category::where('slug', 'alimentacao')->value('id'), $ifoodId);
        $this->assertSame(Category::where('slug', 'transporte')->value('id'), $uberId);
        $this->assertSame(Category::where('slug', 'moradia')->value('id'), $rentId);
        $this->assertSame(Category::where('slug', 'lazer')->value('id'), $netflixId);
        $this->assertSame(Category::where('slug', 'salario')->value('id'), $salaryId);
        $this->assertSame(Category::where('slug', 'proventos')->value('id'), $dividendId);
        $this->assertSame(Category::where('slug', 'outros')->value('id'), $fallbackId);
    }
}
