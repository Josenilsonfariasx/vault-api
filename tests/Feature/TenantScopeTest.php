<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ResetTenantContext;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    public function test_it_filters_models_by_the_current_tenant_context(): void
    {
        [$alpha, $alphaUser, $alphaAccount, $alphaTransaction] = $this->createTenantGraph('alpha');
        [$beta, $betaUser, $betaAccount, $betaTransaction] = $this->createTenantGraph('beta');

        app(TenantContext::class)->set($alpha);

        $this->assertSame([$alphaUser->id], User::query()->pluck('id')->all());
        $this->assertSame([$alphaAccount->id], Account::query()->pluck('id')->all());
        $this->assertSame([$alphaTransaction->id], Transaction::query()->pluck('id')->all());

        app(TenantContext::class)->set($beta);

        $this->assertSame([$betaUser->id], User::query()->pluck('id')->all());
        $this->assertSame([$betaAccount->id], Account::query()->pluck('id')->all());
        $this->assertSame([$betaTransaction->id], Transaction::query()->pluck('id')->all());
    }

    public function test_it_sets_tenant_id_automatically_when_creating_models(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => '123456',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'balance' => '100.00',
        ]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount' => '25.00',
        ]);

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame($tenant->id, $account->tenant_id);
        $this->assertSame($tenant->id, $transaction->tenant_id);
    }

    public function test_it_returns_no_records_when_context_is_empty(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => '123456',
        ]);

        app()->instance(TenantContext::class, new TenantContext());

        $this->assertCount(0, User::all());
        $this->assertSame(0, app(TenantContext::class)->getId());
    }

    private function createTenantGraph(string $slug): array
    {
        $tenant = Tenant::create([
            'name' => 'Operadora '.strtoupper($slug),
            'slug' => $slug,
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'name' => 'User '.strtoupper($slug),
            'email' => $slug.'@example.com',
            'password' => '123456',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'balance' => '100.00',
        ]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'type' => 'credit',
            'amount' => '10.00',
        ]);

        return [$tenant, $user, $account, $transaction];
    }
}