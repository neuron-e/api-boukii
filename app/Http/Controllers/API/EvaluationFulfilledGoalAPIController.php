<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateEvaluationFulfilledGoalAPIRequest;
use App\Http\Requests\API\UpdateEvaluationFulfilledGoalAPIRequest;
use App\Http\Resources\API\EvaluationFulfilledGoalResource;
use App\Models\EvaluationFulfilledGoal;
use App\Models\EvaluationHistory;
use App\Repositories\EvaluationFulfilledGoalRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class EvaluationFulfilledGoalController
 */

class EvaluationFulfilledGoalAPIController extends AppBaseController
{
    /** @var  EvaluationFulfilledGoalRepository */
    private $evaluationFulfilledGoalRepository;

    public function __construct(EvaluationFulfilledGoalRepository $evaluationFulfilledGoalRepo)
    {
        $this->evaluationFulfilledGoalRepository = $evaluationFulfilledGoalRepo;
    }

    /**
     * @OA\Get(
     *      path="/evaluation-fulfilled-goals",
     *      summary="getEvaluationFulfilledGoalList",
     *      tags={"EvaluationFulfilledGoal"},
     *      description="Get all EvaluationFulfilledGoals",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/EvaluationFulfilledGoal")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $evaluationFulfilledGoals = $this->evaluationFulfilledGoalRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($evaluationFulfilledGoals, 'Evaluation Fulfilled Goals retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/evaluation-fulfilled-goals",
     *      summary="createEvaluationFulfilledGoal",
     *      tags={"EvaluationFulfilledGoal"},
     *      description="Create EvaluationFulfilledGoal",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/EvaluationFulfilledGoal")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/EvaluationFulfilledGoal"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateEvaluationFulfilledGoalAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $evaluationFulfilledGoal = $this->evaluationFulfilledGoalRepository->create($input);
        $this->logGoalHistory($evaluationFulfilledGoal, null, $request, 'goal_created');

        return $this->sendResponse($evaluationFulfilledGoal, 'Evaluation Fulfilled Goal saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/evaluation-fulfilled-goals/{id}",
     *      summary="getEvaluationFulfilledGoalItem",
     *      tags={"EvaluationFulfilledGoal"},
     *      description="Get EvaluationFulfilledGoal",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of EvaluationFulfilledGoal",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/EvaluationFulfilledGoal"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show($id, Request $request): JsonResponse
    {
        /** @var EvaluationFulfilledGoal $evaluationFulfilledGoal */
        $evaluationFulfilledGoal = $this->evaluationFulfilledGoalRepository->find($id, with: $request->get('with', []));

        if (empty($evaluationFulfilledGoal)) {
            return $this->sendError('Evaluation Fulfilled Goal not found');
        }

        return $this->sendResponse($evaluationFulfilledGoal, 'Evaluation Fulfilled Goal retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/evaluation-fulfilled-goals/{id}",
     *      summary="updateEvaluationFulfilledGoal",
     *      tags={"EvaluationFulfilledGoal"},
     *      description="Update EvaluationFulfilledGoal",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of EvaluationFulfilledGoal",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/EvaluationFulfilledGoal")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/EvaluationFulfilledGoal"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateEvaluationFulfilledGoalAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var EvaluationFulfilledGoal $evaluationFulfilledGoal */
        $evaluationFulfilledGoal = $this->evaluationFulfilledGoalRepository->find($id, with: $request->get('with', []));

        if (empty($evaluationFulfilledGoal)) {
            return $this->sendError('Evaluation Fulfilled Goal not found');
        }

        $previousScore = $evaluationFulfilledGoal->score;
        $evaluationFulfilledGoal = $this->evaluationFulfilledGoalRepository->update($input, $id);
        if ($previousScore !== $evaluationFulfilledGoal->score) {
            $this->logGoalHistory($evaluationFulfilledGoal, $previousScore, $request, 'goal_updated');
        }

        return $this->sendResponse(new EvaluationFulfilledGoalResource($evaluationFulfilledGoal), 'EvaluationFulfilledGoal updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/evaluation-fulfilled-goals/{id}",
     *      summary="deleteEvaluationFulfilledGoal",
     *      tags={"EvaluationFulfilledGoal"},
     *      description="Delete EvaluationFulfilledGoal",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of EvaluationFulfilledGoal",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function destroy($id): JsonResponse
    {
        /** @var EvaluationFulfilledGoal $evaluationFulfilledGoal */
        $evaluationFulfilledGoal = $this->evaluationFulfilledGoalRepository->find($id);

        if (empty($evaluationFulfilledGoal)) {
            return $this->sendError('Evaluation Fulfilled Goal not found');
        }

        $this->logGoalHistory($evaluationFulfilledGoal, $evaluationFulfilledGoal->score, request(), 'goal_deleted');
        $evaluationFulfilledGoal->delete();

        return $this->sendSuccess('Evaluation Fulfilled Goal deleted successfully');
    }

    private function logGoalHistory(
        EvaluationFulfilledGoal $goal,
        ?int $previousScore,
        Request $request,
        string $type
    ): void {
        $user = $request->user() ?? auth('sanctum')->user();

        EvaluationHistory::create([
            'evaluation_id' => $goal->evaluation_id,
            'user_id' => $user?->id,
            'monitor_id' => $this->resolveMonitorId($user),
            'type' => $type,
            'payload' => array_merge([
                'goal_id' => $goal->degrees_school_sport_goals_id,
                'score' => $goal->score,
                'previous_score' => $previousScore,
            ], $this->getCourseContext($request)),
        ]);
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
}
