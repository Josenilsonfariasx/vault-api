<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Services\AccountService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private AccountService $accountService
    ) {}

    /**
     * Create a new transaction (credit or debit).
     */
    public function store(TransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->accountService->getOrCreateAccount($user);

        $type = $request->validated('type');
        $amount = $request->validated('amount');

        if ($type === 'credit') {
            $transaction = $this->transactionService->credit($account, $amount);
        } else {
            $transaction = $this->transactionService->debit($account, $amount);
        }

        $account->refresh();

        return response()->json([
            'message' => 'Transaction created successfully.',
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'created_at' => $transaction->created_at,
                ],
                'balance' => $account->balance,
            ],
        ], 201);
    }

    /**
     * List transaction history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'data' => [
                    'transactions' => [],
                    'balance' => '0.00',
                ],
            ], 200);
        }

        $limit = $request->query('limit', 50);
        $transactions = $this->transactionService->getHistory($account, (int) $limit);

        return response()->json([
            'data' => [
                'transactions' => $transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'created_at' => $t->created_at,
                ]),
                'balance' => $account->balance,
            ],
        ], 200);
    }
}
