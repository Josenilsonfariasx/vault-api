<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accountService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/account/balance",
     *     summary="Consultar saldo",
     *     description="Retorna o saldo atual da conta do usuário autenticado",
     *     tags={"Account"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantHeader"),
     *     @OA\Response(
     *         response=200,
     *         description="Saldo retornado com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="balance", type="string", example="1000.00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->accountService->getBalance($user);

        return response()->json([
            'data' => [
                'balance' => $account->balance,
            ],
        ], 200);
    }
}
