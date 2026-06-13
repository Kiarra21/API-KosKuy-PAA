<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class BranchController extends Controller
{
    #[OA\Get(
        path: '/branches',
        tags: ['Branches'],
        summary: 'List cabang',
        description: 'Access: Semua user login',
        operationId: 'listBranches',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Branch::query()
                ->with(['facilities:id,name', 'photos'])
                ->withMin('roomTypes', 'price')
                ->withMin('roomTypes', 'room_size')
                ->latest()
                ->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/branches',
        tags: ['Branches'],
        summary: 'Buat cabang baru',
        description: 'Access: Pemilik Kos only',
        operationId: 'createBranch',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name','description','address','longitude','latitude','phone','qris_code'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'Cabang A'),
                        new OA\Property(property: 'description', type: 'string', example: 'Deskripsi cabang'),
                        new OA\Property(property: 'address', type: 'string', example: 'Jl. Mawar'),
                        new OA\Property(property: 'longitude', type: 'string', example: '106.827'),
                        new OA\Property(property: 'latitude', type: 'string', example: '-6.175'),
                        new OA\Property(property: 'phone', type: 'string', example: '08123456789'),
                        new OA\Property(property: 'qris_code', type: 'string', format: 'binary'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]

    public function store(Request $request): JsonResponse
    {
        if ($request->has('is_active')) {
            $request->merge([
                'is_active' => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'address' => ['required', 'string'],
            'longitude' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'qris_code' => ['required', 'image', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['qris_code'] = $request->file('qris_code')->store('branch-qris', 'public');

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Cabang berhasil dibuat.',
            'data' => $branch,
        ], 201);
    }

    #[OA\Get(
        path: '/branches/{id}',
        tags: ['Branches'],
        summary: 'Detail cabang',
        description: 'Access: Semua user login. Menampilkan detail satu cabang kos termasuk lokasi dan QRIS.',
        operationId: 'showBranch',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(Branch $branch): JsonResponse
    {
        $branch->load(['facilities:id,name', 'photos']);
        $branch->loadMin('roomTypes', 'price');
        $branch->loadMin('roomTypes', 'room_size');

        return response()->json([
            'data' => $branch,
        ]);
    }

    #[OA\Put(
        path: '/branches/{id}',
        tags: ['Branches'],
        summary: 'Update cabang',
        description: 'Access: Pemilik Kos only',
        operationId: 'updateBranch',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'description', type: 'string', example: 'Deskripsi cabang terbaru'),
                        new OA\Property(property: 'address', type: 'string', example: 'Jl. Melati No. 5'),
                        new OA\Property(property: 'longitude', type: 'string', example: '106.827'),
                        new OA\Property(property: 'latitude', type: 'string', example: '-6.175'),
                        new OA\Property(property: 'phone', type: 'string', example: '08123456789'),
                        new OA\Property(property: 'qris_code', type: 'string', format: 'binary'),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]

    public function update(Request $request, Branch $branch): JsonResponse
    {
        if ($request->has('is_active')) {
            $request->merge([
                'is_active' => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'address' => ['sometimes', 'required', 'string'],
            'longitude' => ['sometimes', 'required', 'string', 'max:100'],
            'latitude' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'qris_code' => ['sometimes', 'required', 'image', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('qris_code')) {
            if ($branch->qris_code) {
                Storage::disk('public')->delete($branch->qris_code);
            }

            $validated['qris_code'] = $request->file('qris_code')->store('branch-qris', 'public');
        }

        $branch->update($validated);

        return response()->json([
            'message' => 'Cabang berhasil diupdate.',
            'data' => $branch,
        ]);
    }

    #[OA\Delete(
        path: '/branches/{id}',
        tags: ['Branches'],
        summary: 'Hapus cabang',
        description: 'Access: Pemilik Kos only',
        operationId: 'deleteBranch',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]

    public function destroy(Branch $branch): JsonResponse
    {
        $branch->delete();

        return response()->json([
            'message' => 'Cabang berhasil dihapus.',
        ]);
    }

    #[OA\Get(
        path: '/branches/{branch}/admins',
        tags: ['Branches'],
        summary: 'List admin cabang',
        description: 'Access: Pemilik Kos only. Menampilkan admin yang sudah terhubung ke cabang ini.',
        operationId: 'listBranchAdmins',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function admins(Branch $branch): JsonResponse
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->where('branch_id', $branch->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $admins,
        ]);
    }

    #[OA\Get(
        path: '/branches/{branch}/admins/available',
        tags: ['Branches'],
        summary: 'List admin tersedia',
        description: 'Access: Pemilik Kos only. Menampilkan admin yang belum punya branch.',
        operationId: 'listAvailableBranchAdmins',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function availableAdmins(Branch $branch): JsonResponse
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->whereNull('branch_id')
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $admins,
        ]);
    }

    #[OA\Post(
        path: '/branches/{branch}/admins/{user}',
        tags: ['Branches'],
        summary: 'Tambah admin ke cabang',
        description: 'Access: Pemilik Kos only. Menautkan user role admin yang masih kosong branch-nya ke cabang ini.',
        operationId: 'attachBranchAdmin',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function attachAdmin(Branch $branch, User $user): JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'User harus memiliki role admin.',
            ], 422);
        }

        if ($user->branch_id !== null && (int) $user->branch_id !== (int) $branch->id) {
            return response()->json([
                'message' => 'Admin sudah terhubung ke cabang lain.',
            ], 422);
        }

        $user->update([
            'branch_id' => $branch->id,
        ]);

        return response()->json([
            'message' => 'Admin berhasil ditambahkan ke cabang.',
            'data' => $user->refresh(),
        ]);
    }

    #[OA\Delete(
        path: '/branches/{branch}/admins/{user}',
        tags: ['Branches'],
        summary: 'Hapus admin dari cabang',
        description: 'Access: Pemilik Kos only. Mengosongkan branch_id pada admin yang terhubung ke cabang ini.',
        operationId: 'detachBranchAdmin',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function detachAdmin(Branch $branch, User $user): JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'User harus memiliki role admin.',
            ], 422);
        }

        if ((int) $user->branch_id !== (int) $branch->id) {
            return response()->json([
                'message' => 'Admin tidak terhubung ke cabang ini.',
            ], 422);
        }

        $user->update([
            'branch_id' => null,
        ]);

        return response()->json([
            'message' => 'Admin berhasil dihapus dari cabang.',
            'data' => $user->refresh(),
        ]);
    }
}
