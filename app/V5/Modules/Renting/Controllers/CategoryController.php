<?php

namespace App\V5\Modules\Renting\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Renting\Services\RentingCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * @OA\Tag(name="V5 Renting Categories")
     */
    public function __construct(private RentingCategoryService $service)
    {
    }

    /**
     * @OA\Get(
     *   path="/api/v5/renting/categories",
     *   summary="List categories or fetch category tree",
     *   tags={"V5 Renting Categories"}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        if ($request->boolean('tree')) {
            return response()->json(['data' => $this->service->tree($schoolId)]);
        }
        $filters = $request->only(['active', 'parent_id']);
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 50);
        $paginator = $this->service->list($schoolId, $filters, $page, $limit);
        return response()->json([
            'data' => $paginator->items(),
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
     *   path="/api/v5/renting/categories/{id}",
     *   summary="Get category detail",
     *   tags={"V5 Renting Categories"}
     * )
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $cat = $this->service->find($id, $schoolId);
        return response()->json(['data' => $cat]);
    }

    /**
     * @OA\Post(
     *   path="/api/v5/renting/categories",
     *   summary="Create category or subcategory",
     *   tags={"V5 Renting Categories"}
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'parent_id' => 'sometimes|nullable|integer|exists:v5_renting_categories,id',
            'position' => 'sometimes|nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);
        $cat = $this->service->create($data, $schoolId);
        return response()->json(['data' => $cat], 201);
    }

    /**
     * @OA\Patch(
     *   path="/api/v5/renting/categories/{id}",
     *   summary="Update category",
     *   tags={"V5 Renting Categories"}
     * )
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'parent_id' => 'sometimes|nullable|integer|exists:v5_renting_categories,id',
            'position' => 'sometimes|nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);
        $cat = $this->service->update($id, $data, $schoolId);
        return response()->json(['data' => $cat]);
    }

    /**
     * @OA\Delete(
     *   path="/api/v5/renting/categories/{id}",
     *   summary="Delete category",
     *   tags={"V5 Renting Categories"}
     * )
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $deleted = $this->service->delete($id, $schoolId);
        return response()->json(['deleted' => $deleted]);
    }
}
