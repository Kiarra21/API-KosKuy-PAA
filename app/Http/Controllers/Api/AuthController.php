<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\JWTGuard;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'login', 'forgotPassword', 'resetPassword']]);
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

        $token = $this->guard()->login($user);

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

        $token = $this->guard()->attempt($credentials);

        if (! is_string($token)) {
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
        description: 'Access: Semua user login',
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
        description: 'Access: Semua user login',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logout berhasil'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    #[OA\Post(
        path: '/auth/refresh',
        tags: ['Authentication'],
        summary: 'Refresh JWT token',
        operationId: 'refreshToken',
        description: 'Access: Semua user login',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Token berhasil direfresh'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    #[OA\Post(
        path: '/auth/forgot-password',
        tags: ['Authentication'],
        summary: 'Kirim kode lupa password ke email',
        operationId: 'forgotPassword',
        description: 'Access: Public',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'kiarra@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kode verifikasi berhasil dikirim'),
            new OA\Response(response: 422, description: 'Validasi gagal / Email tidak terdaftar'),
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
        ]);

        $email = $request->input('email');
        $token = sprintf("%06d", mt_rand(100000, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $token,
                'created_at' => Carbon::now(),
            ]
        );

        // Kirim email HTML
        Mail::send([], [], function ($message) use ($email, $token) {
            $message->to($email)
                ->subject('Kode Verifikasi Lupa Password - KosKuy')
                ->html("
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; background-color: #ffffff;'>
                        <h2 style='color: #4f46e5; text-align: center;'>Pemulihan Kata Sandi KosKuy</h2>
                        <p style='font-size: 16px; color: #374151;'>Halo,</p>
                        <p style='font-size: 16px; color: #374151;'>Kami menerima permintaan untuk menyetel ulang kata sandi akun Anda. Gunakan kode verifikasi di bawah ini untuk melanjutkan:</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #4f46e5; background-color: #f3f4f6; padding: 10px 20px; border-radius: 6px; border: 1px dashed #4f46e5;'>{$token}</span>
                        </div>
                        <p style='font-size: 14px; color: #6b7280; text-align: center;'>Kode ini berlaku selama 15 menit. Jangan bagikan kode ini dengan siapa pun.</p>
                        <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
                        <p style='font-size: 12px; color: #9ca3af; text-align: center;'>Jika Anda tidak meminta penyetelan ulang ini, Anda dapat mengabaikan email ini dengan aman.</p>
                    </div>
                ");
        });

        return response()->json([
            'message' => 'Kode verifikasi telah dikirim ke email Anda.',
        ]);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        tags: ['Authentication'],
        summary: 'Ubah password dengan token verifikasi',
        operationId: 'resetPassword',
        description: 'Access: Public',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'kiarra@example.com'),
                    new OA\Property(property: 'token', type: 'string', example: '123456'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'passwordbaru123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'passwordbaru123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password berhasil diubah'),
            new OA\Response(response: 422, description: 'Validasi gagal / Token tidak valid atau kadaluwarsa'),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $request->input('email');
        $token = $request->input('token');

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (! $resetRecord) {
            return response()->json([
                'message' => 'Kode verifikasi atau email tidak valid.',
            ], 422);
        }

        // Cek kadaluwarsa (15 menit)
        $createdAt = Carbon::parse($resetRecord->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'message' => 'Kode verifikasi telah kadaluwarsa.',
            ], 422);
        }

        // Update password
        $user = User::where('email', $email)->firstOrFail();
        $user->password = $request->input('password');
        $user->save();

        // Hapus token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'message' => 'Kata sandi Anda berhasil disetel ulang.',
        ]);
    }

    protected function respondWithToken(string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
            'user' => $this->guard()->user(),
        ], $status);
    }

    protected function guard(): JWTGuard
    {
        $guard = Auth::guard('api');

        if (! $guard instanceof JWTGuard) {
            throw new \RuntimeException('The api guard must use the jwt driver.');
        }

        return $guard;
    }
}
