<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ResetTenantContext;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    private TransactionService $transactionService;
    private Tenant $tenant;
    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionService = new TransactionService();

        $this->tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($this->tenant);

        $this->user = User::create([
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'balance' => '1000.00',
        ]);
    }

    // ==================== CREDIT TESTS ====================

    public function test_credit_adds_amount_to_balance(): void
    {
        $transaction = $this->transactionService->credit($this->account, '250.00');

        $this->account->refresh();

        $this->assertSame('1250.00', $this->account->balance);
        $this->assertSame('credit', $transaction->type);
        $this->assertSame('250.00', $transaction->amount);
        $this->assertSame($this->account->id, $transaction->account_id);
    }

    public function test_credit_creates_transaction_record(): void
    {
        $this->assertDatabaseCount('transactions', 0);

        $this->transactionService->credit($this->account, '100.00');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $this->account->id,
            'type' => 'credit',
            'amount' => '100.00',
        ]);
    }

    public function test_credit_with_decimal_amount(): void
    {
        $this->transactionService->credit($this->account, '99.99');

        $this->account->refresh();

        $this->assertSame('1099.99', $this->account->balance);
    }

    // ==================== DEBIT TESTS ====================

    public function test_debit_subtracts_amount_from_balance(): void
    {
        $transaction = $this->transactionService->debit($this->account, '300.00');

        $this->account->refresh();

        $this->assertSame('700.00', $this->account->balance);
        $this->assertSame('debit', $transaction->type);
        $this->assertSame('300.00', $transaction->amount);
    }

    public function test_debit_throws_exception_when_insufficient_balance(): void
    {
        $this->expectException(InsufficientBalanceException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->transactionService->debit($this->account, '9999.00');
    }

    public function test_debit_does_not_change_balance_on_insufficient_funds(): void
    {
        try {
            $this->transactionService->debit($this->account, '9999.00');
        } catch (InsufficientBalanceException $e) {
            // Expected
        }

        $this->account->refresh();

        $this->assertSame('1000.00', $this->account->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_debit_allows_exact_balance_withdrawal(): void
    {
        $transaction = $this->transactionService->debit($this->account, '1000.00');

        $this->account->refresh();

        $this->assertSame('0.00', $this->account->balance);
        $this->assertInstanceOf(Transaction::class, $transaction);
    }

    public function test_debit_with_decimal_amount(): void
    {
        $this->transactionService->debit($this->account, '0.01');

        $this->account->refresh();

        $this->assertSame('999.99', $this->account->balance);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_credit_rejects_zero_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $this->transactionService->credit($this->account, '0.00');
    }

    public function test_credit_rejects_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        $this->transactionService->credit($this->account, '-100.00');
    }

    public function test_credit_rejects_more_than_two_decimal_places(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must have at most 2 decimal places');

        $this->transactionService->credit($this->account, '10.999');
    }

    public function test_debit_rejects_invalid_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a valid number');

        $this->transactionService->debit($this->account, 'not-a-number');
    }

    // ==================== HISTORY TESTS ====================

    public function test_get_history_returns_transactions_in_descending_order(): void
    {
        $this->transactionService->credit($this->account, '100.00');
        $this->transactionService->credit($this->account, '200.00');
        $this->transactionService->debit($this->account, '50.00');

        $history = $this->transactionService->getHistory($this->account);

        $this->assertCount(3, $history);
        $this->assertSame('50.00', $history[0]->amount); // Most recent first
        $this->assertSame('debit', $history[0]->type);
        $this->assertSame('200.00', $history[1]->amount);
        $this->assertSame('100.00', $history[2]->amount);
    }

    public function test_get_history_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->transactionService->credit($this->account, '10.00');
        }

        $history = $this->transactionService->getHistory($this->account, 5);

        $this->assertCount(5, $history);
    }

    public function test_get_history_returns_empty_collection_when_no_transactions(): void
    {
        $history = $this->transactionService->getHistory($this->account);

        $this->assertCount(0, $history);
    }

    // ==================== TENANT ISOLATION TESTS ====================

    public function test_transactions_are_isolated_by_tenant(): void
    {
        // Create transaction for Alpha
        $this->transactionService->credit($this->account, '500.00');

        // Create Beta tenant and account
        $beta = Tenant::create([
            'name' => 'Operadora Beta',
            'slug' => 'beta',
        ]);

        app(TenantContext::class)->set($beta);

        $betaUser = User::create([
            'name' => 'Maria Beta',
            'email' => 'maria@beta.com',
            'password' => bcrypt('password123'),
        ]);

        $betaAccount = Account::create([
            'user_id' => $betaUser->id,
            'balance' => '2000.00',
        ]);

        $this->transactionService->credit($betaAccount, '100.00');

        // Verify isolation - Beta should only see Beta's transaction
        $betaHistory = $this->transactionService->getHistory($betaAccount);
        $this->assertCount(1, $betaHistory);
        $this->assertSame('100.00', $betaHistory[0]->amount);

        // Switch back to Alpha
        app(TenantContext::class)->set($this->tenant);

        $alphaHistory = $this->transactionService->getHistory($this->account);
        $this->assertCount(1, $alphaHistory);
        $this->assertSame('500.00', $alphaHistory[0]->amount);
    }
}
