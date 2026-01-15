<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\EvaluationComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationCommentController extends AppBaseController
{
    public function index($id, Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 200);
        $limit = $limit > 500 ? 500 : $limit;

        $comments = EvaluationComment::query()
            ->with('user')
            ->where('evaluation_id', $id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->sendResponse($comments, 'Evaluation comments retrieved successfully');
    }

    public function store($id, Request $request): JsonResponse
    {
        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $user = $request->user() ?? auth('sanctum')->user();

        $comment = EvaluationComment::create([
            'evaluation_id' => $id,
            'user_id' => $user?->id,
            'comment' => $request->input('comment'),
        ]);

        return $this->sendResponse($comment, 'Evaluation comment saved successfully');
    }
}
