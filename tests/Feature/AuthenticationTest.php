<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ResetTenantContext;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
        ])->postJson('/api/auth/login', [
            'email' => 'joao@alpha.com',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'Joao Alpha',
                    'email' => 'joao@alpha.com',
                ],
            ]);

        $this->assertNotNull($response->json('token'));
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
        ])->postJson('/api/auth/login', [
            'email' => 'joao@alpha.com',
            'password' => 'wrongpassword',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Credentials not found or invalid.',
            ]);
    }

    public function test_login_with_email_from_different_tenant_is_rejected(): void
    {
        $alpha = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        $beta = Tenant::create([
            'name' => 'Operadora Beta',
            'slug' => 'beta',
        ]);

        app(TenantContext::class)->set($alpha);

        User::create([
            'tenant_id' => $alpha->id,
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'beta',
        ])->postJson('/api/auth/login', [
            'email' => 'joao@alpha.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Credentials not found or invalid.',
            ]);
    }

    public function test_logout_revokes_token(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        app(TenantContext::class)->set($tenant);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Joao Alpha',
            'email' => 'joao@alpha.com',
            'password' => bcrypt('password123'),
        ]);

        // Criar conta para o usuário (necessário para testar /account/balance)
        Account::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'balance' => '1000.00',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
            'Authorization' => "Bearer $token",
        ])->postJson('/api/auth/logout');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        // Verificar que o token foi revogado no banco
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_route_requires_authentication(): void
    {
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-Slug' => 'alpha',
        ])->getJson('/api/account/balance');

        $response->assertStatus(401);
    }
}
