<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Activity;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationLogController extends AppBaseController
{
    public function index($id, Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 50);
        $limit = $limit > 200 ? 200 : $limit;

        $logs = Activity::query()
            ->with('causer')
            ->where('subject_type', Evaluation::class)
            ->where('subject_id', $id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->sendResponse($logs, 'Evaluation logs retrieved successfully');
    }
}
