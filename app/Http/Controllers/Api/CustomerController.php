<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    #[OA\Get(
        path: '/customers',
        tags: ['Users'],
        summary: 'List customer',
        description: 'Access: Pemilik Kos only. Menampilkan daftar user dengan role customer.',
        operationId: 'listCustomers',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', 'customer')
            ->latest();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->paginate(10),
        ]);
    }

    #[OA\Get(
        path: '/customers/{id}',
        tags: ['Users'],
        summary: 'Detail customer',
        description: 'Access: Pemilik Kos only. Menampilkan detail satu customer.',
        operationId: 'showCustomer',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(User $customer): JsonResponse
    {
        if ($customer->role !== 'customer') {
            return response()->json([
                'message' => 'Customer tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => $customer,
        ]);
    }

    #[OA\Put(
        path: '/customers/{id}/status',
        tags: ['Users'],
        summary: 'Update status customer',
        description: 'Access: Pemilik Kos only. Mengaktifkan atau menonaktifkan akun customer.',
        operationId: 'updateCustomerStatus',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, example: 1, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['is_active'], properties: [
            new OA\Property(property: 'is_active', type: 'boolean', example: true),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function updateStatus(Request $request, User $customer): JsonResponse
    {
        if ($customer->role !== 'customer') {
            return response()->json([
                'message' => 'Customer tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Status customer berhasil diupdate.',
            'data' => $customer,
        ]);
    }
}
