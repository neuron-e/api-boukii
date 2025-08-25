<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateSeasonAPIRequest;
use App\Http\Requests\API\UpdateSeasonAPIRequest;
use App\Http\Resources\API\SeasonResource;
use App\Models\Season;
use App\Repositories\SeasonRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class SeasonController
 */

class SeasonAPIController extends AppBaseController
{
    /** @var  SeasonRepository */
    private $seasonRepository;

    public function __construct(SeasonRepository $seasonRepo)
    {
        $this->seasonRepository = $seasonRepo;
    }

    /**
     * @OA\Get(
     *      path="/seasons",
     *      summary="getSeasonList",
     *      tags={"Season"},
     *      description="Get all Seasons",
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
     *                  @OA\Items(ref="#/components/schemas/Season")
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
        $filters = $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']);

        if ($request->boolean('filterActive')) {
            $filters['is_active'] = true;
        }

        $seasons = $this->seasonRepository->all(
            $filters,
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($seasons, 'Seasons retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/seasons",
     *      summary="createSeason",
     *      tags={"Season"},
     *      description="Create Season",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Season")
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
     *                  ref="#/components/schemas/Season"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateSeasonAPIRequest $request): JsonResponse
    {
        $input = $request->all();
        if (!empty($input['is_active'])) {
            $this->seasonRepository->unsetActiveForSchool($input['school_id']);
        }

        $season = $this->seasonRepository->create($input);

        return $this->sendResponse($season, 'Season saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/seasons/{id}",
     *      summary="getSeasonItem",
     *      tags={"Season"},
     *      description="Get Season",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Season",
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
     *                  ref="#/components/schemas/Season"
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
        /** @var Season $season */
        $season = $this->seasonRepository->find($id, with: $request->get('with', []));

        if (empty($season)) {
            return $this->sendError('Season not found');
        }

        return $this->sendResponse($season, 'Season retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/seasons/{id}",
     *      summary="updateSeason",
     *      tags={"Season"},
     *      description="Update Season",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Season",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Season")
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
     *                  ref="#/components/schemas/Season"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateSeasonAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Season $season */
        $season = $this->seasonRepository->find($id, with: $request->get('with', []));

        if (empty($season)) {
            return $this->sendError('Season not found');
        }

        if (!empty($input['is_active'])) {
            $this->seasonRepository->unsetActiveForSchool($season->school_id, $season->id);
        }

        $season = $this->seasonRepository->update($input, $id);

        return $this->sendResponse(new SeasonResource($season), 'Season updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/seasons/{id}",
     *      summary="deleteSeason",
     *      tags={"Season"},
     *      description="Delete Season",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Season",
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
        /** @var Season $season */
        $season = $this->seasonRepository->find($id);

        if (empty($season)) {
            return $this->sendError('Season not found');
        }

        $season->delete();

        return $this->sendSuccess('Season deleted successfully');
    }
}
