<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateClientAPIRequest;
use App\Http\Requests\API\UpdateClientAPIRequest;
use App\Http\Resources\API\ClientResource;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\CourseSubgroup;
use App\Repositories\ClientRepository;
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
    public function __construct(ClientRepository $clientRepo)
    {
        $this->clientRepository = $clientRepo;
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

        $initialGroup = $initialSubgroup->courseGroup;
        $targetGroup = $targetSubgroup->courseGroup;

        $targetSubgroupPosition =
            $targetGroup->courseSubgroups->sortBy('id')->search(function ($subgroup) use ($targetSubgroup) {
                return $subgroup->id == $targetSubgroup->id;
            });
        $today = Carbon::today();

        DB::beginTransaction();
        $subgroupsChanged = [];
        if ($request->moveAllDays) {
            $courseDates = $initialGroup->course->courseDates;
            foreach ($courseDates as $courseDate) {
                $groups = $courseDate->courseGroups->where('degree_id', $targetGroup->degree_id);

                foreach ($groups as $group) {
                    /*if (Carbon::parse($courseDate->date)->gte($today)) {*/
                        //** Removed subgroups length, not seems to be reasonable with new features. */
                        /*if ($group->courseSubgroups->count() == $initialGroup->courseSubgroups->count()) {*/

                        $newTargetSubgroup = $group->courseSubgroups->sortBy('id')[$targetSubgroupPosition] ?? null;

                        if ($newTargetSubgroup) {
                            $subgroupsChanged[] = $newTargetSubgroup;
                            $this->moveUsers($courseDate, $newTargetSubgroup, $request->clientIds);
                        } else {
                            DB::rollBack();
                            Log::error('Some groups are not identical length', $group->courseSubgroups->sortBy('id')->toArray());
                            return $this->sendError('Some groups are not identical');
                        }
                        /*                        } else {
                                                    DB::rollBack();
                                                    Log::error('Initial count'. $initialGroup->courseSubgroups->count() );
                                                    Log::error('Sned count '. $group->courseSubgroups->count() );
                                                    return $this->sendError('Some groups are not identical length');
                                                }*/
                   /* }*/

                }

            }
        } else {
            $this->moveUsers($initialSubgroup, $targetSubgroup, $request->clientIds);
        }
        DB::commit();
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
