<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestSeeder extends Seeder
{
    /**
     * Seed the database with test data for manual API testing.
     * 
     * Creates:
     * - Tenant: "acme" (slug: acme)
     * - User: test@acme.com / password123
     * - Account with balance: 1000.00
     */
    public function run(): void
    {
        // Create tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'acme'],
            ['name' => 'ACME Corporation']
        );

        $this->command->info("Tenant criado: {$tenant->name} (slug: {$tenant->slug})");

        // Create user
        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'test@acme.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Test User',
                'password' => Hash::make('password123'),
            ]
        );

        $this->command->info("Usuário criado: {$user->email}");

        // Create account with initial balance
        $account = Account::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $tenant->id,
                'balance' => '1000.00',
            ]
        );

        $this->command->info("Conta criada com saldo: R$ {$account->balance}");

        $this->command->newLine();
        $this->command->info('=== DADOS PARA TESTE NO INSOMNIA ===');
        $this->command->info('URL Base: http://localhost:8080/api');
        $this->command->info('Header: X-Tenant: acme');
        $this->command->newLine();
        $this->command->info('POST /auth/login');
        $this->command->info('Body: {"email": "test@acme.com", "password": "password123"}');
    }
}
