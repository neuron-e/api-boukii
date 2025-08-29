<?php

namespace App\V5\Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Course\Services\CourseService;
use App\Http\Requests\API\V5\Course\CreateCourseV5Request;
use App\Http\Requests\API\V5\Course\UpdateCourseV5Request;
use App\Http\Resources\API\V5\CourseV5Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private CourseService $service)
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
            'data' => CourseV5Resource::collection($paginator->items()),
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
        return CourseV5Resource::make($item)->response();
    }

    public function store(CreateCourseV5Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->create($request->validated(), $schoolId, $seasonId);
        return CourseV5Resource::make($item)->response()->setStatusCode(201);
    }

    public function update(int $id, UpdateCourseV5Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->update($id, $request->validated(), $schoolId, $seasonId);
        return CourseV5Resource::make($item)->response();
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $deleted = $this->service->delete($id, $schoolId, $seasonId);
        return response()->json(['deleted' => $deleted]);
    }
}
