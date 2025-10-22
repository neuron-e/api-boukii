<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\BookingUser;
use App\Models\Client;
use App\Repositories\ClientRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Response;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class ClientsController extends AppBaseController
{

    /** @var  ClientRepository */
    private $clientRepository;

    public function __construct(ClientRepository $clientRepo)
    {
        $this->clientRepository = $clientRepo;
    }


    /**
     * @OA\Get(
     *      path="/admin/clients",
     *      summary="getClientList",
     *      tags={"Admin"},
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
        // Define el valor por defecto para 'perPage'
        $perPage = $request->input('perPage', 15);

        // Obtén el ID de la escuela y añádelo a los parámetros de búsqueda
        $school = $this->getSchool($request);
        $this->clientRepository->setContextSchoolId($school->id);
        $searchParameters =
            array_merge($request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order',
                'orderColumn', 'page', 'with']), ['school_id' => $school->id]);
        $search = $request->input('search');
        $order = $request->input('order', 'desc');
        $orderColumn = $request->input('orderColumn', 'id');
        $with = $request->input('with', ['utilizers', 'clientSports.degree', 'clientSports.sport']);

        $clientsWithUtilizers =
            $this->clientRepository->all(
                searchArray: $searchParameters,
                search: $search,
                skip: $request->input('skip'),
                limit: $request->input('limit'),
                pagination: $perPage,
                with: $with,
                order: $order,
                orderColumn: $orderColumn,
                additionalConditions: function ($query) use($school) {
                    $query->whereHas('clientsSchools', function ($query) use($school) {
                        $query->where('school_id', $school->id);
                    });
                }
            );

        return response()->json($clientsWithUtilizers);
    }

    /**
     * @OA\Get(
     *      path="/admin/clients/mains",
     *      summary="getClientList",
     *      tags={"Admin"},
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
    public function getMains(Request $request): JsonResponse
    {
        // Define el valor por defecto para 'perPage'
        $perPage = $request->input('perPage', 15);

        // Obtén el ID de la escuela y añádelo a los parámetros de búsqueda
        $school = $this->getSchool($request);
        $this->clientRepository->setContextSchoolId($school->id);
        $searchParameters = array_merge(
            $request->except([
                'skip', 'limit', 'search', 'exclude', 'user', 'active',
                'perPage', 'order', 'orderColumn', 'page', 'with'
            ]),
            ['school_id' => $school->id]
        );

        $search = $request->input('search');
        $order = $request->input('order', 'desc');
        $orderColumn = $request->input('orderColumn', 'id');
        $with = $request->input('with', ['utilizers.clientSports.sport', 'utilizers.clientSports.degree', 'clientSports.degree', 'clientSports.sport']);

        $fieldSearchable = [
        'first_name',
        'last_name',
    ];

        $clientsWithUtilizers = $this->clientRepository->all(
            searchArray: [],
            search: null, // Eliminar búsqueda global por ahora
            skip: $request->input('skip'),
            limit: $request->input('limit'),
            pagination: $perPage,
            with: $with,
            order: $order,
            orderColumn: $orderColumn,
            additionalConditions: function ($query) use ($school, $search, $request, $fieldSearchable) {
                // Filtrar clientes sin 'main' y relacionados con 'clientsSchools'
                $query->whereDoesntHave('main')
                    ->whereHas('clientsSchools', function ($query) use ($school, $request) {
                        $query->where('school_id', $school->id);
                        if ($request->has('active')) {
                            $query->whereNotNull('accepted_at');
                        }
                    });

                // Búsqueda adicional
                if ($search) {
                    $query->where(function ($query) use ($school, $search, $fieldSearchable) {
                        // Buscar en utilizadores
                        $query->whereHas('utilizers', function ($subQuery) use ($search, $fieldSearchable) {
                            $subQuery->where(function ($subSubQuery) use ($search, $fieldSearchable) {
                                foreach ($fieldSearchable as $field) {
                                    $subSubQuery->orWhere($field, 'like', "%" . $search . "%");
                                }
                            });
                        });

                        // Buscar en el cliente principal
                        $query->orWhere(function ($subQuery) use ($search) {
                            $subQuery->where('clients.first_name', 'like', "%" . $search . "%")
                                ->orWhere('clients.last_name', 'like', "%" . $search . "%");
                        });
                    });
                }
            }
        );


        return response()->json($clientsWithUtilizers);
    }


    /**
     * @OA\Get(
     *      path="/admin/clients/{id}",
     *      summary="getClientItem",
     *      tags={"Admin"},
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
    public function show($id): JsonResponse
    {
        /** @var Client $client */
        $mainClient = Client::with('utilizers', 'clientSports.degree', 'clientSports.sport')->find($id);

        if (empty($mainClient)) {
            return $this->sendError('Client not found');
        }

        return $this->sendResponse($mainClient, 'Client retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/admin/clients/{id}/utilizers",
     *      summary="getClientUtilizersList",
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
     *                  @OA\Items(ref="#/components/schemas/Booking")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getUtilizers($id, Request $request): JsonResponse
    {
        $mainClient = Client::with('utilizers')->find($id);

        $utilizers = $mainClient->utilizers;

        return $this->sendResponse($utilizers, 'Utilizers returned successfully');
    }

    /**
     * @OA\Get(
     *      path="/admin/clients/course/{id}",
     *      summary="getClientsByCourse",
     *      tags={"Admin"},
     *      description="Get Clients by course",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Course",
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
    public function getClientsByCourse($id): JsonResponse
    {
        // Busca todos los BookingUser con el course_id proporcionado
        $bookingUsers = BookingUser::where('course_id', $id)
            ->whereHas('booking')
            ->with(['client', 'degree', 'course', 'monitor'])
            ->get();

        // Crea una colección para almacenar clientes únicos
        $uniqueClients = collect([]);

        foreach ($bookingUsers as $bookingUser) {
            // Obtiene el ID del cliente asociado al BookingUser
            $clientId = $bookingUser->client_id;

            // Verifica si el cliente aún no se ha agregado a la colección
            if (!$uniqueClients->has($clientId)) {
                // Agrega el cliente a la colección
                $uniqueClients->put($clientId, [
                    'client' => $bookingUser->client,
                    'course' => $bookingUser->course,
                    'degree' => $bookingUser->degree,
                    'monitor' => $bookingUser->monitor
                ]);
            }
        }

        // Convierte la colección en una matriz de clientes únicos
        $uniqueClients = $uniqueClients->values();

        return $this->sendResponse($uniqueClients, 'Clients retrieved successfully for the course');
    }

}
