<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FacilityController extends Controller
{
    #[OA\Get(
        path: '/facilities',
        tags: ['Facilities'],
        summary: 'List fasilitas',
        description: 'Access: All authenticated roles (Admin, Pemilik Kos, Customer)',
        operationId: 'listFacilities',
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Facility::query()->latest()->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/facilities',
        tags: ['Facilities'],
        summary: 'Buat fasilitas baru',
        description: 'Access: Pemilik Kos only',
        operationId: 'createFacility',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [new OA\Property(property: 'name', type: 'string', example: 'Parkir')]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $facility = Facility::create($validated);

        return response()->json([
            'message' => 'Fasilitas berhasil dibuat.',
            'data' => $facility,
        ], 201);
    }

    public function show(Facility $facility): JsonResponse
    {
        return response()->json([
            'data' => $facility,
        ]);
    }

    #[OA\Put(
        path: '/facilities/{id}',
        tags: ['Facilities'],
        summary: 'Update fasilitas',
        description: 'Access: Pemilik Kos only',
        operationId: 'updateFacility',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [new OA\Property(property: 'name', type: 'string')]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]

    public function update(Request $request, Facility $facility): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $facility->update($validated);

        return response()->json([
            'message' => 'Fasilitas berhasil diupdate.',
            'data' => $facility,
        ]);
    }

    #[OA\Delete(
        path: '/facilities/{id}',
        tags: ['Facilities'],
        summary: 'Hapus fasilitas',
        description: 'Access: Pemilik Kos only',
        operationId: 'deleteFacility',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]

    public function destroy(Facility $facility): JsonResponse
    {
        $facility->delete();

        return response()->json([
            'message' => 'Fasilitas berhasil dihapus.',
        ]);
    }
}
