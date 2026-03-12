<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ResetTenantContext;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    private Tenant $tenant;
    private User $user;
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

        $this->token = $this->user->createToken('api-token')->plainTextToken;
    }

    public function test_balance_returns_existing_account_balance(): void
    {
        Account::create([
            'user_id' => $this->user->id,
            'balance' => '1500.50',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/account/balance');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'balance' => '1500.50',
                ],
            ]);
    }

    public function test_balance_creates_account_with_zero_if_not_exists(): void
    {
        // User has no account
        $this->assertNull($this->user->account);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/account/balance');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'balance' => '0.00',
                ],
            ]);

        // Verify account was created
        $this->assertDatabaseHas('accounts', [
            'user_id' => $this->user->id,
            'balance' => '0.00',
        ]);
    }

    public function test_balance_requires_authentication(): void
    {
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
        ])->getJson('/api/account/balance');

        $response->assertStatus(401);
    }

    public function test_balance_requires_tenant_header(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/account/balance');

        $response->assertStatus(400);
    }

    public function test_balance_shows_correct_value_for_authenticated_user(): void
    {
        // Create account for Alpha user
        Account::create([
            'user_id' => $this->user->id,
            'balance' => '1000.00',
        ]);

        // Create another user in same tenant with different balance
        $otherUser = User::create([
            'name' => 'Pedro Alpha',
            'email' => 'pedro@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        Account::create([
            'user_id' => $otherUser->id,
            'balance' => '5000.00',
        ]);

        // First user sees their own balance, not the other user's
        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/account/balance');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'balance' => '1000.00',
                ],
            ]);
    }
}
