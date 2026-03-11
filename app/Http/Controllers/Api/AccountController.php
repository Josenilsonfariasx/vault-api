<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function balance(Request $request)
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'message' => 'Account not found.',
            ], 404);
        }

        return response()->json([
            'balance' => (string) $account->balance,
        ], 200);
    }
}
