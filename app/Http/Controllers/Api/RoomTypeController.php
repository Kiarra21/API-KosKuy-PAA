<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomTypeController extends Controller
{
    #[OA\Get(
        path: '/room-types',
        tags: ['Rooms'],
        summary: 'List tipe kamar',
        description: 'Access: Semua user login. Menampilkan daftar tipe kamar beserta cabang, fasilitas, foto, dan jumlah kamar.',
        operationId: 'listRoomTypes',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'branch_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = RoomType::query()
            ->with(['branch:id,name,address,latitude,longitude', 'facilities:id,name', 'photos'])
            ->withCount([
                'rooms',
                'rooms as available_rooms_count' => fn ($query) => $query->where('is_active', true)->where('is_filled', false),
            ])
            ->latest();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'data' => $query->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/room-types',
        tags: ['Rooms'],
        summary: 'Buat tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Membuat tipe kamar pada cabang tertentu, termasuk harga, ukuran, dan fasilitas kamar.',
        operationId: 'createRoomType',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['branch_id', 'name', 'description', 'price', 'room_size'],
                properties: [
                    new OA\Property(property: 'branch_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Kamar Standard'),
                    new OA\Property(property: 'description', type: 'string', example: 'Kamar nyaman dengan kamar mandi luar'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 750000),
                    new OA\Property(property: 'room_size', type: 'integer', example: 12),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'facility_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'room_size' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'facility_ids' => ['sometimes', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ]);

        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);

        $roomType = RoomType::create($validated);

        if (! empty($facilityIds)) {
            $roomType->facilities()->sync(array_values(array_unique($facilityIds)));
        }

        return response()->json([
            'message' => 'Tipe kamar berhasil dibuat.',
            'data' => $roomType->load(['branch', 'facilities', 'photos']),
        ], 201);
    }

    #[OA\Get(
        path: '/room-types/{room_type}',
        tags: ['Rooms'],
        summary: 'Detail tipe kamar',
        description: 'Access: Semua user login. Menampilkan detail tipe kamar, cabang, fasilitas, foto, dan daftar kamar.',
        operationId: 'showRoomType',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(RoomType $roomType): JsonResponse
    {
        return response()->json([
            'data' => $roomType->load(['branch', 'facilities', 'photos', 'rooms']),
        ]);
    }

    #[OA\Put(
        path: '/room-types/{room_type}',
        tags: ['Rooms'],
        summary: 'Update tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Mengubah informasi tipe kamar, harga, ukuran, status aktif, dan fasilitas kamar.',
        operationId: 'updateRoomType',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'branch_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float'),
                    new OA\Property(property: 'room_size', type: 'integer'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'facility_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:branches,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'room_size' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'facility_ids' => ['sometimes', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ]);

        $hasFacilityIds = array_key_exists('facility_ids', $validated);
        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);

        $roomType->update($validated);

        if ($hasFacilityIds) {
            $roomType->facilities()->sync(array_values(array_unique($facilityIds)));
        }

        return response()->json([
            'message' => 'Tipe kamar berhasil diupdate.',
            'data' => $roomType->load(['branch', 'facilities', 'photos']),
        ]);
    }

    #[OA\Delete(
        path: '/room-types/{room_type}',
        tags: ['Rooms'],
        summary: 'Hapus tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Menghapus tipe kamar beserta kamar, foto, dan relasi fasilitasnya.',
        operationId: 'deleteRoomType',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(RoomType $roomType): JsonResponse
    {
        $roomType->delete();

        return response()->json([
            'message' => 'Tipe kamar berhasil dihapus.',
        ]);
    }
}
