<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Vault API",
 *     description="API Multi-tenant para gestão de contas e transações financeiras",
 *     @OA\Contact(
 *         email="dev@vault.com",
 *         name="Vault API Team"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8080",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="sanctum",
 *     description="Informe o token obtido no login"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Autenticação de usuários"
 * )
 *
 * @OA\Tag(
 *     name="Account",
 *     description="Operações de conta e saldo"
 * )
 *
 * @OA\Tag(
 *     name="Transactions",
 *     description="Operações de crédito, débito e histórico"
 * )
 *
 * @OA\Schema(
 *     schema="Error",
 *     @OA\Property(property="message", type="string", example="Error message")
 * )
 *
 * @OA\Parameter(
 *     parameter="TenantHeader",
 *     name="X-Tenant-Slug",
 *     in="header",
 *     required=true,
 *     description="Slug do tenant (operadora)",
 *     @OA\Schema(type="string", example="alpha")
 * )
 */
class ApiDocumentation
{
}
