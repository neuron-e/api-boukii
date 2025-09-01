<?php

namespace App\V5\Modules\Renting\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Renting\Services\RentingService;
use App\Http\Resources\API\V5\RentingEquipmentV5Resource;
use App\Http\Requests\API\V5\Renting\CreateRentingV5Request;
use App\Http\Requests\API\V5\Renting\UpdateRentingV5Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentingController extends Controller
{
    /**
     * @OA\Tag(name="V5 Renting")
     */
    public function __construct(private RentingService $service)
    {
    }

    /**
     * @OA\Get(
     *   path="/api/v5/renting",
     *   summary="List rented equipment (by booking) scoped by school/season",
     *   tags={"V5 Renting"}
     * )
     */
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

    /**
     * @OA\Get(
     *   path="/api/v5/renting/{id}",
     *   summary="Get equipment detail",
     *   tags={"V5 Renting"}
     * )
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->find($id, $schoolId, $seasonId);
        return RentingEquipmentV5Resource::make($item)->response();
    }

    /**
     * @OA\Post(
     *   path="/api/v5/renting",
     *   summary="Create equipment for a booking",
     *   tags={"V5 Renting"}
     * )
     */
    public function store(CreateRentingV5Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->create($request->validated(), $schoolId, $seasonId);
        return RentingEquipmentV5Resource::make($item)->response()->setStatusCode(201);
    }

    /**
     * @OA\Patch(
     *   path="/api/v5/renting/{id}",
     *   summary="Update equipment",
     *   tags={"V5 Renting"}
     * )
     */
    public function update(int $id, UpdateRentingV5Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->update($id, $request->validated(), $schoolId, $seasonId);
        return RentingEquipmentV5Resource::make($item)->response();
    }

    /**
     * @OA\Delete(
     *   path="/api/v5/renting/{id}",
     *   summary="Delete equipment",
     *   tags={"V5 Renting"}
     * )
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $deleted = $this->service->delete($id, $schoolId, $seasonId);
        return response()->json(['deleted' => $deleted]);
    }
}
