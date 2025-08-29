<?php

namespace App\V5\Modules\Renting\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Renting\Services\RentingService;
use App\Http\Resources\API\V5\RentingEquipmentV5Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentingController extends Controller
{
    public function __construct(private RentingService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $filters = $request->except(['page', 'limit']);
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $paginator = $this->service->list($schoolId, $seasonId, $filters, $page, $limit);
        return response()->json([
            'data' => RentingEquipmentV5Resource::collection($paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->find($id, $schoolId, $seasonId);
        return RentingEquipmentV5Resource::make($item)->response();
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
