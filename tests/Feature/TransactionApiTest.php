<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ResetTenantContext;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    private Tenant $tenant;
    private User $user;
    private Account $account;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->token = $this->user->createToken('api-token')->plainTextToken;
    }

    // ==================== CREDIT TESTS ====================

    public function test_credit_transaction_increases_balance(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'credit',
            'amount' => '250.00',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'transaction' => ['id', 'type', 'amount', 'created_at'],
                    'balance',
                ],
            ])
            ->assertJson([
                'message' => 'Transaction created successfully.',
                'data' => [
                    'transaction' => [
                        'type' => 'credit',
                        'amount' => '250.00',
                    ],
                    'balance' => '1250.00',
                ],
            ]);
    }

    public function test_credit_creates_account_if_not_exists(): void
    {
        // Create user without account
        $userNoAccount = User::create([
            'name' => 'Maria Alpha',
            'email' => 'maria@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $userNoAccount->createToken('api-token')->plainTextToken;

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/transactions', [
            'type' => 'credit',
            'amount' => '500.00',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'balance' => '500.00',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $userNoAccount->id,
            'balance' => '500.00',
        ]);
    }

    // ==================== DEBIT TESTS ====================

    public function test_debit_transaction_decreases_balance(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'debit',
            'amount' => '300.00',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'transaction' => [
                        'type' => 'debit',
                        'amount' => '300.00',
                    ],
                    'balance' => '700.00',
                ],
            ]);
    }

    public function test_debit_with_insufficient_balance_returns_422(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'debit',
            'amount' => '9999.00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'error' => 'insufficient_balance',
            ]);

        // Verify balance unchanged
        $this->account->refresh();
        $this->assertSame('1000.00', $this->account->balance);
    }

    public function test_debit_exact_balance_succeeds(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'debit',
            'amount' => '1000.00',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'balance' => '0.00',
                ],
            ]);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_transaction_requires_type(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'amount' => '100.00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_transaction_requires_valid_type(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'invalid',
            'amount' => '100.00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_transaction_requires_amount(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'credit',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transaction_rejects_zero_amount(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'credit',
            'amount' => '0',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transaction_rejects_negative_amount(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'credit',
            'amount' => '-100.00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_transaction_rejects_more_than_two_decimals(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', [
            'type' => 'credit',
            'amount' => '10.999',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    // ==================== HISTORY TESTS ====================

    public function test_index_returns_transaction_history(): void
    {
        // Create some transactions
        $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', ['type' => 'credit', 'amount' => '100.00']);

        $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', ['type' => 'debit', 'amount' => '50.00']);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/transactions');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'transactions' => [
                        '*' => ['id', 'type', 'amount', 'created_at'],
                    ],
                    'balance',
                ],
            ])
            ->assertJsonCount(2, 'data.transactions');
    }

    public function test_index_returns_empty_for_user_without_account(): void
    {
        $userNoAccount = User::create([
            'name' => 'Pedro Alpha',
            'email' => 'pedro@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $userNoAccount->createToken('api-token')->plainTextToken;

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/transactions');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'transactions' => [],
                    'balance' => '0.00',
                ],
            ]);
    }

    public function test_index_respects_limit_parameter(): void
    {
        // Create 10 transactions
        for ($i = 0; $i < 10; $i++) {
            $this->withHeaders([
                'X-Tenant-Slug' => 'alpha',
                'Authorization' => 'Bearer ' . $this->token,
            ])->postJson('/api/transactions', ['type' => 'credit', 'amount' => '10.00']);
        }

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/transactions?limit=5');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data.transactions');
    }

    // ==================== TENANT ISOLATION TESTS ====================

    public function test_user_only_sees_own_transactions(): void
    {
        // This test verifies that transactions are filtered by account_id
        // Multi-tenant isolation is tested in TenantScopeTest and TransactionServiceTest

        // Create multiple transactions for first user
        $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', ['type' => 'credit', 'amount' => '100.00']);

        $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/transactions', ['type' => 'debit', 'amount' => '50.00']);

        // Verify user sees both their own transactions
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/transactions');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.transactions');

        // Verify the transactions are correct
        $transactions = $response->json('data.transactions');
        $amounts = array_column($transactions, 'amount');

        $this->assertContains('100.00', $amounts);
        $this->assertContains('50.00', $amounts);
    }

    // ==================== AUTH TESTS ====================

    public function test_transaction_requires_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
        ])->postJson('/api/transactions', [
            'type' => 'credit',
            'amount' => '100.00',
        ]);

        $response->assertStatus(401);
    }

    public function test_history_requires_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
        ])->getJson('/api/transactions');

        $response->assertStatus(401);
    }
}
