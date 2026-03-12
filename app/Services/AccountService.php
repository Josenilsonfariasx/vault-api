<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;

class AccountService
{
    /**
     * Get the account for a given user.
     * Creates a new account with zero balance if one doesn't exist.
     */
    public function getBalance(User $user): Account
    {
        $account = $user->account;

        if (!$account) {
            $account = Account::create([
                'user_id' => $user->id,
                'balance' => '0.00',
            ]);
        }

        return $account;
    }

    /**
     * Get or create an account for a user (alias for getBalance).
     */
    public function getOrCreateAccount(User $user): Account
    {
        return $this->getBalance($user);
    }
}
