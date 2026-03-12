<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Credit (add) an amount to an account.
     *
     * @param Account $account The account to credit
     * @param string  $amount  The amount to credit (as string for precision)
     * @return Transaction The created transaction
     */
    public function credit(Account $account, string $amount): Transaction
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($account, $amount) {
            // Lock the account row to prevent concurrent modifications
            $lockedAccount = Account::where('id', $account->id)
                ->lockForUpdate()
                ->first();

            // Update balance
            $lockedAccount->balance = bcadd($lockedAccount->balance, $amount, 2);
            $lockedAccount->save();

            // Create transaction record
            return Transaction::create([
                'account_id' => $lockedAccount->id,
                'type' => 'credit',
                'amount' => $amount,
            ]);
        });
    }

    /**
     * Debit (subtract) an amount from an account.
     *
     * @param Account $account The account to debit
     * @param string  $amount  The amount to debit (as string for precision)
     * @return Transaction The created transaction
     * @throws InsufficientBalanceException If balance is insufficient
     */
    public function debit(Account $account, string $amount): Transaction
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($account, $amount) {
            // Lock the account row to prevent concurrent modifications (race condition)
            $lockedAccount = Account::where('id', $account->id)
                ->lockForUpdate()
                ->first();

            // Check if balance is sufficient
            if (bccomp($lockedAccount->balance, $amount, 2) < 0) {
                throw new InsufficientBalanceException(
                    "Insufficient balance. Available: {$lockedAccount->balance}, Requested: {$amount}"
                );
            }

            // Update balance
            $lockedAccount->balance = bcsub($lockedAccount->balance, $amount, 2);
            $lockedAccount->save();

            // Create transaction record
            return Transaction::create([
                'account_id' => $lockedAccount->id,
                'type' => 'debit',
                'amount' => $amount,
            ]);
        });
    }

    /**
     * Get transaction history for an account.
     *
     * @param Account $account The account
     * @param int     $limit   Maximum number of transactions to return
     * @return Collection<Transaction>
     */
    public function getHistory(Account $account, int $limit = 50): Collection
    {
        return Transaction::where('account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Validate that the amount is positive and has at most 2 decimal places.
     *
     * @param string $amount
     * @throws \InvalidArgumentException
     */
    private function validateAmount(string $amount): void
    {
        // Check if amount is numeric
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Amount must be a valid number.');
        }

        // Check if amount is positive
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        // Check decimal places (at most 2)
        if (preg_match('/\.\d{3,}$/', $amount)) {
            throw new \InvalidArgumentException('Amount must have at most 2 decimal places.');
        }
    }
}
