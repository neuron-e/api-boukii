<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateEvaluationAPIRequest;
use App\Http\Requests\API\UpdateEvaluationAPIRequest;
use App\Http\Resources\API\EvaluationResource;
use App\Models\Evaluation;
use App\Repositories\EvaluationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class EvaluationController
 */

class EvaluationAPIController extends AppBaseController
{
    /** @var  EvaluationRepository */
    private $evaluationRepository;

    public function __construct(EvaluationRepository $evaluationRepo)
    {
        $this->evaluationRepository = $evaluationRepo;
    }

    /**
     * @OA\Get(
     *      path="/evaluations",
     *      summary="getEvaluationList",
     *      tags={"Evaluation"},
     *      description="Get all Evaluations",
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
     *                  @OA\Items(ref="#/components/schemas/Evaluation")
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
        $evaluations = $this->evaluationRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($evaluations, 'Evaluations retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/evaluations",
     *      summary="createEvaluation",
     *      tags={"Evaluation"},
     *      description="Create Evaluation",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Evaluation")
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
     *                  ref="#/components/schemas/Evaluation"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateEvaluationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $existing = Evaluation::query()
            ->where('client_id', $input['client_id'] ?? null)
            ->where('degree_id', $input['degree_id'] ?? null)
            ->first();

        if ($existing) {
            $evaluation = $this->evaluationRepository->update($input, $existing->id);
            return $this->sendResponse(new EvaluationResource($evaluation), 'Evaluation updated successfully');
        }

        $evaluation = $this->evaluationRepository->create($input);

        return $this->sendResponse($evaluation, 'Evaluation saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/evaluations/{id}",
     *      summary="getEvaluationItem",
     *      tags={"Evaluation"},
     *      description="Get Evaluation",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Evaluation",
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
     *                  ref="#/components/schemas/Evaluation"
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
        /** @var Evaluation $evaluation */
        $evaluation = $this->evaluationRepository->find($id, with: $request->get('with', []));

        if (empty($evaluation)) {
            return $this->sendError('Evaluation not found');
        }

        return $this->sendResponse($evaluation, 'Evaluation retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/evaluations/{id}",
     *      summary="updateEvaluation",
     *      tags={"Evaluation"},
     *      description="Update Evaluation",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Evaluation",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Evaluation")
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
     *                  ref="#/components/schemas/Evaluation"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateEvaluationAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Evaluation $evaluation */
        $evaluation = $this->evaluationRepository->find($id, with: $request->get('with', []));

        if (empty($evaluation)) {
            return $this->sendError('Evaluation not found');
        }

        $evaluation = $this->evaluationRepository->update($input, $id);

        return $this->sendResponse(new EvaluationResource($evaluation), 'Evaluation updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/evaluations/{id}",
     *      summary="deleteEvaluation",
     *      tags={"Evaluation"},
     *      description="Delete Evaluation",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Evaluation",
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
        /** @var Evaluation $evaluation */
        $evaluation = $this->evaluationRepository->find($id);

        if (empty($evaluation)) {
            return $this->sendError('Evaluation not found');
        }

        $evaluation->delete();

        return $this->sendSuccess('Evaluation deleted successfully');
    }
}
