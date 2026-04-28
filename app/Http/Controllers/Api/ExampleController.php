<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class ExampleController extends Controller
{
    #[OA\Get(
        path: '/api/ping',
        tags: ['Testing'],
        summary: 'Ping endpoint untuk test koneksi API',
        operationId: 'pingApi',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Response berhasil',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'pong'),
                        new OA\Property(property: 'timestamp', type: 'string', example: '2026-04-28T12:00:00+00:00'),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'pong',
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }
}
