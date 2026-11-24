<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateTransactionRequest;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountTransactionController extends Controller
{
    public function __construct(
        private readonly BankTransactionService $bankTransactionService,
    ) {}

    public function index(Request $request, BankAccount $bankAccount): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 30);
        $rawSearch = $request->query('search');
        $search = is_string($rawSearch) ? $rawSearch : null;
        $rawCategoryId = $request->query('category_id');
        $categoryId = is_numeric($rawCategoryId) ? (int) $rawCategoryId : null;

        return response()->json([
            'data' => $this->bankTransactionService->listForBankAccount(
                user: $user,
                bankAccount: $bankAccount,
                perPage: $perPage,
                search: $search,
                categoryId: $categoryId,
            ),
        ], 200);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->bankTransactionService->updateTransaction(
                user: $user,
                transaction: $transaction,
                validated: $request->validated(),
            ),
        ], 200);
    }
}
