<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Support\Str;

class TransactionCategorizerService
{
    /**
     * @var array<string, int>
     */
    private array $globalSlugMap = [];

    /**
     * @var array<int, array<string, int>>
     */
    private array $userSlugMaps = [];

    /**
     * @var array<string, string>
     */
    private const EXPENSE_PATTERNS = [
        'alimentacao' => '/\b(IFOOD|UBER\s*EATS|RAPPI|RESTAURANTE|PADARIA|SUPERMERCADO|MERCADO|CARREFOUR|ASSAI|ATACADAO|PAO DE ACUCAR|LANCHONETE|CAFE|PIZZARIA|BURGER)\b/',
        'transporte' => '/\b(UBER|99APP|99POP|POSTO|SHELL|IPIRANGA|PETROBRAS|COMBUSTIVEL|ESTACIONAMENTO|PEDAGIO|SEM PARAR|METRO|ONIBUS|PASSAGEM)\b/',
        'moradia' => '/\b(ALUGUEL|CONDOMINIO|ENEL|CEMIG|COPEL|SABESP|LIGHT|INTERNET|VIVO FIBRA|CLARO|TIM|IPTU|GAS|ENERGIA)\b/',
        'saude' => '/\b(DROGASIL|DROGARIA|FARMACIA|RAIA|PACHECO|UNIMED|AMIL|BRADESCO SAUDE|SULAMERICA|HOSPITAL|CLINICA|LABORATORIO|DENTISTA)\b/',
        'educacao' => '/\b(CURSO|FACULDADE|UNIVERSIDADE|ESCOLA|UDEMY|ALURA|LIVRARIA|AMAZON KINDLE|EDUCACAO|MENSALIDADE)\b/',
        'lazer' => '/\b(NETFLIX|SPOTIFY|DISNEY|PRIME VIDEO|HBO|MAX|YOUTUBE|STEAM|PLAYSTATION|XBOX|CINEMA|INGRESSO|BAR|SHOW)\b/',
        'compras' => '/\b(MERCADO\s*LIVRE|AMAZON|SHOPEE|MAGALU|MAGAZINE LUIZA|AMERICANAS|ZARA|RENNER|RIACHUELO|NIKE|APPLE|KABUM)\b/',
    ];

    public function categorize(
        string $description,
        TransactionType $type,
        ?int $userId = null,
        ?string $pluggyCategory = null,
    ): ?int {
        $normalized = Str::ascii(Str::upper(trim($description.' '.($pluggyCategory ?? ''))));

        $slug = match ($type) {
            TransactionType::Income => $this->matchIncomeSlug($normalized),
            TransactionType::Transfer => $this->matchTransferSlug($normalized),
            TransactionType::Expense => $this->matchExpenseSlug($normalized),
        };

        return $this->resolveCategoryId($slug, $userId);
    }

    public function clearCache(): void
    {
        $this->globalSlugMap = [];
        $this->userSlugMaps = [];
    }

    private function matchIncomeSlug(string $normalized): string
    {
        if (preg_match('/\b(DIVIDENDO|JCP|JSCP|RENDIMENTO|PROVENTO|FII)\b/', $normalized) === 1) {
            return 'proventos';
        }

        if (preg_match('/\b(SALARIO|FOLHA|PAGAMENTO|REMUNERACAO|PIX RECEBIDO|PRO LABORE)\b/', $normalized) === 1) {
            return 'salario';
        }

        return 'salario';
    }

    private function matchTransferSlug(string $normalized): string
    {
        if (preg_match('/\b(INVEST|CORRETORA|TESOURO|B3|CDB|XP|RICO|NUINVEST|BTG|APORTE)\b/', $normalized) === 1) {
            return 'investimentos';
        }

        return 'investimentos';
    }

    private function matchExpenseSlug(string $normalized): string
    {
        foreach (self::EXPENSE_PATTERNS as $slug => $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return $slug;
            }
        }

        return 'outros';
    }

    private function resolveCategoryId(string $slug, ?int $userId): ?int
    {
        if ($userId !== null) {
            if (! isset($this->userSlugMaps[$userId])) {
                $this->userSlugMaps[$userId] = Category::query()
                    ->where('user_id', $userId)
                    ->pluck('id', 'slug')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();
            }

            if (isset($this->userSlugMaps[$userId][$slug])) {
                return $this->userSlugMaps[$userId][$slug];
            }
        }

        if ($this->globalSlugMap === []) {
            $this->globalSlugMap = Category::query()
                ->whereNull('user_id')
                ->pluck('id', 'slug')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        return $this->globalSlugMap[$slug] ?? $this->globalSlugMap['outros'] ?? null;
    }
}
