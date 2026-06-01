<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RoomTypeFacilityController extends Controller
{
    #[OA\Get(
        path: '/room-types/{room_type}/facilities',
        tags: ['Rooms'],
        summary: 'List fasilitas tipe kamar',
        description: 'Access: Semua user login. Menampilkan semua fasilitas yang dimiliki tipe kamar tertentu.',
        operationId: 'listRoomTypeFacilities',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(RoomType $roomType): JsonResponse
    {
        return response()->json([
            'room_type' => [
                'id' => $roomType->id,
                'name' => $roomType->name,
            ],
            'data' => $roomType->facilities()->get(['facilities.id', 'facilities.name']),
        ]);
    }

    #[OA\Post(
        path: '/room-types/{room_type}/facilities',
        tags: ['Rooms'],
        summary: 'Tambah fasilitas tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Menambahkan satu atau beberapa fasilitas ke tipe kamar tanpa menghapus fasilitas yang sudah ada.',
        operationId: 'attachRoomTypeFacilities',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'facility_id', type: 'integer'),
            new OA\Property(property: 'facility_ids', type: 'array', items: new OA\Items(type: 'integer')),
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request, RoomType $roomType): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => ['sometimes', 'integer', 'exists:facilities,id,deleted_at,NULL'],
            'facility_ids' => ['sometimes', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id,deleted_at,NULL'],
        ]);

        $ids = [];

        if (! empty($data['facility_id'])) {
            $ids[] = $data['facility_id'];
        }

        if (! empty($data['facility_ids'])) {
            $ids = array_merge($ids, $data['facility_ids']);
        }

        if (empty($ids)) {
            return response()->json([
                'message' => 'No facility id provided.',
            ], 422);
        }

        $roomType->facilities()->syncWithoutDetaching(array_values(array_unique($ids)));

        return response()->json([
            'message' => 'Facilities attached.',
            'data' => $roomType->facilities()->pluck('facilities.id'),
        ], 201);
    }

    #[OA\Put(
        path: '/room-types/{room_type}/facilities',
        tags: ['Rooms'],
        summary: 'Update fasilitas tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Mengganti seluruh daftar fasilitas pada tipe kamar dengan daftar fasilitas baru yang dikirim.',
        operationId: 'syncRoomTypeFacilities',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'facility_ids', type: 'array', items: new OA\Items(type: 'integer')),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $data = $request->validate([
            'facility_ids' => ['required', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id,deleted_at,NULL'],
        ]);

        $roomType->facilities()->sync(array_values(array_unique($data['facility_ids'])));

        return response()->json([
            'message' => 'Facilities updated.',
            'data' => $roomType->facilities()->pluck('facilities.id'),
        ]);
    }

    #[OA\Delete(
        path: '/room-types/{room_type}/facilities',
        tags: ['Rooms'],
        summary: 'Hapus fasilitas dari tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Menghapus satu fasilitas dari tipe kamar tanpa menghapus data fasilitas master.',
        operationId: 'detachRoomTypeFacility',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'facility_id', type: 'integer'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(Request $request, RoomType $roomType): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id,deleted_at,NULL'],
        ]);

        $roomType->facilities()->detach($data['facility_id']);

        return response()->json([
            'message' => 'Facility detached.',
            'data' => $roomType->facilities()->pluck('facilities.id'),
        ]);
    }
}
