<?php

namespace App\V5\Modules\Renting\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Renting\Services\RentingItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\API\V5\RentingItemV5Resource;

class ItemController extends Controller
{
    /**
     * @OA\Tag(name="V5 Renting Items")
     */
    public function __construct(private RentingItemService $service)
    {
    }

    /**
     * @OA\Get(
     *   path="/api/v5/renting/items",
     *   summary="List inventory items",
     *   tags={"V5 Renting Items"}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $filters = $request->only(['active', 'category_id', 'search']);
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 50);
        $paginator = $this->service->list($schoolId, $filters, $page, $limit);
        return response()->json([
            'data' => RentingItemV5Resource::collection($paginator->items()),
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
     *   path="/api/v5/renting/items/{id}",
     *   summary="Get inventory item",
     *   tags={"V5 Renting Items"}
     * )
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $item = $this->service->find($id, $schoolId);
        return RentingItemV5Resource::make($item)->response();
    }

    /**
     * @OA\Post(
     *   path="/api/v5/renting/items",
     *   summary="Create inventory item",
     *   tags={"V5 Renting Items"}
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'sometimes|nullable|string|max:100',
            'description' => 'sometimes|nullable|string',
            'category_id' => 'required|integer|exists:v5_renting_categories,id',
            'base_daily_rate' => 'required|numeric|min:0',
            'deposit' => 'sometimes|numeric|min:0',
            'currency' => 'required|string|size:3',
            'inventory_count' => 'required|integer|min:0',
            'attributes' => 'sometimes|array',
            'active' => 'sometimes|boolean',
        ]);
        $item = $this->service->create($data, $schoolId);
        return RentingItemV5Resource::make($item)->response()->setStatusCode(201);
    }

    /**
     * @OA\Patch(
     *   path="/api/v5/renting/items/{id}",
     *   summary="Update inventory item",
     *   tags={"V5 Renting Items"}
     * )
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|nullable|string|max:100',
            'description' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|integer|exists:v5_renting_categories,id',
            'base_daily_rate' => 'sometimes|numeric|min:0',
            'deposit' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'inventory_count' => 'sometimes|integer|min:0',
            'attributes' => 'sometimes|array',
            'active' => 'sometimes|boolean',
        ]);
        $item = $this->service->update($id, $data, $schoolId);
        return RentingItemV5Resource::make($item)->response();
    }

    /**
     * @OA\Delete(
     *   path="/api/v5/renting/items/{id}",
     *   summary="Delete inventory item",
     *   tags={"V5 Renting Items"}
     * )
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $deleted = $this->service->delete($id, $schoolId);
        return response()->json(['deleted' => $deleted]);
    }
}
