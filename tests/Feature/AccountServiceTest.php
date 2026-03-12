<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountService;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ResetTenantContext;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    private AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = new AccountService();
    }

    public function test_get_balance_returns_existing_account(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'balance' => '1000.00',
        ]);

        $result = $this->accountService->getBalance($user);

        $this->assertSame($account->id, $result->id);
        $this->assertSame('1000.00', $result->balance);
    }

    public function test_get_balance_creates_account_if_not_exists(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $this->assertNull($user->account);

        $result = $this->accountService->getBalance($user);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertSame('0.00', $result->balance);
        $this->assertSame($user->id, $result->user_id);
        $this->assertSame($tenant->id, $result->tenant_id);
    }

    public function test_get_or_create_account_is_alias_for_get_balance(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $result = $this->accountService->getOrCreateAccount($user);

        $this->assertInstanceOf(Account::class, $result);
        $this->assertSame('0.00', $result->balance);
    }
}
