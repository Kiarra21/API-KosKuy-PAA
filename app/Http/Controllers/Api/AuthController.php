<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'login']]);
    }

    #[OA\Post(
        path: '/auth/register',
        tags: ['Authentication'],
        summary: 'Daftar akun customer baru',
        operationId: 'registerCustomer',
        description: 'Access: Public (creates Customer role by default)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Kiarra'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'kiarra@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08123456789'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Jl. Mawar No. 10'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registrasi berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Kiarra'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'kiarra@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'customer'),
                                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08123456789'),
                                new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Jl. Mawar No. 10'),
                                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'customer',
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->respondWithToken($token, 201);
    }

    #[OA\Post(
        path: '/auth/login',
        tags: ['Authentication'],
        summary: 'Login user',
        operationId: 'loginUser',
        description: 'Access: Public',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'kiarra@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Kredensial tidak valid'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    #[OA\Get(
        path: '/auth/me',
        tags: ['Authentication'],
        summary: 'Ambil profil user login',
        operationId: 'getAuthenticatedUser',
        description: 'Access: Authenticated users (Admin, Pemilik Kos, Customer)',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Data user login'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function me(): JsonResponse
    {
        return response()->json([
            'user' => Auth::guard('api')->user(),
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        tags: ['Authentication'],
        summary: 'Logout user',
        operationId: 'logoutUser',
        description: 'Access: Authenticated users',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logout berhasil'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    #[OA\Post(
        path: '/auth/refresh',
        tags: ['Authentication'],
        summary: 'Refresh JWT token',
        operationId: 'refreshToken',
        description: 'Access: Authenticated users',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token berhasil direfresh'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(Auth::guard('api')->refresh());
    }

    protected function respondWithToken(string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => Auth::guard('api')->user(),
        ], $status);
    }
}
