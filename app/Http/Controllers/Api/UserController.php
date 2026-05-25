<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/users',
        tags: ['Users'],
        summary: 'List user',
        description: 'Access: Pemilik Kos only. Menampilkan daftar semua user berdasarkan role, status, atau pencarian.',
        operationId: 'listUsers',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['admin', 'pemilik_kos', 'customer'])),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/users',
        tags: ['Users'],
        summary: 'Buat user',
        description: 'Access: Pemilik Kos only. Membuat akun admin, pemilik kos, atau customer.',
        operationId: 'createUser',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Pemilik Kos'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'pemilik2@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'pemilik_kos', 'customer'], example: 'pemilik_kos'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08123456789'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Jl. Mawar No. 10'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'branch_id', type: 'integer', nullable: true, example: 1),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'pemilik_kos', 'customer'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'data' => $user,
        ], 201);
    }

    #[OA\Get(
        path: '/users/{id}',
        tags: ['Users'],
        summary: 'Detail user',
        description: 'Access: Pemilik Kos only. Menampilkan detail satu user.',
        operationId: 'showUser',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user,
        ]);
    }

    #[OA\Put(
        path: '/users/{id}',
        tags: ['Users'],
        summary: 'Update user',
        description: 'Access: Pemilik Kos only. Mengubah data akun dan status user.',
        operationId: 'updateUser',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Pemilik Kos'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'pemilik2@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'passwordbaru123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'passwordbaru123'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'pemilik_kos', 'customer'], example: 'pemilik_kos'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08123456789'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Jl. Mawar No. 10'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'branch_id', type: 'integer', nullable: true, example: 1),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'pemilik_kos', 'customer'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User berhasil diupdate.',
            'data' => $user->refresh(),
        ]);
    }

    #[OA\Delete(
        path: '/users/{id}',
        tags: ['Users'],
        summary: 'Hapus user',
        description: 'Access: Pemilik Kos only. Menghapus akun user.',
        operationId: 'deleteUser',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(User $user): JsonResponse
    {
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.',
        ]);
    }
}
