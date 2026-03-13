# Vault API

Backend multi-tenant para gestão de contas e transações financeiras. Cada operadora (tenant) tem sua própria base de usuários e dados completamente isolados.

## Stack

- PHP 8.3 + Laravel 11
- PostgreSQL 16
- Docker Compose
- Laravel Sanctum (autenticação)

## Pré-requisitos

- Docker 20.10+
- Docker Compose 2.0+
- Portas disponíveis: `8080` (API) e `5432` (PostgreSQL)

## Rodando o projeto

```bash
# Subir os containers
docker compose up -d --build

# Instalar dependências
docker compose exec app composer install

# Configurar ambiente
cp .env.example .env
docker compose exec app php artisan key:generate

# Criar tabelas
docker compose exec app php artisan migrate

# Popular dados de teste
docker compose exec app php artisan db:seed --class=TestSeeder
```

A API estará disponível em `http://localhost:8080/api`

### Dados de teste

O seeder cria dois tenants para validar o isolamento:

| Tenant  | Usuário        | Senha  | Saldo inicial |
| ------- | -------------- | ------ | ------------- |
| `alpha` | joao@alpha.com | 123456 | R$ 1.000,00   |
| `beta`  | maria@beta.com | 123456 | R$ 500,00     |

## Endpoints

Todas as rotas exigem o header `X-Tenant-Slug` para identificar a operadora.

| Método | Rota                   | Auth | Descrição                 |
| ------ | ---------------------- | ---- | ------------------------- |
| POST   | `/api/auth/login`      | Não  | Autentica e retorna token |
| POST   | `/api/auth/logout`     | Sim  | Revoga o token            |
| GET    | `/api/account/balance` | Sim  | Consulta saldo            |
| POST   | `/api/transactions`    | Sim  | Cria crédito ou débito    |
| GET    | `/api/transactions`    | Sim  | Lista histórico           |

### Exemplos de uso

**Login:**

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant-Slug: alpha" \
  -d '{"email":"joao@alpha.com","password":"123456"}'
```

**Consultar saldo:**

```bash
curl http://localhost:8080/api/account/balance \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-Slug: alpha"
```

**Fazer crédito:**

```bash
curl -X POST http://localhost:8080/api/transactions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-Slug: alpha" \
  -d '{"type":"credit","amount":"150.00"}'
```

**Fazer débito:**

```bash
curl -X POST http://localhost:8080/api/transactions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-Slug: alpha" \
  -d '{"type":"debit","amount":"50.00"}'
```

## Documentação Swagger

A API possui documentação interativa disponível em:

```
http://localhost:8080/api/documentation
```

A documentação OpenAPI 3.0 também pode ser acessada em JSON:

```
http://localhost:8080/docs/api-docs.json
```

## Respostas de erro

| Código | Situação                                 | Exemplo de resposta                        |
| ------ | ---------------------------------------- | ------------------------------------------ |
| 400    | Header de tenant ausente                 | `{"message": "Tenant header ausente."}`    |
| 401    | Não autenticado ou credenciais inválidas | `{"message": "Unauthenticated."}`          |
| 404    | Tenant não encontrado                    | `{"message": "Operadora não encontrada."}` |
| 422    | Validação falhou ou saldo insuficiente   | `{"message": "Saldo insuficiente."}`       |

## Testes automatizados

```bash
docker compose exec app php artisan test
```

54 testes cobrindo isolamento de tenant, autenticação, regras de negócio e endpoints.

---

## Decisões técnicas

### Por que PostgreSQL e não MySQL?

O plano inicial era MySQL, mas optei por PostgreSQL por alguns motivos:

1. **Tipo DECIMAL nativo** — precisão exata para valores monetários sem surpresas de arredondamento
2. **Experiência pessoal** — já trabalhei mais com Postgres em sistemas financeiros

Trade-off: precisei mudar o driver de `pdo_mysql` para `pdo_pgsql` no Dockerfile.

### Identificação do tenant via header HTTP

Escolhi usar o header `X-Tenant-Slug` para identificar a operadora. Alternativas consideradas:

- **Subdomínio** (`alpha.vault.com`) — mais elegante, mas exige configuração de DNS e setup extra no ambiente de desenvolvimento
- **Path prefix** (`/api/alpha/...`) — polui a estrutura de rotas e não é tão intuitivo para APIs REST
- **Claim no JWT** — acoplaria autenticação com identificação de tenant e complicaria o fluxo de login

O header é simples de testar (curl, Postman) e não exige setup extra. Em produção real, provavelmente usaria subdomínio.

### Global Scope para isolamento

O isolamento entre tenants é feito via Global Scope do Eloquent. Toda query em `User`, `Account` e `Transaction` automaticamente adiciona `WHERE tenant_id = ?`.

```php
// Qualquer lugar do código
User::all(); // Já filtra pelo tenant atual
```

O `tenant_id` está desnormalizado em todas as tabelas (mesmo tendo FK para tabelas que já têm `tenant_id`). Isso evita JOINs no scope e simplifica as queries. Principalmente redundância proposital.

### Lock pessimista no débito

Operações de débito usam `lockForUpdate()` dentro de uma transaction:

```php
DB::transaction(function () {
    $account = Account::where('id', $id)->lockForUpdate()->first();
    // validar saldo, subtrair, salvar
});
```

Isso previne race condition em requisições concorrentes (double-spend). É um lock pessimista — bloqueia a linha até o commit. Um lock otimista seria mais performático, mas mais complexo de implementar corretamente.

### BC Math para aritmética monetária

Nunca uso `+` e `-` do PHP para dinheiro. Operadores nativos convertem para float e perdem precisão em centavos.

```php
$balance = bcadd($balance, $amount, 2);  // correto
$balance = $balance + $amount;           // perigoso
```

Trade-off: código mais verboso, mas zero risco de erros de arredondamento.

### Validação em camadas

Valores são validados em 3 lugares:

1. **FormRequest** — tipo e formato básico
2. **Service** — regras de negócio (saldo suficiente, valor positivo)
3. **Banco** — constraint `DECIMAL(15,2)`

Se uma camada falhar, outra protege. Defesa em profundidade.

### Autenticação com Sanctum

Escolhi Sanctum por ser nativo do Laravel e simples para APIs stateless. Cada login gera um token único que o cliente deve guardar. Logout revoga apenas o token atual (usuário pode ter múltiplos dispositivos).

O login faz double-check do `tenant_id` mesmo após o Global Scope filtrar:

```php
if ($user->tenant_id !== $tenant->id) {
    return response()->json(['message' => 'Credenciais inválidas.'], 401);
}
```

Se o middleware falhar por qualquer motivo, o sistema não deixa vazar acesso entre tenants. Gosto de ter essa camada extra de segurança.

---

## Premissas assumidas

1. **Seed é suficiente para operadoras** — não criei CRUD de tenants, conforme permitido no enunciado

2. **Um usuário = uma conta** — relação 1:1 entre User e Account. Se no futuro um usuário pudesse ter múltiplas contas, precisaria refatorar

3. **Transações são imutáveis** — uma vez criada, não pode ser editada ou deletada. Histórico é append-only

4. **Valores sempre com 2 casas decimais** — não suporto 3+ casas (ex: criptomoedas). O `DECIMAL(15,2)` é fixo

5. **Sem paginação no histórico** — retorna todas as transações. Em produção, adicionaria paginação e filtros (data, tipo)

6. **Token sem expiração automática** — Sanctum não expira tokens por padrão. Em produção, configuraria TTL e refresh tokens para segurança extra

---

## Estrutura do projeto

```
app/
├── Http/
│   ├── Controllers/Api/     # AuthController, AccountController, TransactionController
│   ├── Middleware/          # ResolveTenant
│   └── Requests/            # TransactionRequest (validação)
├── Models/                  # Tenant, User, Account, Transaction
├── Services/                # AccountService, TransactionService (regras de negócio)
├── Scopes/                  # TenantScope (isolamento automático)
├── Exceptions/              # InsufficientBalanceException
└── TenantContext.php        # Singleton com tenant da request atual

tests/
├── Feature/
│   ├── AuthenticationTest.php
│   ├── AccountApiTest.php
│   ├── TransactionApiTest.php
│   └── ...
└── Unit/
```

A lógica de negócio fica nos Services, não nos Controllers. Controllers só orquestram request → service → response.

## Patterns e boas práticas

- **Service Layer** — regras de negócio centralizadas em classes de serviço, facilitando manutenção e testes
- **FormRequest** — validação de entrada desacoplada dos controllers
- **Global Scope** — isolamento de tenant automático em todas as queries
- **Transactions e Locking** — garantia de consistência em operações críticas (débito)
- **Defesa em profundidade** — validação em múltiplas camadas para evitar falhas de segurança
- **BC Math** — precisão total em cálculos monetários
- **Testes automatizados** — cobertura abrangente para garantir qualidade e prevenir regressões

## Arquitetura

Arquitetura padrão do Laravel (MVC) com uma camada de Service para isolar regras de negócio:

```
Request → Middleware → Controller → Service → Model → Database
```

Não implementei Clean Architecture ou DDD — seria overengineering para o escopo do teste. A separação Controller/Service já resolve bem o problema de manter a lógica financeira testável e fora dos controllers.

Oberservação:

- A nomenclatura é simples e direta, seguindo convenções do Laravel. Não criei repositórios ou interfaces para os models, pois o Eloquent já é um ORM poderoso e não vejo necessidade de abstração extra nesse caso.
- O foco foi entregar um código claro, seguro e fácil de manter, sem adicionar complexidade desnecessária.
- O projeto é modular o suficiente para permitir futuras melhorias, como adição de novos tipos de transação, suporte a múltiplas contas por usuário, ou integração com serviços externos (ex: gateways de pagamento).
- A estrutura de pastas é organizada por responsabilidade, facilitando a navegação e localização de código relevante.
- Os testes cobrem os casos principais, mas em um projeto real eu adicionaria mais cenários de borda e testes de carga para garantir robustez sob alta concorrência.
- Não segui um padrão solid estrito (ex: SOLID, Clean Architecture) para evitar overengineering. O código é simples e direto, mas ainda assim modular e testável.
- Apliquei princípios de segurança desde o início, como validação rigorosa, isolamento de dados e defesa em profundidade. Isso é crucial em sistemas financeiros onde erros podem ter consequências graves.

## Considerações finais

O projeto é um backend robusto e seguro para gestão financeira multi-tenant. As decisões técnicas foram tomadas com foco em segurança, precisão e simplicidade de manutenção. O código é testado e segue boas práticas de desenvolvimento. O isolamento entre tenants é garantido por design, e a API é fácil de usar e estender no futuro.

## Autor: Josenilson Farias

Contato: fariaslwork@gmail.com
