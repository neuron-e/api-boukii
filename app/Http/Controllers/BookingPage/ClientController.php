<?php

namespace App\Http\Controllers\BookingPage;

use App\Http\Controllers\AppBaseController;
use App\Http\Resources\API\BookingResource;
use App\Models\Booking;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\ClientsSchool;
use App\Models\ClientsUtilizer;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Response;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class ClientController extends SlugAuthController
{


    /**
     * @OA\Get(
     *      path="/slug/client/{id}/voucher/{code}",
     *      summary="getVoucherForClient",
     *      tags={"BookingPage"},
     *      description="Search by code voucher for a client",
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
     *                  @OA\Items(ref="#/components/schemas/Voucher")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getVoucherByCode($id, $code, Request $request): JsonResponse
    {

        $voucher =
            Voucher::where('school_id', $this->school->id)->where('client_id', $id)->where('code', $code)->first();

        if(!$voucher) {
            return $this->sendError( 'Voucher not found');
        }

        return $this->sendResponse($voucher, 'Voucher returned successfully');
    }

    /**
     * @OA\Get(
     *      path="/slug/clients/{id}/utilizers",
     *      summary="getClientUtilizersList",
     *      tags={"BookingPage"},
     *      description="Get all Clients utilizers from id",
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
    public function getUtilizers($id, Request $request): JsonResponse
    {
        $mainClient = Client::with('utilizers')->find($id);

        $utilizers = $mainClient->utilizers;

        return $this->sendResponse($utilizers, 'Utilizers returned successfully');
    }

    /**
     * @OA\Post(
     *      path="/slug/clients/{id}/utilizers",
     *      summary="createUtilizer",
     *      tags={"BookingPage"},
     *      description="Create utilizer for a client",
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
    public function storeUtilizers($id, Request $request): JsonResponse
    {
        // Valida los datos de la solicitud, asegúrate de que contenga al menos los campos necesarios
        $request->validate([
            'name' => 'required|string',
            'last_name' => 'required|string',
            'birth_date' => 'required',
            'language1_id' => 'required'
        ]);

        // Encuentra al cliente principal con la ID proporcionada
        $mainClient = Client::find($id);

        if (!$mainClient) {
            return $this->sendError('Main client not found', [], 404);
        }

        // Crea un nuevo cliente con los datos de la solicitud
        $newClient = new Client([
            'first_name' => $request->input('name'),
            'last_name' => $request->input('last_name'),
            'birth_date' => $request->input('birth_date'),
            'language1_id' => $request->input('language1_id')
        ]);

        // Guarda el nuevo cliente en la base de datos
        $newClient->save();

        // Crea un registro en ClientsUtilizer con la main_id y client_id
        $clientsUtilizer = new ClientsUtilizer([
            'main_id' => $mainClient->id,
            'client_id' => $newClient->id,
        ]);

        $clientsUtilizer->save();

        return $this->sendResponse($newClient, 'Utilizer created successfully');
    }

    /**
     * @OA\Post(
     *      path="/slug/clients/{id}/utilizers",
     *      summary="createUtilizer",
     *      tags={"BookingPage"},
     *      description="Create utilizer for a client",
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
    public function store(Request $request): JsonResponse
    {
        // Valida los datos de la solicitud, asegúrate de que contenga al menos los campos necesarios
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'birth_date' => 'required',
            'phone' => 'required',
            'email' => 'required',
            'password' => 'required',
            'language1_id' => 'required',
            'language2_id' => 'nullable',
            'language3_id' => 'nullable'
        ]);

        $input = $request->all();

        if(!empty($input['password'])) {
            $input['password'] = bcrypt($input['password']);
        } else {
            //$input['password'] = bcrypt(Str::random(8));
            return $this->sendError('User cannot be created without a password');
        }

        $client = Client::where('email', $input['email'])->whereHas('clientsSchools', function ($q) {
            $q->where('school_id', $this->school->id);
        })->first();


        if ($client) {
            // Verifica si el cliente tiene un usuario asociado
            if (!$client->user) {
                // Si no tiene usuario, crea uno
                $newUser = new User($input);
                $newUser->type = 'client';
                $newUser->save();
                $client->user_id = $newUser->id;
                $client->save();
                return $this->sendResponse($client, 'Client created successfully');
            }
            return $this->sendError('User already exists');
        }

        // Crea un nuevo cliente con los datos de la solicitud
        $newClient = new Client($input);

        // Guarda el nuevo cliente en la base de datos
        $newClient->save();

        ClientsSchool::create([
           'client_id' => $newClient->id,
           'school_id' => $this->school->id
        ]);

        $newUser = new User($input);
        $newUser->type = 'client';
        $newUser->save();
        $newClient->user_id = $newUser->id;
        $newClient->save();

        return $this->sendResponse($newClient, 'Client created successfully');
    }

    /**
     * @OA\Get(
     *      path="/slug/clients/mains",
     *      summary="getClientListMains",
     *      tags={"BookingPage"},
     *      description="Get all Clients Mains",
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
                additionalConditions: function($query) use($school) {
                    $query->whereDoesntHave('main')->whereHas('clientsSchools', function ($query) use($school) {
                        $query->where('school_id', $school->id);
                    });
                }
            );

        return response()->json($clientsWithUtilizers);
    }


}
