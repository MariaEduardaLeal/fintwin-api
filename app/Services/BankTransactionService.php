<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class BankTransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly SimulationCacheInvalidator $cacheInvalidator,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(User $user): array
    {
        return Category::query()
            ->where(static function ($query) use ($user): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(static fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'type' => $category->type->value,
                'color' => $category->color,
                'icon' => $category->icon,
                'is_essential' => (bool) $category->is_essential,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function listForBankAccount(
        User $user,
        BankAccount $bankAccount,
        int $perPage = 30,
        ?string $search = null,
        ?int $categoryId = null,
    ): array {
        if ($bankAccount->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(BankAccount::class, [$bankAccount->id]);
        }

        $clampedPerPage = max(1, min(100, $perPage));

        $paginator = $this->transactions->paginateAccountTransactions(
            userId: $user->id,
            bankAccountId: $bankAccount->id,
            perPage: $clampedPerPage,
            search: $search,
            categoryId: $categoryId,
        );

        $items = $paginator->getCollection()
            ->map(fn (Transaction $transaction): array => $this->serializeTransaction($transaction))
            ->values()
            ->all();

        return [
            'bank_account' => [
                'id' => $bankAccount->id,
                'institution_name' => $bankAccount->institution_name,
                'name' => $bankAccount->name,
                'type' => $bankAccount->type->value,
                'currency_code' => $bankAccount->currency_code,
                'balance' => round((float) $bankAccount->balance, 2),
                'last_synced_at' => $bankAccount->last_synced_at?->toIso8601String(),
            ],
            'transactions' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function updateTransaction(User $user, Transaction $transaction, array $validated): array
    {
        if ($transaction->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(Transaction::class, [$transaction->id]);
        }

        if (array_key_exists('notes', $validated)) {
            $rawNotes = $validated['notes'];
            $transaction->notes = is_string($rawNotes) && trim($rawNotes) !== '' ? trim($rawNotes) : null;
        }

        if (array_key_exists('category_id', $validated)) {
            $rawCategoryId = $validated['category_id'];

            if ($rawCategoryId !== null) {
                $allowedCategory = Category::query()
                    ->whereKey((int) $rawCategoryId)
                    ->where(static function ($query) use ($user): void {
                        $query->whereNull('user_id')
                            ->orWhere('user_id', $user->id);
                    })
                    ->exists();

                if (! $allowedCategory) {
                    throw ValidationException::withMessages([
                        'category_id' => ['Categoria inválida.'],
                    ]);
                }

                $transaction->category_id = (int) $rawCategoryId;
                $transaction->is_category_manual = true;
            } else {
                $transaction->category_id = null;
                $transaction->is_category_manual = false;
            }
        }

        $transaction->save();
        $transaction->load('category');

        $this->cacheInvalidator->invalidateForUser($user->id);

        return $this->serializeTransaction($transaction);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'description' => $transaction->description,
            'notes' => $transaction->notes,
            'amount' => round((float) $transaction->amount, 2),
            'type' => $transaction->type->value,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'status' => $transaction->status->value,
            'payment_method' => $transaction->payment_method,
            'is_category_manual' => (bool) $transaction->is_category_manual,
            'category' => $transaction->category !== null ? [
                'id' => $transaction->category->id,
                'name' => $transaction->category->name,
                'slug' => $transaction->category->slug,
                'type' => $transaction->category->type->value,
                'color' => $transaction->category->color,
                'icon' => $transaction->category->icon,
                'is_essential' => (bool) $transaction->category->is_essential,
            ] : null,
        ];
    }
}
