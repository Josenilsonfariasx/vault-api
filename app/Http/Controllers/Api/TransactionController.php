<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Services\AccountService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private AccountService $accountService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/transactions",
     *     summary="Criar transação",
     *     description="Cria uma nova transação de crédito ou débito na conta do usuário",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantHeader"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "amount"},
     *             @OA\Property(property="type", type="string", enum={"credit", "debit"}, example="credit"),
     *             @OA\Property(property="amount", type="number", format="float", example=100.50, minimum=0.01)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transação criada com sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Transaction created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="transaction",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="type", type="string", example="credit"),
     *                     @OA\Property(property="amount", type="string", example="100.50"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="balance", type="string", example="1100.50")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados inválidos ou saldo insuficiente para débito",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function store(TransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->accountService->getOrCreateAccount($user);

        $type = $request->validated('type');
        $amount = $request->validated('amount');

        if ($type === 'credit') {
            $transaction = $this->transactionService->credit($account, $amount);
        } else {
            $transaction = $this->transactionService->debit($account, $amount);
        }

        $account->refresh();

        return response()->json([
            'message' => 'Transaction created successfully.',
            'data' => [
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'created_at' => $transaction->created_at,
                ],
                'balance' => $account->balance,
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/transactions",
     *     summary="Listar transações",
     *     description="Retorna o histórico de transações do usuário autenticado",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantHeader"),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Número máximo de transações a retornar",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de transações",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="type", type="string", example="credit"),
     *                         @OA\Property(property="amount", type="string", example="100.50"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
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
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'data' => [
                    'transactions' => [],
                    'balance' => '0.00',
                ],
            ], 200);
        }

        $limit = $request->query('limit', 50);
        $transactions = $this->transactionService->getHistory($account, (int) $limit);

        return response()->json([
            'data' => [
                'transactions' => $transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => $t->amount,
                    'created_at' => $t->created_at,
                ]),
                'balance' => $account->balance,
            ],
        ], 200);
    }
}
