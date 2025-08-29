<?php

namespace App\V5\Modules\Monitor\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Monitor\Services\MonitorService;
use App\Http\Requests\API\V5\Monitor\CreateMonitorV5Request;
use App\Http\Requests\API\V5\Monitor\UpdateMonitorV5Request;
use App\Http\Resources\API\V5\MonitorV5Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function __construct(private MonitorService $service)
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
            'data' => MonitorV5Resource::collection($paginator->items()),
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
        return MonitorV5Resource::make($item)->response();
    }

    public function store(CreateMonitorV5Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->create($request->validated(), $schoolId, $seasonId);
        return MonitorV5Resource::make($item)->response()->setStatusCode(201);
    }

    public function update(int $id, UpdateMonitorV5Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $item = $this->service->update($id, $request->validated(), $schoolId, $seasonId);
        return MonitorV5Resource::make($item)->response();
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $seasonId = (int) $request->get('context_season_id');
        $deleted = $this->service->delete($id, $schoolId, $seasonId);
        return response()->json(['deleted' => $deleted]);
    }
}
