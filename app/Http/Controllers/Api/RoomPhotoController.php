<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomPhoto;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class RoomPhotoController extends Controller
{
    #[OA\Get(
        path: '/room-types/{room_type}/photos',
        tags: ['Rooms'],
        summary: 'List foto tipe kamar',
        description: 'Access: Semua user login. Menampilkan semua foto untuk tipe kamar tertentu.',
        operationId: 'listRoomTypePhotos',
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
            'data' => $roomType->photos,
        ]);
    }

    #[OA\Post(
        path: '/room-types/{room_type}/photos',
        tags: ['Rooms'],
        summary: 'Upload foto tipe kamar',
        description: 'Access: Admin dan Pemilik Kos. Mengunggah foto tipe kamar dari kamera atau galeri.',
        operationId: 'uploadRoomTypePhoto',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_type', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
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
    public function store(Request $request, RoomType $roomType): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $path = $request->file('photo')->store('room-photos', 'public');

        $photo = RoomPhoto::create([
            'room_type_id' => $roomType->id,
            'photo' => $path,
            'order' => $validated['order'] ?? (($roomType->photos()->max('order') ?? 0) + 1),
        ]);

        return response()->json([
            'message' => 'Foto kamar berhasil diupload.',
            'data' => $photo,
        ], 201);
    }

    #[OA\Put(
        path: '/room-photos/{room_photo}',
        tags: ['Rooms'],
        summary: 'Update urutan foto kamar',
        description: 'Access: Admin dan Pemilik Kos. Mengubah urutan tampilan foto kamar.',
        operationId: 'updateRoomPhoto',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'order', type: 'integer'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, RoomPhoto $roomPhoto): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'integer', 'min:0'],
        ]);

        $roomPhoto->update($validated);

        return response()->json([
            'message' => 'Foto kamar berhasil diupdate.',
            'data' => $roomPhoto,
        ]);
    }

    #[OA\Delete(
        path: '/room-photos/{room_photo}',
        tags: ['Rooms'],
        summary: 'Hapus foto kamar',
        description: 'Access: Admin dan Pemilik Kos. Menghapus foto tipe kamar dari data dan storage.',
        operationId: 'deleteRoomPhoto',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'room_photo', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(RoomPhoto $roomPhoto): JsonResponse
    {
        if ($roomPhoto->photo) {
            Storage::disk('public')->delete($roomPhoto->photo);
        }

        $roomPhoto->delete();

        return response()->json([
            'message' => 'Foto kamar berhasil dihapus.',
        ]);
    }
}
