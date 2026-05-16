<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class RoomController extends Controller
{
    #[OA\Get(
        path: '/rooms',
        tags: ['Rooms'],
        summary: 'List kamar',
        description: 'Access: Semua user login. Menampilkan daftar kamar fisik beserta tipe kamar dan cabangnya.',
        operationId: 'listRooms',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'room_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'branch_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'is_filled', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Room::query()
            ->with(['roomType.branch'])
            ->latest();

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->integer('room_type_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('roomType', fn ($query) => $query->where('branch_id', $request->integer('branch_id')));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_filled')) {
            $query->where('is_filled', $request->boolean('is_filled'));
        }

        return response()->json([
            'data' => $query->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/rooms',
        tags: ['Rooms'],
        summary: 'Buat kamar',
        description: 'Access: Admin dan Pemilik Kos. Membuat kamar fisik berdasarkan tipe kamar dan menentukan status tersedia atau penuh.',
        operationId: 'createRoom',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['room_type_id', 'number'],
                properties: [
                    new OA\Property(property: 'room_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'number', type: 'integer', example: 101),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'is_filled', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('rooms')->where(fn ($query) => $query->where('room_type_id', $request->integer('room_type_id'))),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_filled' => ['sometimes', 'boolean'],
        ]);

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Kamar berhasil dibuat.',
            'data' => $room->load('roomType.branch'),
        ], 201);
    }

    #[OA\Get(
        path: '/rooms/{room}',
        tags: ['Rooms'],
        summary: 'Detail kamar',
        description: 'Access: Semua user login. Menampilkan detail kamar fisik, tipe kamar, cabang, fasilitas, dan foto tipe kamar.',
        operationId: 'showRoom',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(Room $room): JsonResponse
    {
        return response()->json([
            'data' => $room->load(['roomType.branch', 'roomType.facilities', 'roomType.photos']),
        ]);
    }

    #[OA\Put(
        path: '/rooms/{room}',
        tags: ['Rooms'],
        summary: 'Update kamar',
        description: 'Access: Admin dan Pemilik Kos. Mengubah nomor kamar, tipe kamar, status aktif, dan status penuh.',
        operationId: 'updateRoom',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'room_type_id', type: 'integer'),
                    new OA\Property(property: 'number', type: 'integer'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'is_filled', type: 'boolean'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, Room $room): JsonResponse
    {
        $roomTypeId = $request->integer('room_type_id') ?: $room->room_type_id;

        $validated = $request->validate([
            'room_type_id' => ['sometimes', 'required', 'integer', 'exists:room_types,id'],
            'number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('rooms')->where(fn ($query) => $query->where('room_type_id', $roomTypeId))->ignore($room->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_filled' => ['sometimes', 'boolean'],
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Kamar berhasil diupdate.',
            'data' => $room->load('roomType.branch'),
        ]);
    }

    #[OA\Delete(
        path: '/rooms/{room}',
        tags: ['Rooms'],
        summary: 'Hapus kamar',
        description: 'Access: Admin dan Pemilik Kos. Menghapus data kamar fisik.',
        operationId: 'deleteRoom',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json([
            'message' => 'Kamar berhasil dihapus.',
        ]);
    }
}
