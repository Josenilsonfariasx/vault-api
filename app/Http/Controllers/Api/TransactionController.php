<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Transaction created.',
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'transactions' => [],
            ], 200);
        }

        $transactions = $account->transactions()->latest()->get();

        return response()->json([
            'transactions' => $transactions,
        ], 200);
    }
}
