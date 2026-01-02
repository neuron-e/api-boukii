<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateClientAPIRequest;
use App\Http\Requests\API\UpdateClientAPIRequest;
use App\Http\Resources\API\ClientResource;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\CourseSubgroup;
use App\Models\CourseSubgroupDate;
use App\Repositories\ClientRepository;
use App\Services\CourseRepairDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Class ClientController
 */

class ClientAPIController extends AppBaseController
{
    private CourseRepairDispatcher $repairDispatcher;

    public function __construct(ClientRepository $clientRepo, CourseRepairDispatcher $repairDispatcher)
    {
        $this->clientRepository = $clientRepo;
        $this->repairDispatcher = $repairDispatcher;
    }

    /**
     * @OA\Get(
     *      path="/clients",
     *      summary="getClientList",
     *      tags={"Client"},
     *      description="Get all Clients",
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
     *                  @OA\Items(ref="#/components/schemas/Client")
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

        $clients = $this->clientRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id'),
            function ($query) use ($request) {
                if ($request->has('school_id')) {
                    $query->whereHas('clientsSchools', function ($subQuery) use ($request) {
                        $subQuery->where('school_id', $request->get('school_id'));
                    })->orWhereHas('main', function ($subQuery) use ($request) {
                        $subQuery->whereHas('clientsSchools', function ($subQuery) use ($request) {
                            $subQuery->where('school_id', $request->get('school_id'));
                        });
                    });
                }
            }
        );

        return $this->sendResponse($clients, 'Clients retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/clients",
     *      summary="createClient",
     *      tags={"Client"},
     *      description="Create Client",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Client")
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
     *                  ref="#/components/schemas/Client"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateClientAPIRequest $request): JsonResponse
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

            $imageName = 'client/image_'.time().'.'.$type;
            Storage::disk('public')->put($imageName, $imageData);
            $input['image'] = url(Storage::url($imageName));
        }

        $client = $this->clientRepository->create($input);

        return $this->sendResponse(new ClientResource($client), 'Client saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/clients/{id}",
     *      summary="getClientItem",
     *      tags={"Client"},
     *      description="Get Client",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Client",
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
     *                  ref="#/components/schemas/Client"
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
        /** @var Client $client */
        $client = $this->clientRepository->find($id, with: $request->get('with', []));

        if (empty($client)) {
            return $this->sendError('Client not found');
        }

        return $this->sendResponse($client, 'Client retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/clients/{id}",
     *      summary="updateClient",
     *      tags={"Client"},
     *      description="Update Client",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Client",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Client")
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
     *                  ref="#/components/schemas/Client"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateClientAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Client $client */
        $client = $this->clientRepository->find($id, with: $request->get('with', []));

        if (empty($client)) {
            return $this->sendError('Client not found');
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
                $imageName = 'client/image_'.time().'.'.$type;
                Storage::disk('public')->put($imageName, $imageData);
                $input['image'] = url(Storage::url($imageName));
            } else {
                $this->sendError('did not match data URI with image data');
            }
        } else {
            $input = $request->except('image');
        }

        $client = $this->clientRepository->update($input, $id);

        return $this->sendResponse(new ClientResource($client), 'Client updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/clients/{id}",
     *      summary="deleteClient",
     *      tags={"Client"},
     *      description="Delete Client",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Client",
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
        /** @var Client $client */
        $client = $this->clientRepository->find($id);

        if (empty($client)) {
            return $this->sendError('Client not found');
        }

        $client->delete();

        return $this->sendSuccess('Client deleted successfully');
    }

    /**
     * @OA\Post(
     *      path="/clients/transfer",
     *      summary="transferClients",
     *      tags={"Client"},
     *      description="Transfer clients",
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
     *                  @OA\Items(ref="#/components/schemas/BookingUser")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function transferClients(Request $request): JsonResponse
    {
        $initialSubgroup = CourseSubgroup::with('courseGroup.courseDate')->find($request->initialSubgroupId);
        $targetSubgroup = CourseSubgroup::find($request->targetSubgroupId);


        if (!$initialSubgroup || !$targetSubgroup) {
            // Manejar error
            return $this->sendError('No existe el subgrupo');
        }

        // Validar que el subgrupo objetivo tiene subgroup_dates_id asignado
        if (!$targetSubgroup->subgroup_dates_id) {
            Log::channel('availability')->error('Target subgroup missing subgroup_dates_id', ['subgroup_id' => $targetSubgroup->id]);
            return $this->sendError('Target subgroup is not properly configured (missing subgroup_dates_id)');
        }

        $initialGroup = $initialSubgroup->courseGroup;
        $targetGroup = $targetSubgroup->courseGroup;

        $scope = $request->get('scope');
        if (!$scope) {
            $scope = $request->moveAllDays ? 'all' : 'single';
        }
        $scopeDate = $request->get('scope_date');
        $startDate = null;
        if ($scope === 'future') {
            if (!$scopeDate) {
                return $this->sendError('Missing scope_date for future transfer');
            }
            $startDate = Carbon::parse($scopeDate)->startOfDay();
        }

        // MEJORADO: Usar subgroup_dates_id en lugar de posición implícita
        // Esto es más confiable y explícito
        $targetSubgroupDatesId = $targetSubgroup->subgroup_dates_id;

        DB::beginTransaction();
        $subgroupsChanged = [];
        if ($scope === 'all' || $scope === 'future') {
            $courseDates = $initialGroup->course->courseDates->whereNull('deleted_at');
            if ($scope === 'future') {
                $courseDates = $courseDates->filter(function ($courseDate) use ($startDate) {
                    return Carbon::parse($courseDate->date)->startOfDay()->gte($startDate);
                });
            }
            $targetSubgroupDateEntries = CourseSubgroupDate::with('courseSubgroup.courseGroup')
                ->whereHas('courseSubgroup', function ($subgroupQuery) use ($targetSubgroupDatesId, $targetGroup) {
                    $subgroupQuery->where('subgroup_dates_id', $targetSubgroupDatesId)
                        ->whereNull('deleted_at')
                        ->whereHas('courseGroup', fn($q) => $q->where('degree_id', $targetGroup->degree_id)->whereNull('deleted_at'));
                })
                ->whereIn('course_date_id', $courseDates->pluck('id')->all())
                ->get()
                ->groupBy('course_date_id');

            $missingDates = [];

            $changedSubgroupIds = [];
            foreach ($courseDates as $courseDate) {
                $entries = $targetSubgroupDateEntries->get($courseDate->id);
                $targetEntry = $entries?->first();

                if (!$targetEntry) {
                    $missingDates[$courseDate->id] = $courseDate->date;
                    continue;
                }

                $newTargetSubgroup = $targetEntry->courseSubgroup;
                if ($newTargetSubgroup) {
                    if (!in_array($newTargetSubgroup->id, $changedSubgroupIds, true)) {
                        $subgroupsChanged[] = $newTargetSubgroup;
                        $changedSubgroupIds[] = $newTargetSubgroup->id;
                    }
                    $this->moveUsers($courseDate, $newTargetSubgroup, $request->clientIds);
                }
            }

            if (empty($subgroupsChanged)) {
                DB::rollBack();
                Log::channel('availability')->error(
                    'Target subgroup with subgroup_dates_id not found in any course date',
                    [
                        'course_id' => $initialGroup->course->id,
                        'target_subgroup_dates_id' => $targetSubgroupDatesId,
                        'course_date_ids' => $courseDates->pluck('id')->values()->all()
                    ]
                );
                return $this->sendError('Target subgroup configuration not found in any course date');
            }

            if (!empty($missingDates)) {
                Log::channel('availability')->warning(
                    'Target subgroup missing for some course dates',
                    [
                        'course_id' => $initialGroup->course->id,
                        'missing_course_date_ids' => array_keys($missingDates),
                        'missing_dates' => array_values($missingDates),
                        'target_subgroup_dates_id' => $targetSubgroupDatesId
                    ]
                );
            }
        } else {
            $initialCourseDate = $initialGroup?->courseDate;
            if (!$initialCourseDate) {
                DB::rollBack();
                return $this->sendError('CourseDate not found for subgroup transfer');
            }
            $this->moveUsers($initialCourseDate, $targetSubgroup, $request->clientIds);
            $subgroupsChanged[] = $targetSubgroup;
        }
        DB::commit();
        $this->repairDispatcher->dispatchForSchool($school->id ?? null);
        if(count($subgroupsChanged)) {
            return $this->sendResponse($subgroupsChanged, 'Clients transfer successfully');
        }
        return $this->sendError('No groups changed');
    }


    private function moveUsers($initialCourseDate, $targetSubgroup, $clientIds)
    {
        // Mover los usuarios
        foreach ($clientIds as $clientId) {
            BookingUser::where('course_date_id', $initialCourseDate->id)
                ->where('client_id', $clientId)
                ->update(['course_subgroup_id' => $targetSubgroup->id,
                        'course_group_id' => $targetSubgroup->course_group_id,
                        'degree_id' => $targetSubgroup->degree_id,
                        'monitor_id' => $targetSubgroup->monitor_id,
                        'group_changed' => true
                    ]
                );
        }
    }
}

