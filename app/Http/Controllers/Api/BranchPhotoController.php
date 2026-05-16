<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class BranchPhotoController extends Controller
{
    #[OA\Get(
        path: '/branches/{branch}/photos',
        tags: ['Branches'],
        summary: 'List foto cabang',
        description: 'Access: Semua user login. Menampilkan foto bangunan atau fasilitas umum pada cabang kos tertentu.',
        operationId: 'listBranchPhotos',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Branch $branch): JsonResponse
    {
        return response()->json([
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
            'data' => $branch->photos,
        ]);
    }

    #[OA\Post(
        path: '/branches/{branch}/photos',
        tags: ['Branches'],
        summary: 'Upload foto cabang',
        description: 'Access: Pemilik Kos only. Mengunggah foto bangunan kos atau fasilitas umum cabang.',
        operationId: 'uploadBranchPhoto',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['photo'],
                    properties: [
                        new OA\Property(property: 'photo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'order', type: 'integer', example: 1),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $path = $request->file('photo')->store('branch-photos', 'public');

        $photo = BranchPhoto::create([
            'branch_id' => $branch->id,
            'photo' => $path,
            'order' => $validated['order'] ?? (($branch->photos()->max('order') ?? 0) + 1),
        ]);

        return response()->json([
            'message' => 'Foto cabang berhasil diupload.',
            'data' => $photo,
        ], 201);
    }

    #[OA\Put(
        path: '/branch-photos/{branch_photo}',
        tags: ['Branches'],
        summary: 'Update urutan foto cabang',
        description: 'Access: Pemilik Kos only. Mengubah urutan tampilan foto cabang.',
        operationId: 'updateBranchPhoto',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch_photo', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['order'], properties: [
            new OA\Property(property: 'order', type: 'integer', example: 1),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, BranchPhoto $branchPhoto): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'integer', 'min:0'],
        ]);

        $branchPhoto->update($validated);

        return response()->json([
            'message' => 'Foto cabang berhasil diupdate.',
            'data' => $branchPhoto,
        ]);
    }

    #[OA\Delete(
        path: '/branch-photos/{branch_photo}',
        tags: ['Branches'],
        summary: 'Hapus foto cabang',
        description: 'Access: Pemilik Kos only. Menghapus foto cabang dari data dan storage.',
        operationId: 'deleteBranchPhoto',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch_photo', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(BranchPhoto $branchPhoto): JsonResponse
    {
        if ($branchPhoto->photo) {
            Storage::disk('public')->delete($branchPhoto->photo);
        }

        $branchPhoto->delete();

        return response()->json([
            'message' => 'Foto cabang berhasil dihapus.',
        ]);
    }
}
