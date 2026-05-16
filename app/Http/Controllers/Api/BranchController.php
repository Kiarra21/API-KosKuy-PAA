<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
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
        if ($branch->qris_code) {
            Storage::disk('public')->delete($branch->qris_code);
        }

        $branch->delete();

        return response()->json([
            'message' => 'Cabang berhasil dihapus.',
        ]);
    }
}
