<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\EvaluationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationHistoryController extends AppBaseController
{
    public function index($id, Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 500);
        $limit = $limit > 1000 ? 1000 : $limit;

        $history = EvaluationHistory::query()
            ->with('user')
            ->where('evaluation_id', $id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->sendResponse($history, 'Evaluation history retrieved successfully');
    }
}
