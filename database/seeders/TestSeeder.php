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
     * Creates two tenants for isolation testing:
     * - Tenant Alpha: joao@alpha.com / 123456 (balance: 1000.00)
     * - Tenant Beta: maria@beta.com / 123456 (balance: 500.00)
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 Criando dados de teste...');
        $this->command->info('');

        // ══════════════════════════════════════════════════════════════
        // TENANT ALPHA
        // ══════════════════════════════════════════════════════════════
        $alpha = Tenant::firstOrCreate(
            ['slug' => 'alpha'],
            ['name' => 'Operadora Alpha']
        );

        $joao = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'joao@alpha.com'],
            [
                'tenant_id' => $alpha->id,
                'name' => 'João Silva',
                'password' => Hash::make('123456'),
            ]
        );

        $joaoAccount = Account::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $joao->id],
            [
                'tenant_id' => $alpha->id,
                'balance' => '1000.00',
            ]
        );

        $this->command->info("✅ Tenant: {$alpha->name} (slug: {$alpha->slug})");
        $this->command->info("   └─ Usuário: {$joao->email} | Saldo: R$ {$joaoAccount->balance}");

        // ══════════════════════════════════════════════════════════════
        // TENANT BETA
        // ══════════════════════════════════════════════════════════════
        $beta = Tenant::firstOrCreate(
            ['slug' => 'beta'],
            ['name' => 'Operadora Beta']
        );

        $maria = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'maria@beta.com'],
            [
                'tenant_id' => $beta->id,
                'name' => 'Maria Santos',
                'password' => Hash::make('123456'),
            ]
        );

        $mariaAccount = Account::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $maria->id],
            [
                'tenant_id' => $beta->id,
                'balance' => '500.00',
            ]
        );

        $this->command->info("✅ Tenant: {$beta->name} (slug: {$beta->slug})");
        $this->command->info("   └─ Usuário: {$maria->email} | Saldo: R$ {$mariaAccount->balance}");

        // ══════════════════════════════════════════════════════════════
        // INSTRUÇÕES DE TESTE
        // ══════════════════════════════════════════════════════════════
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  📋 ROTEIRO DE TESTES MANUAIS (Insomnia/Postman)');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('URL Base: http://localhost:8080/api');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 1 — Login correto                                   │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ POST /api/auth/login                                        │');
        $this->command->info('│ Header: X-Tenant-Slug: alpha                                │');
        $this->command->info('│ Body: {"email":"joao@alpha.com","password":"123456"}        │');
        $this->command->info('│ Esperado: 200 + token                                       │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 2 — Login com tenant errado (isolamento!)           │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ POST /api/auth/login                                        │');
        $this->command->info('│ Header: X-Tenant-Slug: beta                                 │');
        $this->command->info('│ Body: {"email":"joao@alpha.com","password":"123456"}        │');
        $this->command->info('│ Esperado: 401 (usuário não existe nesse tenant)             │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 3 — Consultar saldo                                 │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ GET /api/account/balance                                    │');
        $this->command->info('│ Header: X-Tenant-Slug: alpha                                │');
        $this->command->info('│ Header: Authorization: Bearer {token}                       │');
        $this->command->info('│ Esperado: {"data":{"balance":"1000.00"}}                    │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 4 — Crédito                                         │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ POST /api/transactions                                      │');
        $this->command->info('│ Header: X-Tenant-Slug: alpha                                │');
        $this->command->info('│ Header: Authorization: Bearer {token}                       │');
        $this->command->info('│ Body: {"type":"credit","amount":"500.00"}                   │');
        $this->command->info('│ Esperado: 201 + saldo = 1500.00                             │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 5 — Débito com saldo suficiente                     │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ POST /api/transactions                                      │');
        $this->command->info('│ Body: {"type":"debit","amount":"200.00"}                    │');
        $this->command->info('│ Esperado: 201 + saldo = 1300.00                             │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 6 — Débito sem saldo (erro esperado)                │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ POST /api/transactions                                      │');
        $this->command->info('│ Body: {"type":"debit","amount":"9999.00"}                   │');
        $this->command->info('│ Esperado: 422 + mensagem de saldo insuficiente              │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────┐');
        $this->command->info('│ CENÁRIO 7 — Histórico de transações                         │');
        $this->command->info('├─────────────────────────────────────────────────────────────┤');
        $this->command->info('│ GET /api/transactions                                       │');
        $this->command->info('│ Esperado: lista das movimentações do usuário                │');
        $this->command->info('└─────────────────────────────────────────────────────────────┘');
        $this->command->info('');
    }
}
