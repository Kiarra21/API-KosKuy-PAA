<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BranchFacilityController extends Controller
{
    #[OA\Get(
        path: '/branches/{branch}/facilities',
        tags: ['Branches'],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'branch', type: 'object', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')]),
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [new OA\Property(property: 'id', type: 'integer'), new OA\Property(property: 'name', type: 'string')]))
        ]))]
    )]
    public function index(Branch $branch): JsonResponse
    {
        $branchData = ['id' => $branch->id, 'name' => $branch->name];
        $facilities = $branch->facilities()->get(['facilities.id', 'facilities.name'])->map(function ($f) {
            return ['id' => $f->id, 'name' => $f->name];
        });

        return response()->json(['branch' => $branchData, 'data' => $facilities]);
    }
    
    #[OA\Post(
        path: '/branches/{branch}/facilities',
        tags: ['Branches'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'facility_id', type: 'integer'),
            new OA\Property(property: 'facility_ids', type: 'array', items: new OA\Items(type: 'integer')),
        ])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => ['sometimes', 'integer', 'exists:facilities,id'],
            'facility_ids' => ['sometimes', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ]);

        $ids = [];
        if (!empty($data['facility_id'])) {
            $ids[] = $data['facility_id'];
        }
        if (!empty($data['facility_ids'])) {
            $ids = array_merge($ids, $data['facility_ids']);
        }

        if (empty($ids)) {
            return response()->json(['message' => 'No facility id provided.'], 422);
        }
        $branch->facilities()->syncWithoutDetaching(array_values(array_unique($ids)));
        return response()->json(['message' => 'Facilities attached.', 'data' => $branch->facilities()->pluck('facilities.id')], 201);
    }

    #[OA\Put(
        path: '/branches/{branch}/facilities',
        tags: ['Branches'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'facility_ids', type: 'array', items: new OA\Items(type: 'integer')),
        ])),
        responses: [new OA\Response(response: 200, description: 'Updated')]
    )]
    public function update(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'facility_ids' => ['required', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ]);

        $branch->facilities()->sync(array_values(array_unique($data['facility_ids'])));
        return response()->json(['message' => 'Facilities updated.', 'data' => $branch->facilities()->pluck('facilities.id')]);
    }

    #[OA\Delete(
        path: '/branches/{branch}/facilities',
        tags: ['Branches'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'branch', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'facility_id', type: 'integer'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Deleted')]
    )]
    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $branch->facilities()->detach($data['facility_id']);
        return response()->json(['message' => 'Facility detached.', 'data' => $branch->facilities()->pluck('facilities.id')]);
    }
}
