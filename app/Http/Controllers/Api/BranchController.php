<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BranchController extends Controller
{
    #[OA\Get(
        path: '/branches',
        tags: ['Branches'],
        summary: 'List cabang',
        description: 'Access: All authenticated roles (Admin, Pemilik Kos, Customer)',
        operationId: 'listBranches',
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Branch::query()->latest()->paginate(10),
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
            content: new OA\JsonContent(
                required: ['name','description','address','longitude','latitude','phone','qris_code'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Cabang A'),
                    new OA\Property(property: 'description', type: 'string', example: 'Deskripsi cabang'),
                    new OA\Property(property: 'address', type: 'string', example: 'Jl. Mawar'),
                    new OA\Property(property: 'longitude', type: 'string', example: '106.827'),
                    new OA\Property(property: 'latitude', type: 'string', example: '-6.175'),
                    new OA\Property(property: 'phone', type: 'string', example: '08123456789'),
                    new OA\Property(property: 'qris_code', type: 'string', example: 'qris-123'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'address' => ['required', 'string'],
            'longitude' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'qris_code' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Cabang berhasil dibuat.',
            'data' => $branch,
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
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
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'longitude', type: 'string'),
                    new OA\Property(property: 'latitude', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'qris_code', type: 'string'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'address' => ['sometimes', 'required', 'string'],
            'longitude' => ['sometimes', 'required', 'string', 'max:100'],
            'latitude' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'qris_code' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
}
