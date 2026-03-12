<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login do usuário",
     *     description="Autentica o usuário e retorna um token Bearer",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/TenantHeader"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="joao@alpha.com"),
     *             @OA\Property(property="password", type="string", format="password", example="123456", minLength=6)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login bem-sucedido",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="1|abcdef123456..."),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="João"),
     *                 @OA\Property(property="email", type="string", example="joao@alpha.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciais inválidas ou tenant não encontrado",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados de validação inválidos",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function login(Request $request, TenantContext $tenantContext)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ]);

        $tenant = $tenantContext->get();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant context not found.',
            ], 401);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Credentials not found or invalid.',
            ], 401);
        }

        // Double-check: user belongs to current tenant (defensive)
        if ($user->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Credentials not found or invalid.',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout do usuário",
     *     description="Revoga o token atual do usuário",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantHeader"),
     *     @OA\Response(
     *         response=200,
     *         description="Logout bem-sucedido",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autenticado",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ], 200);
    }
}
