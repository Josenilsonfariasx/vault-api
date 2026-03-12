<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accountService
    ) {}

    /**
     * Get the balance for the authenticated user's account.
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->accountService->getBalance($user);

        return response()->json([
            'data' => [
                'balance' => $account->balance,
            ],
        ], 200);
    }
}
