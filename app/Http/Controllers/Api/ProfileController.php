<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/profile',
        tags: ['Profile'],
        summary: 'Ambil profil',
        description: 'Access: Semua user login. Menampilkan profil user yang sedang login.',
        operationId: 'showProfile',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user(),
        ]);
    }

    #[OA\Put(
        path: '/profile',
        tags: ['Profile'],
        summary: 'Update profil',
        description: 'Access: Semua user login. Mengubah nama, email, nomor HP, dan alamat user yang sedang login.',
        operationId: 'updateProfile',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Kiarra'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'kiarra@example.com'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '08123456789'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, example: 'Jl. Mawar No. 10'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil berhasil diupdate.',
            'data' => $user->refresh(),
        ]);
    }

    #[OA\Put(
        path: '/profile/password',
        tags: ['Profile'],
        summary: 'Ganti password',
        description: 'Access: Semua user login. Mengganti password user yang sedang login.',
        operationId: 'updateProfilePassword',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'passwordbaru123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'passwordbaru123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
        ]);

        return response()->json([
            'message' => 'Password berhasil diupdate.',
        ]);
    }

    #[OA\Post(
        path: '/profile/photo',
        tags: ['Profile'],
        summary: 'Upload foto profil',
        description: 'Access: Semua user login. Mengunggah atau mengganti foto profil user yang sedang login.',
        operationId: 'uploadProfilePhoto',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_picture'],
                    properties: [
                        new OA\Property(property: 'profile_picture', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Uploaded')]
    )]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_picture' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('profile_picture')->store('profile-pictures', 'public');

        $user->update([
            'profile_picture' => $path,
        ]);

        return response()->json([
            'message' => 'Foto profil berhasil diupload.',
            'data' => $user->refresh(),
        ]);
    }
}
