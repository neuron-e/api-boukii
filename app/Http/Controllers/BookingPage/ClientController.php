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
use App\Models\GiftVoucher;
use App\Repositories\ClientRepository;
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
        $voucher = $this->findVoucherForCurrentSchool($code);

        if (!$voucher) {
            return $this->sendError('Voucher not found', null, 404);
        }

        $clientId = (int) $id;
        $validation = $this->evaluateVoucherUsage($voucher, $clientId);

        if (!$validation['valid']) {
            return $this->sendError(
                'Voucher cannot be used',
                ['reasons' => $validation['reasons']],
                400
            );
        }

        $voucher->setAttribute('is_generic', $voucher->isGeneric());

        return $this->sendResponse($voucher, 'Voucher returned successfully');
    }

    /**
     * Busca un bono por código sin necesidad de un cliente específico.
     * Permite validar bonos genéricos para cualquier cuenta.
     */
    public function findVoucherByCode($code, Request $request): JsonResponse
    {
        $voucher = $this->findVoucherForCurrentSchool($code);

        if (!$voucher) {
            return $this->sendError('Voucher not found', null, 404);
        }

        $clientId = $request->input('client_id');
        $clientId = $clientId !== null && $clientId !== '' ? (int) $clientId : null;

        $validation = $this->evaluateVoucherUsage($voucher, $clientId);

        if (!$validation['valid']) {
            return $this->sendError(
                'Voucher cannot be used',
                ['reasons' => $validation['reasons']],
                400
            );
        }

        $voucher->setAttribute('is_generic', $voucher->isGeneric());

        return $this->sendResponse($voucher, 'Voucher returned successfully');
    }

    /**
     * Normaliza la búsqueda por código para la escuela actual.
     * Busca primero en vouchers, luego en gift_vouchers.
     * Si encuentra un gift_voucher pagado y no canjeado, lo canjea automáticamente.
     */
    private function findVoucherForCurrentSchool(string $code): ?Voucher
    {
        $normalizedCode = Str::upper(trim($code));

        // Primero buscar en la tabla vouchers
        $voucher = Voucher::where('school_id', $this->school->id)
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->first();

        if ($voucher) {
            return $voucher;
        }

        // Si no se encuentra, buscar en gift_vouchers
        $giftVoucher = GiftVoucher::where('school_id', $this->school->id)
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->first();

        if (!$giftVoucher) {
            return null;
        }

        // Si el gift_voucher ya tiene un voucher asociado, devolverlo
        if ($giftVoucher->voucher_id) {
            return Voucher::find($giftVoucher->voucher_id);
        }

        // Si el gift_voucher está pagado pero no tiene voucher asociado, crear uno
        if ($giftVoucher->is_paid && $giftVoucher->status === 'active') {
            $newVoucher = Voucher::create([
                'code' => $giftVoucher->code,
                'name' => 'Gift Voucher',
                'quantity' => $giftVoucher->amount,
                'remaining_balance' => $giftVoucher->balance ?? $giftVoucher->amount,
                'payed' => true,
                'is_gift' => true,
                'buyer_name' => $giftVoucher->buyer_name ?? $giftVoucher->sender_name,
                'buyer_email' => $giftVoucher->buyer_email,
                'buyer_phone' => $giftVoucher->buyer_phone,
                'recipient_name' => $giftVoucher->recipient_name,
                'recipient_email' => $giftVoucher->recipient_email,
                'recipient_phone' => $giftVoucher->recipient_phone,
                'expires_at' => $giftVoucher->expires_at,
                'school_id' => $giftVoucher->school_id,
            ]);

            // Asociar el voucher al gift_voucher
            $giftVoucher->voucher_id = $newVoucher->id;
            $giftVoucher->save();

            return $newVoucher;
        }

        // Si el gift_voucher no está pagado o activo, no se puede usar
        return null;
    }

    /**
     * Valida si un bono puede ser usado por el cliente indicado.
     */
    private function evaluateVoucherUsage(Voucher $voucher, ?int $clientId): array
    {
        $reasons = [];

        if (!$voucher->canBeUsed()) {
            if ($voucher->isExpired()) {
                $reasons[] = 'Voucher expired';
            }
            if (!$voucher->hasBalance()) {
                $reasons[] = 'Voucher has no available balance';
            }
            if ($voucher->hasReachedMaxUses()) {
                $reasons[] = 'Voucher maximum uses reached';
            }
            if ($voucher->trashed()) {
                $reasons[] = 'Voucher is not active';
            }
        }

        if ($voucher->isGeneric()) {
            if ($clientId !== null && !$voucher->canBeUsedByClient($clientId)) {
                $reasons[] = 'Voucher cannot be used by this client';
            }
        } else {
            if ($clientId === null) {
                $reasons[] = 'Voucher is assigned to a specific client';
            } elseif (!$voucher->canBeUsedByClient($clientId)) {
                $effectiveClient = $voucher->getEffectiveClientId();
                if ($effectiveClient && $effectiveClient !== $clientId) {
                    $reasons[] = 'Voucher is assigned to a different client';
                } else {
                    $reasons[] = 'Voucher cannot be used by this client';
                }
            }
        }

        return [
            'valid' => empty($reasons),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function normalizeLanguageIds(array $input): array
    {
        $languageFields = ['language1_id', 'language2_id', 'language3_id'];

        foreach ($languageFields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            if (is_array($value) && array_key_exists('id', $value)) {
                $value = $value['id'];
            }

            if ($value === '' || $value === null) {
                $input[$field] = null;
                continue;
            }

            if (is_numeric($value)) {
                $input[$field] = (int) $value;
                continue;
            }

            $input[$field] = $value;
        }

        return $input;
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
        $mainClient = Client::with('utilizers.clientSports.degree')->find($id);

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
            'first_name' => 'required|string',
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
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'birth_date' => $request->input('birth_date'),
            'email' => $mainClient->email,
            'phone' => $mainClient->phone,
            'telephone' => $mainClient->email,
            'address' => $mainClient->address,
            'cp' => $mainClient->cp,
            'city' => $mainClient->city,
            'province' => $mainClient->province,
            'country' => $mainClient->country,
            'station_id' => $mainClient->station_id,
            'password' => bcrypt(Str::random(8)),
            'language1_id' => $request->input('language1_id')
        ]);



        // Guarda el nuevo cliente en la base de datos
        $newClient->save();

        ClientsSchool::create([
            'client_id' => $newClient->id,
            'school_id' => $this->school->id
        ]);

        $newUser = new User([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'birth_date' => $request->input('birth_date'),
            'email' => $mainClient->email,
            'phone' => $mainClient->phone,
            'telephone' => $mainClient->email,
            'address' => $mainClient->address,
            'cp' => $mainClient->cp,
            'city' => $mainClient->city,
            'province' => $mainClient->province,
            'country' => $mainClient->country,
            'station_id' => $mainClient->station_id,
            'password' => bcrypt(Str::random(8)),
            'language1_id' => $request->input('language1_id')
            ]
        );
        $newUser->type = 'client';
        $newUser->save();
        $newClient->user_id = $newUser->id;
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
     *      path="/slug/clients",
     *      summary="createClient",
     *      tags={"BookingPage"},
     *      description="Create client",
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
        $input = $this->normalizeLanguageIds($request->all());

        $validator = Validator::make($input, [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'birth_date' => 'required',
            'phone' => 'required',
            'email' => 'required',
            'password' => 'required',
            'language1_id' => 'required|integer',
            'language2_id' => 'nullable|integer',
            'language3_id' => 'nullable|integer',
            'accepts_newsletter' => 'nullable|boolean'
        ]);

        $validator->validate();
        $input = $validator->validated();

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
                $newUser->active = true;
                $newUser->type = 'client';
                $newUser->save();
                $client->user_id = $newUser->id;
                $client->save();
                return $this->sendResponse($client, 'Client created successfully');
            }
            return $this->sendError('Client already exists');
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
        $newUser->active = true;
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
        $school = $this->school;
        $searchParameters =
            array_merge($request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order',
                'orderColumn', 'page', 'with']), ['school_id' => $school->id]);
        $search = $request->input('search');
        $order = $request->input('order', 'desc');
        $orderColumn = $request->input('orderColumn', 'id');
        $with = $request->input('with', ['utilizers', 'clientSports.degree', 'clientSports.sport']);

        $clientRepository = new ClientRepository();
        $clientsWithUtilizers =
            $clientRepository->all(
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
