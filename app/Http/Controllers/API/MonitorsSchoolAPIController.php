<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateMonitorsSchoolAPIRequest;
use App\Http\Requests\API\UpdateMonitorsSchoolAPIRequest;
use App\Http\Resources\API\MonitorsSchoolResource;
use App\Models\MonitorsSchool;
use App\Repositories\MonitorsSchoolRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Class MonitorsSchoolController
 */

class MonitorsSchoolAPIController extends AppBaseController
{
    /** @var  MonitorsSchoolRepository */
    private $monitorsSchoolRepository;

    public function __construct(MonitorsSchoolRepository $monitorsSchoolRepo)
    {
        $this->monitorsSchoolRepository = $monitorsSchoolRepo;
    }

    /**
     * @OA\Get(
     *      path="/monitors-schools",
     *      summary="getMonitorsSchoolList",
     *      tags={"MonitorsSchool"},
     *      description="Get all MonitorsSchools",
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
     *                  @OA\Items(ref="#/components/schemas/MonitorsSchool")
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
        // Cache and optimize for large requests commonly used in monitor details
        if ($request->get('perPage') == 99999 && !$request->get('search')) {
            $school = null;
            $schoolId = 'all';
            
            // Safely get school if method exists
            if (method_exists($this, 'getSchool')) {
                try {
                    $school = $this->getSchool($request);
                    $schoolId = $school ? $school->id : 'all';
                } catch (\Exception $e) {
                    $schoolId = 'all';
                }
            }
            
            $cacheKey = "monitors_schools_all_{$schoolId}";
            
            $monitorsSchools = Cache::remember($cacheKey, 300, function () use ($request, $schoolId) { // 5 minutes cache
                // Optimized query: only active records with essential fields
                $query = MonitorsSchool::select(['id', 'monitor_id', 'school_id', 'active_school'])
                    ->where('active_school', 1);
                
                if ($schoolId !== 'all') {
                    $query->where('school_id', $schoolId);
                }
                
                return $query->orderBy('id', 'desc')->get();
            });
        } else {
            $monitorsSchools = $this->monitorsSchoolRepository->all(
                $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
                $request->get('search'),
                $request->get('skip'),
                $request->get('limit'),
                $request->perPage,
                $request->get('with', []),
                $request->get('order', 'desc'),
                $request->get('orderColumn', 'id')
            );
        }

        return $this->sendResponse($monitorsSchools, 'Monitors Schools retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/monitors-schools",
     *      summary="createMonitorsSchool",
     *      tags={"MonitorsSchool"},
     *      description="Create MonitorsSchool",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/MonitorsSchool")
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
     *                  ref="#/components/schemas/MonitorsSchool"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateMonitorsSchoolAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $monitorsSchool = $this->monitorsSchoolRepository->create($input);

        return $this->sendResponse($monitorsSchool, 'Monitors School saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/monitors-schools/{id}",
     *      summary="getMonitorsSchoolItem",
     *      tags={"MonitorsSchool"},
     *      description="Get MonitorsSchool",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorsSchool",
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
     *                  ref="#/components/schemas/MonitorsSchool"
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
        /** @var MonitorsSchool $monitorsSchool */
        $monitorsSchool = $this->monitorsSchoolRepository->find($id, with: $request->get('with', []));

        if (empty($monitorsSchool)) {
            return $this->sendError('Monitors School not found');
        }

        return $this->sendResponse($monitorsSchool, 'Monitors School retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/monitors-schools/{id}",
     *      summary="updateMonitorsSchool",
     *      tags={"MonitorsSchool"},
     *      description="Update MonitorsSchool",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorsSchool",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/MonitorsSchool")
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
     *                  ref="#/components/schemas/MonitorsSchool"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateMonitorsSchoolAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var MonitorsSchool $monitorsSchool */
        $monitorsSchool = $this->monitorsSchoolRepository->find($id, with: $request->get('with', []));

        if (empty($monitorsSchool)) {
            return $this->sendError('Monitors School not found');
        }

        $monitorsSchool = $this->monitorsSchoolRepository->update($input, $id);

        return $this->sendResponse(new MonitorsSchoolResource($monitorsSchool), 'MonitorsSchool updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/monitors-schools/{id}",
     *      summary="deleteMonitorsSchool",
     *      tags={"MonitorsSchool"},
     *      description="Delete MonitorsSchool",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of MonitorsSchool",
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
        /** @var MonitorsSchool $monitorsSchool */
        $monitorsSchool = $this->monitorsSchoolRepository->find($id);

        if (empty($monitorsSchool)) {
            return $this->sendError('Monitors School not found');
        }

        $monitorsSchool->delete();

        return $this->sendSuccess('Monitors School deleted successfully');
    }
}
