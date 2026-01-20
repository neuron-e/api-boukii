<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\EvaluationComment;
use App\Models\EvaluationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaluationCommentController extends AppBaseController
{
    public function index($id, Request $request): JsonResponse
    {
        $limit = (int) $request->get('limit', 200);
        $limit = $limit > 500 ? 500 : $limit;

        $comments = EvaluationComment::query()
            ->with(['user', 'monitor'])
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
            'monitor_id' => $this->resolveMonitorId($user),
            'comment' => $request->input('comment'),
        ]);

        EvaluationHistory::create([
            'evaluation_id' => $id,
            'user_id' => $user?->id,
            'monitor_id' => $this->resolveMonitorId($user),
            'type' => 'comment_added',
            'payload' => array_merge([
                'comment_id' => $comment->id,
                'comment' => $comment->comment,
            ], $this->getCourseContext($request)),
        ]);

        return $this->sendResponse($comment, 'Evaluation comment saved successfully');
    }

    private function getCourseContext(Request $request): array
    {
        $payload = [];
        $courseId = $request->input('course_id');
        $courseName = $request->input('course_name');

        if ($courseId) {
            $payload['course_id'] = $courseId;
        }

        if ($courseName) {
            $payload['course_name'] = $courseName;
        }

        return $payload;
    }

    private function resolveMonitorId(?\App\Models\User $user): ?int
    {
        if (!$user) {
            return null;
        }

        $type = $user->type;
        if ($type !== 3 && $type !== 'monitor') {
            return null;
        }

        return $user->monitors()
            ->orderByDesc('active_school')
            ->orderByDesc('id')
            ->value('id');
    }
}
