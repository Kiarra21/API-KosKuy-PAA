<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class BookingController extends Controller
{
    #[OA\Get(
        path: '/bookings',
        tags: ['Bookings'],
        summary: 'Daftar pemesanan',
        description: 'Access: Semua user login. Customer hanya melihat pesanan sendiri, Admin melihat pesanan di cabangnya, Pemilik melihat semua.',
        operationId: 'listBookings',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'cancelled', 'completed'])),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Booking::with(['user', 'roomType.branch', 'room', 'payment', 'review']);

        if ($user->role === 'customer') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'admin') {
            if ($user->branch_id) {
                $query->whereHas('roomType', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            } else {
                // Admin tanpa cabang tidak bisa melihat booking
                $query->whereRaw('1 = 0');
            }
        } // pemilik_kos can see all

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json([
            'data' => $query->latest()->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/bookings',
        tags: ['Bookings'],
        summary: 'Buat pemesanan baru',
        description: 'Access: Customer (untuk diri sendiri), Admin/Pemilik (bisa memesankan untuk customer lain).',
        operationId: 'createBooking',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['room_type_id', 'check_in_date', 'check_out_date'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', description: 'ID User Customer (wajib jika pemesan adalah Admin/Pemilik)'),
                    new OA\Property(property: 'room_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'check_in_date', type: 'string', format: 'date', example: '2026-06-10'),
                    new OA\Property(property: 'check_out_date', type: 'string', format: 'date', example: '2026-06-17'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Minta lantai bawah'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validasi gagal / Kamar penuh')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'room_type_id' => ['required', 'exists:room_types,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'notes' => ['nullable', 'string'],
        ];

        if ($user->role === 'customer') {
            $customerId = $user->id;
        } else {
            $rules['user_id'] = ['required', 'exists:users,id'];
            $customerId = $request->input('user_id');
        }

        $validated = $request->validate($rules);

        // Validasi batas maksimal pending (maksimal 3)
        $pendingCount = Booking::where('user_id', $customerId)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount >= 3) {
            return response()->json([
                'message' => 'Anda memiliki batas maksimal 3 pemesanan tertunda (pending). Silakan lakukan pembayaran terlebih dahulu.',
            ], 422);
        }

        // Validasi batas maksimal pembatalan per hari (maksimal 5)
        $cancelledTodayCount = Booking::where('user_id', $customerId)
            ->where('status', 'cancelled')
            ->whereDate('updated_at', Carbon::today())
            ->count();

        if ($cancelledTodayCount >= 5) {
            return response()->json([
                'message' => 'Anda telah membatalkan 5 pemesanan hari ini. Anda tidak dapat membuat pemesanan baru lagi untuk hari ini.',
            ], 422);
        }

        $roomType = RoomType::findOrFail($validated['room_type_id']);

        // Fase 1: Validasi Kapasitas Kamar
        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);
        $totalNights = $checkIn->diffInDays($checkOut);

        if ($totalNights < 1) {
            return response()->json([
                'message' => 'Pemesanan minimal harus 1 malam.',
            ], 422);
        }

        $totalRoomsCount = Room::where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->count();

        if ($totalRoomsCount === 0) {
            return response()->json([
                'message' => 'Tipe kamar ini tidak memiliki kamar aktif yang tersedia.',
            ], 422);
        }

        // Loop day by day untuk mengecek ketersediaan tipe kamar
        for ($date = $checkIn->copy(); $date->lt($checkOut); $date->addDay()) {
            $dateStr = $date->toDateString();
            $activeBookingsOnDate = Booking::where('room_type_id', $roomType->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('check_in_date', '<=', $dateStr)
                ->where('check_out_date', '>', $dateStr)
                ->count();

            if ($activeBookingsOnDate >= $totalRoomsCount) {
                return response()->json([
                    'message' => "Tipe kamar ini sudah penuh pada tanggal {$dateStr}.",
                ], 422);
            }
        }

        // Hitung total harga
        $totalPrice = $roomType->price * $totalNights;

        $booking = Booking::create([
            'user_id' => $customerId,
            'room_type_id' => $roomType->id,
            'room_id' => null, // Kamar ditentukan admin nanti
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'total_nights' => $totalNights,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Pemesanan berhasil dibuat, silakan lakukan pembayaran.',
            'data' => $booking->load(['user', 'roomType.branch']),
        ], 201);
    }

    #[OA\Get(
        path: '/bookings/{id}',
        tags: ['Bookings'],
        summary: 'Detail pemesanan',
        description: 'Access: Semua user login. Customer hanya melihat pesanan sendiri, Admin melihat pesanan di cabangnya, Pemilik melihat semua.',
        operationId: 'showBooking',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(Booking $booking): JsonResponse
    {
        $this->authorizeBookingAccess($booking);

        return response()->json([
            'data' => $booking->load(['user', 'roomType.branch', 'room', 'payment']),
        ]);
    }

    #[OA\Post(
        path: '/bookings/{id}/cancel',
        tags: ['Bookings'],
        summary: 'Batalkan pemesanan',
        description: 'Access: Customer (untuk pesanan sendiri yang masih pending/confirmed), Admin/Pemilik (untuk semua pesanan).',
        operationId: 'cancelBooking',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function cancel(Booking $booking): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeBookingAccess($booking);

        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Pemesanan ini sudah dibatalkan.',
            ], 422);
        }

        if ($booking->status === 'completed') {
            return response()->json([
                'message' => 'Pemesanan yang sudah selesai tidak dapat dibatalkan.',
            ], 422);
        }

        // Customer hanya bisa cancel status pending atau confirmed
        if ($user->role === 'customer' && !in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Anda tidak dapat membatalkan pemesanan dengan status saat ini.',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        if ($booking->room_id) {
            $booking->room->update([
                'is_filled' => false,
            ]);
        }

        return response()->json([
            'message' => 'Pemesanan berhasil dibatalkan.',
            'data' => $booking->refresh(),
        ]);
    }

    #[OA\Post(
        path: '/bookings/{id}/confirm',
        tags: ['Bookings'],
        summary: 'Konfirmasi pemesanan dan alokasi kamar',
        description: 'Access: Admin (untuk cabangnya), Pemilik Kos (untuk semua cabang). Menentukan nomor kamar fisik.',
        operationId: 'confirmBooking',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['room_id'],
                properties: [
                    new OA\Property(property: 'room_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 422, description: 'Validasi gagal / Kamar tidak tersedia')
        ]
    )]
    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'pemilik_kos'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->authorizeBookingAccess($booking);

        if ($booking->status === 'cancelled') {
            return response()->json([
                'message' => 'Tidak dapat mengonfirmasi pemesanan yang sudah dibatalkan.',
            ], 422);
        }

        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
        ]);

        $room = Room::findOrFail($validated['room_id']);

        // Pastikan kamar sesuai dengan tipe yang dipesan
        if ((int) $room->room_type_id !== (int) $booking->room_type_id) {
            return response()->json([
                'message' => 'Nomor kamar tidak sesuai dengan tipe kamar yang dipesan.',
            ], 422);
        }

        // Pastikan kamar aktif
        if (!$room->is_active) {
            return response()->json([
                'message' => 'Kamar ini sedang tidak aktif.',
            ], 422);
        }

        // Fase 2: Validasi apakah kamar spesifik kosong pada tanggal tersebut
        $overlappingRoomBooking = Booking::where('room_id', $room->id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_in_date', '<', $booking->check_out_date)
            ->where('check_out_date', '>', $booking->check_in_date)
            ->exists();

        if ($overlappingRoomBooking) {
            return response()->json([
                'message' => 'Kamar ini sudah terisi atau dipesan oleh pelanggan lain pada tanggal tersebut.',
            ], 422);
        }

        $booking->update([
            'room_id' => $room->id,
            'status' => 'confirmed',
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        $room->update([
            'is_filled' => true,
        ]);

        return response()->json([
            'message' => 'Pemesanan berhasil dikonfirmasi dan kamar telah dialokasikan.',
            'data' => $booking->refresh()->load(['user', 'roomType', 'room']),
        ]);
    }

    private function authorizeBookingAccess(Booking $booking): void
    {
        $user = Auth::user();

        if ($user->role === 'customer' && (int) $booking->user_id !== (int) $user->id) {
            abort(403, 'Unauthorized action.');
        }

        if ($user->role === 'admin') {
            if (!$user->branch_id || (int) $booking->roomType->branch_id !== (int) $user->branch_id) {
                abort(403, 'Unauthorized action.');
            }
        }
    }
}
