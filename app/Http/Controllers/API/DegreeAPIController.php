<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateDegreeAPIRequest;
use App\Http\Requests\API\UpdateDegreeAPIRequest;
use App\Http\Resources\API\DegreeResource;
use App\Models\Degree;
use App\Repositories\DegreeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

/**
 * Class DegreeController
 */

class DegreeAPIController extends AppBaseController
{
    /** @var  DegreeRepository */
    private $degreeRepository;

    public function __construct(DegreeRepository $degreeRepo)
    {
        $this->degreeRepository = $degreeRepo;
    }

    /**
     * @OA\Get(
     *      path="/degrees",
     *      summary="getDegreeList",
     *      tags={"Degree"},
     *      description="Get all Degrees",
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
     *                  @OA\Items(ref="#/components/schemas/Degree")
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
        // Cache and optimize the request for perPage=99999 which is commonly used in monitor details
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
            
            $cacheKey = "degrees_all_{$schoolId}";
            
            $degrees = Cache::remember($cacheKey, 300, function () use ($request, $schoolId) { // 5 minutes cache
                // Optimized query: select only essential fields and add school filter
                $query = Degree::select(['id', 'name', 'level', 'color', 'school_id', 'sport_id', 'active'])
                    ->where('active', 1);
                
                if ($schoolId !== 'all') {
                    $query->where('school_id', $schoolId);
                }
                
                return $query->orderBy('name', 'asc')->get();
            });
        } else {
            $degrees = $this->degreeRepository->all(
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

        return $this->sendResponse($degrees, 'Degrees retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/degrees",
     *      summary="createDegree",
     *      tags={"Degree"},
     *      description="Create Degree",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Degree")
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
     *                  ref="#/components/schemas/Degree"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateDegreeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if(!empty($input['image'])) {
            $base64Image = $request->input('image');

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]);
                $imageData = base64_decode($imageData);

                if ($imageData === false) {
                    $this->sendError('base64_decode failed');
                }
            } else {
                $this->sendError('did not match data URI with image data');
            }

            $imageName = 'degree/image_'.time().'.'.$type;
            Storage::disk('public')->put($imageName, $imageData);
            $input['image'] = url(Storage::url($imageName));
        }

        $degree = $this->degreeRepository->create($input);

        return $this->sendResponse($degree, 'Degree saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/degrees/{id}",
     *      summary="getDegreeItem",
     *      tags={"Degree"},
     *      description="Get Degree",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Degree",
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
     *                  ref="#/components/schemas/Degree"
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
        /** @var Degree $degree */
        $degree = $this->degreeRepository->find($id, with: $request->get('with', []));

        if (empty($degree)) {
            return $this->sendError('Degree not found');
        }

        return $this->sendResponse($degree, 'Degree retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/degrees/{id}",
     *      summary="updateDegree",
     *      tags={"Degree"},
     *      description="Update Degree",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Degree",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Degree")
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
     *                  ref="#/components/schemas/Degree"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateDegreeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Degree $degree */
        $degree = $this->degreeRepository->find($id, with: $request->get('with', []));

        if (empty($degree)) {
            return $this->sendError('Degree not found');
        }

        if(!empty($input['image'])) {
            $base64Image = $request->input('image');

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]);
                $imageData = base64_decode($imageData);

                if ($imageData === false) {
                    $this->sendError('base64_decode failed');
                }
                $imageName = 'degree/image_'.time().'.'.$type;
                Storage::disk('public')->put($imageName, $imageData);
                $input['image'] = url(url(Storage::url($imageName)));
            } else {
                $this->sendError('did not match data URI with image data');
            }
        } else {
            $input = $request->except('image');
        }

        $degree = $this->degreeRepository->update($input, $id);

        return $this->sendResponse(new DegreeResource($degree), 'Degree updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/degrees/{id}",
     *      summary="deleteDegree",
     *      tags={"Degree"},
     *      description="Delete Degree",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Degree",
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
        /** @var Degree $degree */
        $degree = $this->degreeRepository->find($id);

        if (empty($degree)) {
            return $this->sendError('Degree not found');
        }

        $degree->delete();

        return $this->sendSuccess('Degree deleted successfully');
    }
}
