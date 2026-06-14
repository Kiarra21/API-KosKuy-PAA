<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CheckInOut;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class CheckInOutController extends Controller
{
    #[OA\Post(
        path: '/bookings/{id}/check-in',
        tags: ['Check-In-Out'],
        summary: 'Proses Check-In',
        description: 'Access: Admin (untuk cabangnya), Pemilik Kos (untuk semua cabang). Melakukan check-in pelanggan.',
        operationId: 'checkInBooking',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'check_in_photo', type: 'string', format: 'binary', description: 'Foto saat check-in'),
                        new OA\Property(property: 'notes', type: 'string', description: 'Catatan tambahan'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validasi gagal / Tanggal belum sesuai / Status bukan confirmed')
        ]
    )]
    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdminAccess($booking);

        if ($booking->status !== 'confirmed') {
            return response()->json([
                'message' => 'Hanya pemesanan berstatus confirmed yang dapat melakukan check-in.',
            ], 422);
        }

        // Cek jika sudah check-in sebelumnya
        if ($booking->checkInOut()->whereNotNull('checked_in_at')->exists()) {
            return response()->json([
                'message' => 'Pemesanan ini sudah melakukan check-in.',
            ], 422);
        }

        // Validasi Tanggal Check-in
        $today = Carbon::today();
        if ($today->lt($booking->check_in_date)) {
            return response()->json([
                'message' => "Belum saatnya check-in. Tanggal check-in dijadwalkan pada: {$booking->check_in_date->toDateString()}.",
            ], 422);
        }

        if ($today->gte($booking->check_out_date)) {
            return response()->json([
                'message' => "Masa pemesanan sudah berakhir. Tanggal check-out dijadwalkan pada: {$booking->check_out_date->toDateString()}.",
            ], 422);
        }

        $request->validate([
            'check_in_photo' => ['nullable', 'image', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('check_in_photo')) {
            $path = $request->file('check_in_photo')->store('check-in-photos', 'public');
        }

        $checkInOut = CheckInOut::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'handled_by' => $user->id,
                'checked_in_at' => now(),
                'check_in_photo' => $path ?? null,
                'notes' => $request->input('notes'),
            ]
        );

        if ($booking->room) {
            $booking->room->update([
                'is_filled' => true,
            ]);
        }

        return response()->json([
            'message' => 'Check-in berhasil diproses.',
            'data' => $checkInOut->load('booking'),
        ]);
    }

    #[OA\Post(
        path: '/bookings/{id}/check-out',
        tags: ['Check-In-Out'],
        summary: 'Proses Check-Out',
        description: 'Access: Admin (untuk cabangnya), Pemilik Kos (untuk semua cabang). Melakukan check-out pelanggan, membebaskan kamar, dan mengubah status booking menjadi completed.',
        operationId: 'checkOutBooking',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'check_out_photo', type: 'string', format: 'binary', description: 'Foto saat check-out'),
                        new OA\Property(property: 'notes', type: 'string', description: 'Catatan tambahan'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validasi gagal / Belum check-in')
        ]
    )]
    public function checkOut(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAdminAccess($booking);

        $checkInOut = $booking->checkInOut;

        if (!$checkInOut || !$checkInOut->checked_in_at) {
            return response()->json([
                'message' => 'Pemesanan ini belum melakukan check-in.',
            ], 422);
        }

        if ($checkInOut->checked_out_at) {
            return response()->json([
                'message' => 'Pemesanan ini sudah melakukan check-out.',
            ], 422);
        }

        $request->validate([
            'check_out_photo' => ['nullable', 'image', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('check_out_photo')) {
            $path = $request->file('check_out_photo')->store('check-out-photos', 'public');
        }

        $currentNotes = $checkInOut->notes;
        if ($request->filled('notes')) {
            $currentNotes = $currentNotes 
                ? $currentNotes . "\nCheckout Notes: " . $request->input('notes')
                : "Checkout Notes: " . $request->input('notes');
        }

        $checkInOut->update([
            'handled_by' => $user->id,
            'checked_out_at' => now(),
            'check_out_photo' => $path ?? null,
            'notes' => $currentNotes,
        ]);

        $booking->update([
            'status' => 'completed',
        ]);

        if ($booking->room) {
            $booking->room->update([
                'is_filled' => false,
            ]);
        }

        return response()->json([
            'message' => 'Check-out berhasil diproses. Kamar telah dikosongkan.',
            'data' => $checkInOut->load('booking'),
        ]);
    }

    private function authorizeAdminAccess(Booking $booking): void
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'pemilik_kos'])) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->role === 'admin') {
            if (!$user->branch_id || (int) $booking->roomType->branch_id !== (int) $user->branch_id) {
                abort(403, 'Unauthorized action.');
            }
        }
    }
}
