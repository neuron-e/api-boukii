<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateMonitorSportsDegreeAPIRequest;
use App\Http\Requests\API\UpdateMonitorSportsDegreeAPIRequest;
use App\Http\Resources\API\MonitorSportsDegreeResource;
use App\Models\MonitorSportsDegree;
use App\Repositories\MonitorSportsDegreeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class MonitorSportsDegreeController
 */

class MonitorSportsDegreeAPIController extends AppBaseController
{
    /** @var  MonitorSportsDegreeRepository */
    private $monitorSportsDegreeRepository;

    public function __construct(MonitorSportsDegreeRepository $monitorSportsDegreeRepo)
    {
        $this->monitorSportsDegreeRepository = $monitorSportsDegreeRepo;
    }

    /**
     * @OA\Get(
     *      path="/monitor-sports-degrees",
     *      summary="getMonitorSportsDegreeList",
     *      tags={"MonitorSportsDegree"},
     *      description="Get all MonitorSportsDegrees",
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
     *                  @OA\Items(ref="#/components/schemas/MonitorSportsDegree")
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
        $monitorSportsDegrees = $this->monitorSportsDegreeRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($monitorSportsDegrees, 'Monitor Sports Degrees retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/monitor-sports-degrees",
     *      summary="createMonitorSportsDegree",
     *      tags={"MonitorSportsDegree"},
     *      description="Create MonitorSportsDegree",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/MonitorSportsDegree")
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
     *                  ref="#/components/schemas/MonitorSportsDegree"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateMonitorSportsDegreeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        // Check if this combination already exists (prevent duplicates)
        $existing = MonitorSportsDegree::where('monitor_id', $input['monitor_id'])
            ->where('sport_id', $input['sport_id'])
            ->where('school_id', $input['school_id'])
            ->first();

        if ($existing) {
            // If it exists, return the existing record instead of creating a duplicate
            return $this->sendResponse($existing, 'Monitor Sports Degree already exists');
        }

        $monitorSportsDegree = $this->monitorSportsDegreeRepository->create($input);

        return $this->sendResponse($monitorSportsDegree, 'Monitor Sports Degree saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/monitor-sports-degrees/{id}",
     *      summary="getMonitorSportsDegreeItem",
     *      tags={"MonitorSportsDegree"},
     *      description="Get MonitorSportsDegree",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorSportsDegree",
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
     *                  ref="#/components/schemas/MonitorSportsDegree"
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
        /** @var MonitorSportsDegree $monitorSportsDegree */
        $monitorSportsDegree = $this->monitorSportsDegreeRepository->find($id, with: $request->get('with', []));

        if (empty($monitorSportsDegree)) {
            return $this->sendError('Monitor Sports Degree not found');
        }

        return $this->sendResponse($monitorSportsDegree, 'Monitor Sports Degree retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/monitor-sports-degrees/{id}",
     *      summary="updateMonitorSportsDegree",
     *      tags={"MonitorSportsDegree"},
     *      description="Update MonitorSportsDegree",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorSportsDegree",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/MonitorSportsDegree")
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
     *                  ref="#/components/schemas/MonitorSportsDegree"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateMonitorSportsDegreeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var MonitorSportsDegree $monitorSportsDegree */
        $monitorSportsDegree = $this->monitorSportsDegreeRepository->find($id, with: $request->get('with', []));

        if (empty($monitorSportsDegree)) {
            return $this->sendError('Monitor Sports Degree not found');
        }

        $monitorSportsDegree = $this->monitorSportsDegreeRepository->update($input, $id);

        return $this->sendResponse(new MonitorSportsDegreeResource($monitorSportsDegree), 'MonitorSportsDegree updated successfully');
    }

    /**
     * @OA\Put(
     *      path="/api/monitor-sports-degrees/multiple",
     *      summary="Update or insert multiple monitor sports degrees",
     *      tags={"MonitorSportsDegree"},
     *      description="Update or insert monitor sports degrees in bulk.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  required={"sport_id", "degree_id", "monitor_id", "is_default"},
     *                  @OA\Property(property="sport_id", type="integer", description="Sport ID"),
     *                  @OA\Property(property="degree_id", type="integer", description="Degree ID"),
     *                  @OA\Property(property="monitor_id", type="integer", description="Monitor ID"),
     *                  @OA\Property(property="is_default", type="boolean", description="Is Default")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Monitor sports degrees updated or inserted successfully",
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *      )
     * )
     */
    public function updateMultiple(Request $request)
    {
        $dataToUpdate = $request->json()->all();

        if (empty($dataToUpdate)) {
            return response()->json(['message' => 'No data provided'], 400);
        }

        try {
            foreach ($dataToUpdate as $data) {
                MonitorSportsDegree::updateOrInsert(
                    [
                        'sport_id' => $data['sport_id'],
                        'degree_id' => $data['degree_id'],
                        'monitor_id' => $data['monitor_id'],
                    ],
                    [
                        'is_default' => $data['is_default']
                    ]
                );
            }

            return response()->json(['message' => 'Monitor sports degrees updated or inserted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating or inserting monitor sports degrees'], 500);
        }
    }

    /**
     * @OA\Delete(
     *      path="/monitor-sports-degrees/{id}",
     *      summary="deleteMonitorSportsDegree",
     *      tags={"MonitorSportsDegree"},
     *      description="Delete MonitorSportsDegree",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorSportsDegree",
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
        /** @var MonitorSportsDegree $monitorSportsDegree */
        $monitorSportsDegree = $this->monitorSportsDegreeRepository->find($id);

        if (empty($monitorSportsDegree)) {
            return $this->sendError('Monitor Sports Degree not found');
        }

        $monitorSportsDegree->delete();

        return $this->sendSuccess('Monitor Sports Degree deleted successfully');
    }
}
