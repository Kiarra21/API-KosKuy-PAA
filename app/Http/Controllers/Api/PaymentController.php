<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Post(
        path: '/payments/submit',
        tags: ['Payments'],
        summary: 'Submit bukti pembayaran',
        description: 'Access: Customer (untuk pesanannya sendiri). Mengunggah bukti transfer pembayaran.',
        operationId: 'submitPayment',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['booking_id', 'proof_image'],
                    properties: [
                        new OA\Property(property: 'booking_id', type: 'integer', example: 1),
                        new OA\Property(property: 'proof_image', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
            'proof_image' => ['required', 'image', 'max:2048'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        // Pastikan booking milik customer ini
        if ($user->role === 'customer' && (int) $booking->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Pastikan status booking masih pending/belum cancel/complete
        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Pemesanan ini sudah dibatalkan.',
            ], 422);
        }

        if ($booking->status === 'completed') {
            return response()->json([
                'message' => 'Pemesanan ini sudah selesai.',
            ], 422);
        }

        // Unggah gambar bukti
        $path = $request->file('proof_image')->store('payment-proofs', 'public');

        // Jika sebelumnya sudah ada bukti, hapus berkas lamanya
        if ($booking->payment && $booking->payment->proof_image) {
            Storage::disk('public')->delete($booking->payment->proof_image);
        }

        $payment = Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'code' => 'PAY-' . time() . '-' . rand(1000, 9999),
                'status' => 'pending',
                'proof_image' => $path,
                'rejection_reason' => null,
                'handled_by' => null,
                'handled_at' => null,
                'paid_at' => null,
            ]
        );

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi admin.',
            'data' => $payment,
        ]);
    }

    #[OA\Post(
        path: '/payments/{id}/approve',
        tags: ['Payments'],
        summary: 'Setujui pembayaran',
        description: 'Access: Admin (untuk cabangnya), Pemilik Kos (untuk semua cabang). Mengubah status pembayaran menjadi paid.',
        operationId: 'approvePayment',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function approve(Payment $payment): JsonResponse
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'pemilik_kos'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $booking = $payment->booking;

        // Validasi cabang admin
        if ($user->role === 'admin') {
            if (!$user->branch_id || $booking->roomType->branch_id !== $user->branch_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'message' => 'Pembayaran ini sudah disetujui.',
            ], 422);
        }

        $payment->update([
            'status' => 'paid',
            'handled_by' => $user->id,
            'handled_at' => now(),
            'paid_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'Pembayaran berhasil disetujui.',
            'data' => $payment->load('booking'),
        ]);
    }

    #[OA\Post(
        path: '/payments/{id}/reject',
        tags: ['Payments'],
        summary: 'Tolak pembayaran',
        description: 'Access: Admin (untuk cabangnya), Pemilik Kos (untuk semua cabang). Mengubah status pembayaran menjadi failed.',
        operationId: 'rejectPayment',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['rejection_reason'],
                properties: [
                    new OA\Property(property: 'rejection_reason', type: 'string', example: 'Gambar bukti bayar blur atau nominal tidak sesuai'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validasi gagal')
        ]
    )]
    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'pemilik_kos'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $booking = $payment->booking;

        // Validasi cabang admin
        if ($user->role === 'admin') {
            if (!$user->branch_id || $booking->roomType->branch_id !== $user->branch_id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        if ($payment->status === 'failed') {
            return response()->json([
                'message' => 'Pembayaran ini sudah ditolak.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $payment->update([
            'status' => 'failed',
            'handled_by' => $user->id,
            'handled_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'Pembayaran ditolak.',
            'data' => $payment->load('booking'),
        ]);
    }
}
