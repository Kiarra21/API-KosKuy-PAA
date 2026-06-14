<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReviewController extends Controller
{
    #[OA\Get(
        path: '/reviews',
        tags: ['Reviews'],
        summary: 'List semua review',
        description: 'Access: Semua user login. Admin/pemilik kos dapat melihat semua review, customer hanya melihat review yang tidak disembunyikan.',
        operationId: 'listReviews',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Review::query()->with(['user:id,name,profile_picture', 'booking.roomType.branch']);

        // Jika bukan admin atau pemilik kos, hanya tampilkan yang tidak di-hide (invisible = false)
        if ($user->role !== 'admin' && $user->role !== 'pemilik_kos') {
            $query->where('invisible', false);
        }

        return response()->json([
            'data' => $query->latest()->paginate(10),
        ]);
    }

    #[OA\Post(
        path: '/reviews',
        tags: ['Reviews'],
        summary: 'Buat review baru untuk pemesanan (booking)',
        description: 'Access: Customer pemilik booking. Ulasan hanya bisa dibuat untuk booking yang berstatus confirmed atau completed.',
        operationId: 'createReview',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['booking_id', 'rating'],
                properties: [
                    new OA\Property(property: 'booking_id', type: 'integer', example: 1),
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 5),
                    new OA\Property(property: 'comment', type: 'string', example: 'Kamarnya sangat bersih dan nyaman!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 400, description: 'Bad Request'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        if ($user->role !== 'customer') {
            return response()->json([
                'message' => 'Hanya customer yang dapat memberikan ulasan.',
            ], 403);
        }

        // Pastikan booking milik user yang login
        if ((int) $booking->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk memberikan ulasan pada pemesanan ini.',
            ], 403);
        }

        // Pastikan status booking sudah confirmed atau completed
        if (!in_array($booking->status, ['confirmed', 'completed'])) {
            return response()->json([
                'message' => 'Anda hanya bisa memberikan ulasan untuk pemesanan yang telah dikonfirmasi atau selesai.',
            ], 400);
        }

        // Pastikan belum pernah di-review sebelumnya
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json([
                'message' => 'Pemesanan ini sudah diberikan ulasan sebelumnya.',
            ], 400);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'invisible' => false,
        ]);

        return response()->json([
            'message' => 'Ulasan berhasil ditambahkan.',
            'data' => $review->load(['user:id,name,profile_picture', 'booking.roomType.branch']),
        ], 201);
    }

    #[OA\Get(
        path: '/reviews/{id}',
        tags: ['Reviews'],
        summary: 'Detail review',
        description: 'Access: Semua user login',
        operationId: 'showReview',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Not Found')
        ]
    )]
    public function show(Review $review): JsonResponse
    {
        $user = auth()->user();
        if ($review->invisible && $user->role !== 'admin' && $user->role !== 'pemilik_kos') {
            return response()->json(['message' => 'Ulasan ini tidak tersedia.'], 404);
        }

        return response()->json([
            'data' => $review->load(['user:id,name,profile_picture', 'booking.roomType.branch']),
        ]);
    }

    #[OA\Put(
        path: '/reviews/{id}',
        tags: ['Reviews'],
        summary: 'Update review',
        description: 'Access: Customer pembuat review',
        operationId: 'updateReview',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5, example: 4),
                    new OA\Property(property: 'comment', type: 'string', example: 'Ternyata AC agak kurang dingin di siang hari.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found')
        ]
    )]
    public function update(Request $request, Review $review): JsonResponse
    {
        if ((int) $review->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak diizinkan untuk mengubah ulasan ini.',
            ], 403);
        }

        $validated = $request->validate([
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $review->update($validated);

        return response()->json([
            'message' => 'Ulasan berhasil diperbarui.',
            'data' => $review->load(['user:id,name,profile_picture', 'booking.roomType.branch']),
        ]);
    }

    #[OA\Delete(
        path: '/reviews/{id}',
        tags: ['Reviews'],
        summary: 'Hapus review',
        description: 'Access: Customer pembuat ulasan, atau Admin, atau Pemilik Kos.',
        operationId: 'deleteReview',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
    public function destroy(Review $review): JsonResponse
    {
        $user = auth()->user();

        // Hanya pemilik ulasan, admin, atau pemilik kos yang dapat menghapus ulasan
        if ((int) $review->user_id !== (int) $user->id && $user->role !== 'admin' && $user->role !== 'pemilik_kos') {
            return response()->json([
                'message' => 'Anda tidak diizinkan untuk menghapus ulasan ini.',
            ], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Ulasan berhasil dihapus.',
        ]);
    }

    #[OA\Get(
        path: '/branches/{branch}/reviews',
        tags: ['Reviews'],
        summary: 'List review untuk cabang kos tertentu',
        description: 'Access: Semua user login. Menampilkan ulasan publik untuk satu cabang kos.',
        operationId: 'branchReviews',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function branchReviews(Branch $branch): JsonResponse
    {
        $baseQuery = Review::whereHas('booking.roomType', function ($query) use ($branch) {
            $query->where('branch_id', $branch->id);
        })->where('invisible', false);

        $stats = (clone $baseQuery)
            ->selectRaw('ROUND(AVG(rating), 2) as average_rating, COUNT(*) as total_reviews')
            ->first();

        $starCounts = (clone $baseQuery)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $reviews = (clone $baseQuery)
            ->with(['user:id,name,profile_picture', 'booking.roomType'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'stats' => [
                'average_rating' => (float) ($stats->average_rating ?? 0),
                'total_reviews' => (int) ($stats->total_reviews ?? 0),
                'star_counts' => [
                    '1' => $starCounts[1] ?? 0,
                    '2' => $starCounts[2] ?? 0,
                    '3' => $starCounts[3] ?? 0,
                    '4' => $starCounts[4] ?? 0,
                    '5' => $starCounts[5] ?? 0,
                ],
            ],
            'data' => $reviews,
        ]);
    }

    #[OA\Put(
        path: '/reviews/{id}/toggle-visibility',
        tags: ['Reviews'],
        summary: 'Sembunyikan/tampilkan ulasan (Toggle Invisible)',
        description: 'Access: Admin atau Pemilik Kos saja.',
        operationId: 'toggleReviewVisibility',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
    public function toggleVisibility(Review $review): JsonResponse
    {
        $review->update([
            'invisible' => !$review->invisible,
        ]);

        $statusMessage = $review->invisible ? 'disembunyikan' : 'ditampilkan';

        return response()->json([
            'message' => "Visibilitas ulasan berhasil diubah. Ulasan sekarang {$statusMessage}.",
            'data' => $review,
        ]);
    }
}
