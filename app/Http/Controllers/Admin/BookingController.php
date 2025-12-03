<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\PayrexxHelpers;
use App\Http\Resources\API\BookingResource;
use App\Mail\BookingCancelMailer;
use App\Mail\BookingCreateMailer;
use App\Mail\BookingInfoMailer;
use App\Mail\BookingInfoUpdateMailer;
use App\Mail\BookingPayMailer;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingUser;
use App\Models\BookingUserExtra;
use App\Models\Client;
use App\Models\ClientSport;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseExtra;
use App\Models\CourseSubgroup;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\VouchersLog;
use App\Repositories\BookingRepository;
use App\Services\MonitorNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;
use Payrexx\Payrexx;
use Response;
use Validator;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class BookingController extends AppBaseController
{

    /** @var  BookingRepository */
    private $bookingRepository;

    public function __construct(BookingRepository $bookingRepo)
    {
        $this->bookingRepository = $bookingRepo;
    }


    /**
     * @OA\Get(
     *      path="/admin/bookings",
     *      summary="getBookingList",
     *      tags={"Booking"},
     *      description="Get all Bookings",
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
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::where('school_id', $request->school_id);

        return $this->sendResponse(BookingResource::collection($bookings), 'Bookings retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/admin/bookings",
     *      summary="createCourse",
     *      tags={"BookingPage"},
     *      description="Create Course",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Course")
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
     *                  ref="#/components/schemas/Course"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */

    public function store(Request $request)
    {

        $school = $this->getSchool($request);
        //TODO: Check OVERLAP
        $data = $request->all();

        // VALIDACIN CRTICA: Verificar coherencia cliente-participantes
        if (isset($data['client_main_id']) && isset($data['cart'])) {
            $clientMainId = $data['client_main_id'];
            $mainClient = Client::with('utilizers')->find($clientMainId);

            if (!$mainClient) {
                return $this->sendError('Cliente principal no encontrado');
            }

            // Obtener IDs vlidos: cliente principal + sus utilizers
            $validClientIds = [$clientMainId];
            foreach ($mainClient->utilizers as $utilizer) {
                $validClientIds[] = $utilizer->id;
            }

            // Verificar que todos los participantes sean vlidos
            foreach ($data['cart'] as $cartItem) {
                if (!in_array($cartItem['client_id'], $validClientIds)) {
                    $invalidClient = Client::find($cartItem['client_id']);
                    return $this->sendError(
                        'Error de coherencia: El participante ' .
                        ($invalidClient->first_name ?? 'ID:' . $cartItem['client_id']) . ' ' .
                        ($invalidClient->last_name ?? '') .
                        ' no pertenece al cliente principal ' . $mainClient->first_name . ' ' . $mainClient->last_name
                    );
                }
            }
        }

        $basketData = $this->createBasket($data);

        $basketJson = json_encode($basketData); // Asegrate de que no haya problemas con la conversin a JSON

        $courseIds = collect($data['cart'] ?? [])
            ->pluck('course_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $meetingPointData = $this->resolveMeetingPointFromCourses($courseIds);

        DB::beginTransaction();
        try {
            $voucherAmount = array_sum(array_column($data['vouchers'], 'bonus.reducePrice'));
            if($voucherAmount > 0){
                $data['paid'] = $data['price_total'] <= $voucherAmount;
            }
            // Crear la reserva (Booking)
            $booking = Booking::create([
                'school_id' => $school['id'],
                'user_id' => $data['user_id'],
                'client_main_id' => $data['client_main_id'],
                'has_tva' => $data['has_tva'],
                // BOUKII CARE DESACTIVADO -                 'has_boukii_care' => $data['has_boukii_care'],
                'has_cancellation_insurance' => $data['has_cancellation_insurance'],
                'has_reduction' => $data['has_reduction'],
                'price_total' => $data['price_total'],
                'price_reduction' => $data['price_reduction'],
                'price_tva' => $data['price_tva'],
                // BOUKII CARE DESACTIVADO -                 'price_boukii_care' => $data['price_boukii_care'],
                'price_cancellation_insurance' => $data['price_cancellation_insurance'],
                'payment_method_id' => $data['payment_method_id'],
                'paid_total' => $data['paid_total'],
                'paid' => $data['paid'],
                'basket' => $basketJson,
                'source' => 'admin',
                'status' => 1,
                'currency' => $data['cart'][0]['currency'], // Si todas las lneas tienen la misma moneda
                'meeting_point' => Arr::get($data, 'meeting_point', $meetingPointData['meeting_point']),
                'meeting_point_address' => Arr::get($data, 'meeting_point_address', $meetingPointData['meeting_point_address']),
                'meeting_point_instructions' => Arr::get($data, 'meeting_point_instructions', $meetingPointData['meeting_point_instructions']),
            ]);

            // Crear BookingUser para cada detalle
            foreach ($data['cart'] as $cartItem) {
                $courseDate = CourseDate::find($cartItem['course_date_id']);
                $bookingUser = new BookingUser([
                    'school_id' => $school['id'],
                    'booking_id' => $booking->id,
                    'client_id' => $cartItem['client_id'],
                    'price' => $cartItem['price'],
                    'currency' => $cartItem['currency'],
                    'course_id' => $cartItem['course_id'],
                    'course_date_id' => $cartItem['course_date_id'],
                    'degree_id' => $cartItem['degree_id'],
                    'hour_start' => $cartItem['hour_start'],
                    'hour_end' => $cartItem['hour_end'],
                    'notes_school' => $cartItem['notes_school'],
                    'notes' => $cartItem['notes'],
                    'date' => $courseDate->date,
                    'group_id' => $cartItem['group_id']
                ]);

                // MEJORA CRTICA: Verificacin y asignacin atmica de subgrupos para cursos colectivos
                if ($cartItem['course_type'] == 1) {
                    // LOGGING: Registrar intento de reserva para anlisis de concurrencia
                    Log::info('BOOKING_CONCURRENCY_ATTEMPT', [
                        'course_date_id' => $cartItem['course_date_id'],
                        'degree_id' => $cartItem['degree_id'],
                        'client_id' => $cartItem['client_id'],
                        'timestamp' => now(),
                        'user_agent' => request()->userAgent()
                    ]);

                    // SOLUCIN CONCURRENCIA: Usar transaccin con lock pessimista
                    $subgroupAssigned = DB::transaction(function () use ($cartItem, $bookingUser) {

                        // 1. Obtener todos los subgrupos candidatos con LOCK FOR UPDATE
                        $candidateSubgroups = CourseSubgroup::where('course_date_id', $cartItem['course_date_id'])
                            ->where('degree_id', $cartItem['degree_id'])
                            ->lockForUpdate() //  LOCK PESSIMISTA - Previene race conditions
                            ->get();

                        // 2. Verificar disponibilidad real contando BookingUsers activos
                        foreach ($candidateSubgroups as $subgroup) {
                            $currentParticipants = BookingUser::where('course_subgroup_id', $subgroup->id)
                                ->where('status', 1)
                                ->count();

                            // 3. Verificacin atmica: hay espacio disponible?
                            if ($currentParticipants < $subgroup->max_participants) {
                                //  XITO: Asignar inmediatamente mientras tenemos el lock
                                $bookingUser->course_group_id = $subgroup->course_group_id;
                                $bookingUser->course_subgroup_id = $subgroup->id;

                                // LOGGING: Registro exitoso de asignacin
                                Log::info('BOOKING_CONCURRENCY_SUCCESS', [
                                    'subgroup_id' => $subgroup->id,
                                    'participants_before' => $currentParticipants,
                                    'max_participants' => $subgroup->max_participants,
                                    'client_id' => $cartItem['client_id']
                                ]);

                                return true; // Subgrupo asignado exitosamente
                            }
                        }

                        // LOGGING: No hay subgrupos disponibles
                        Log::warning('BOOKING_CONCURRENCY_FULL', [
                            'course_date_id' => $cartItem['course_date_id'],
                            'degree_id' => $cartItem['degree_id'],
                            'total_subgroups_checked' => $candidateSubgroups->count()
                        ]);

                        return false; // No hay subgrupos disponibles
                    });

                    // 4. Verificar resultado de la asignacin atmica
                    if (!$subgroupAssigned) {
                        DB::rollBack();
                        return $this->sendError(
                            'No hay plazas disponibles en el nivel ' . $cartItem['degree_id'] .
                            ' para la fecha solicitada. El curso est completo.',
                            ['course_date_id' => $cartItem['course_date_id'], 'degree_id' => $cartItem['degree_id']]
                        );
                    }
                }

                $bookingUser->save();

                $client = Client::find($cartItem['client_id']);
                $course = Course::find($cartItem['course_id']);

                if ($course) {
                    $sportId = $course->sport_id;

                    // Incluir registros soft-deleted para evitar violar la restricciÃ³n Ãºnica
                    $existingClientSport = ClientSport::withTrashed()
                        ->where('client_id', $client->id)
                        ->where('sport_id', $sportId)
                        ->where('school_id', $school['id'])
                        ->first();

                    if ($existingClientSport) {
                        // Si estaba eliminado, restaurar; actualizar degree si aplica
                        if ($existingClientSport->trashed()) {
                            $existingClientSport->restore();
                        }
                        if (!empty($cartItem['degree_id'])) {
                            $existingClientSport->degree_id = $cartItem['degree_id'];
                            $existingClientSport->save();
                        }
                    } else {
                        ClientSport::create([
                            'client_id' => $client->id,
                            'sport_id' => $sportId,
                            'school_id' => $school['id'],
                            'degree_id' => $cartItem['degree_id']
                        ]);
                    }
                }


                // Guardar extras si existen
                if (isset($cartItem['extras'])) {
                    foreach ($cartItem['extras'] as $extra) {
                        BookingUserExtra::create([
                            'booking_user_id' => $bookingUser->id,
                            'course_extra_id' => $extra['course_extra_id'],
                            'quantity' => 1
                        ]);
                    }
                }
            }

            // Procesar Vouchers
            if (!empty($data['vouchers'])) {
                foreach ($data['vouchers'] as $voucherData) {
                    $voucher = Voucher::find($voucherData['bonus']['id']);
                    if ($voucher) {
                        $remaining_balance = $voucher->remaining_balance - $voucherData['bonus']['reducePrice'];
                        $voucher->update(['remaining_balance' => $remaining_balance, 'payed' => $remaining_balance <= 0]);

                        VouchersLog::create([
                            'voucher_id' => $voucher->id,
                            'booking_id' => $booking->id,
                            'amount' => $voucherData['bonus']['reducePrice']
                        ]);
                    }
                }
            }

            // Crear un log inicial de la reserva
            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'created by api',
                'user_id' => $data['user_id']
            ]);

            // Crear un registro de pago si el mtodo de pago es 1 o 4
            if (
                isset($data['payment_method_id']) &&
                in_array($data['payment_method_id'], [1, 4]) &&
                $data['paid']
            ) {
                $remainingAmount = $data['price_total'] - $voucherAmount;

                Payment::create([
                    'booking_id' => $booking->id,
                    'school_id' => $school['id'],
                    'amount' => $remainingAmount,
                    'status' => 'paid', // Puedes ajustar el estado segn tu lgica
                    'notes' => $data['selectedPaymentOption'] ?? null,
                    'payrexx_reference' => null, // aqui puedes integrar Payrexx si lo necesitas
                    'payrexx_transaction' => null
                ]);
            }


            DB::commit();
            return $this->sendResponse($booking, 'Reserva creada con xito', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error: '. $e->getFile());
            Log::error('Error: '. $e->getLine());
            Log::error('Error: '. $e->getMessage());
            return $this->sendError('Error al crear la reserva: ' . $e->getMessage(), 500);
        }
    }

    public function updatePayment(Request $request, $id) {
        $data = $request->all();
        $school = $this->getSchool($request);
        $voucherAmount = array_sum(array_column($data['vouchers'], 'bonus.reducePrice'));

        /** @var Booking $booking */
        $booking = $this->bookingRepository->find($id, with: $request->get('with', []));

        if (empty($booking)) {
            return $this->sendError('Booking not found');
        }
        if($voucherAmount > 0){
            $data['paid'] = $data['price_total'] <= $voucherAmount;
        }

        $booking = $this->bookingRepository->update($data, $id);

        $booking->updateCart();

        if($request->has('send_mail') && $request->input('send_mail')) {
            dispatch(function () use ($booking) {
                // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                try {
                    Mail::to($booking->clientMain->email)->send(new BookingInfoUpdateMailer($booking->school, $booking, $booking->clientMain));
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::debug('Admin/BookingController updatePayment: ',
                        $ex->getTrace());
                }
            })->afterResponse();
        }

        if (!empty($data['vouchers'])) {
            foreach ($data['vouchers'] as $voucherData) {
                $voucher = Voucher::find($voucherData['bonus']['id']);
                if ($voucher) {
                    $remaining_balance = $voucher->remaining_balance - $voucherData['bonus']['reducePrice'];
                    $voucher->update(['remaining_balance' => $remaining_balance, 'payed' => $remaining_balance <= 0]);

                    VouchersLog::create([
                        'voucher_id' => $voucher->id,
                        'booking_id' => $booking->id,
                        'amount' => $voucherData['bonus']['reducePrice']
                    ]);
                }
            }
        }

        // Crear un log inicial de la reserva
        BookingLog::create([
            'booking_id' => $booking->id,
            'action' => 'updated by admin',
            'user_id' => $data['user_id']
        ]);

        //TODO: pagos

        // Crear un registro de pago si el mtodo de pago es 1 o 4
        if (in_array($data['payment_method_id'], [1, 4])) {

            $remainingAmount = $data['price_total'] - $voucherAmount;

            Payment::create([
                'booking_id' => $booking->id,
                'school_id' => $school['id'],
                'amount' => $remainingAmount,
                'status' => 'paid', // Puedes ajustar el estado segn tu lgica
                'notes' => $data['selectedPaymentOption'],
                'payrexx_reference' => null, // aqui puedes integrar Payrexx si lo necesitas
                'payrexx_transaction' => null
            ]);
        }

        return $this->sendResponse($booking, 'Booking updated successfully');

    }

    public function update(Request $request) {
        $school = $this->getSchool($request);
        $schoolSettings = $this->getSchoolSettings($school);
        $monitorNotificationService = app(MonitorNotificationService::class);

        $groupId = $request->group_id;
        $bookingId = $request->booking_id;
        $dates = $request->dates;
        $total = $request->total;

        // MEJORA CRTICA: Logging del intento de actualizacin para auditora
        Log::info('BOOKING_UPDATE_ATTEMPT', [
            'booking_id' => $bookingId,
            'group_id' => $groupId,
            'user_id' => auth()->id(),
            'school_id' => $school['id'],
            'timestamp' => now(),
            'ip' => request()->ip()
        ]);

        // MEJORA CRTICA: Validar que la reserva existe y pertenece a la escuela
        $booking = Booking::where('id', $bookingId)
            ->where('school_id', $school['id'])
            ->first();

        if (!$booking) {
            Log::warning('BOOKING_UPDATE_NOT_FOUND', [
                'booking_id' => $bookingId,
                'school_id' => $school['id']
            ]);
            return $this->sendError('Reserva no encontrada o sin permisos');
        }

        DB::beginTransaction();
        try {

            $bookingUsers = BookingUser::where('booking_id', $bookingId)
                ->where('group_id', $groupId)
                ->get();

            $originalPriceTotal = $bookingUsers[0]->price;
            // Lista para almacenar los IDs de los booking_users que estn presentes en la solicitud
            $requestBookingUserIds = [];

            // 2. Iterar sobre los datos recibidos y actualizar o crear los BookingUsers
            foreach ($dates as $date) {
                if(!$date['selected']) {
                    foreach ($date['booking_users'] as $bookingUserData) {
                        $requestBookingUserIds[] = $bookingUserData['id'];

                        // MEJORA CRTICA: Buscar el BookingUser con relaciones para optimizar queries
                        $bookingUser = BookingUser::with(['course', 'courseSubGroup'])
                            ->find($bookingUserData['id']);

                        if ($bookingUser) {
                            // MEJORA CRTICA: Si es curso colectivo y se cambia la fecha/subgrupo, validar capacidad
                            $isChangingCriticalData = (
                                $bookingUser->course_date_id != $date['course_date_id'] ||
                                $bookingUser->date != $date['date']
                            );

                            if ($bookingUser->course && $bookingUser->course->course_type == 1 && $isChangingCriticalData) {
                                Log::info('BOOKING_UPDATE_CAPACITY_CHECK', [
                                    'booking_user_id' => $bookingUser->id,
                                    'old_date' => $bookingUser->date,
                                    'new_date' => $date['date'],
                                    'old_course_date_id' => $bookingUser->course_date_id,
                                    'new_course_date_id' => $date['course_date_id']
                                ]);

                                // SOLUCIN CONCURRENCIA: Validar disponibilidad con lock para curso colectivo
                                $capacityAvailable = DB::transaction(function () use ($date, $bookingUser) {
                                    // Buscar subgrupos candidatos con lock
                                    $candidateSubgroups = CourseSubgroup::where('course_date_id', $date['course_date_id'])
                                        ->where('degree_id', $bookingUser->degree_id)
                                        ->lockForUpdate()
                                        ->get();

                                    foreach ($candidateSubgroups as $subgroup) {
                                        $currentParticipants = BookingUser::where('course_subgroup_id', $subgroup->id)
                                            ->where('status', 1)
                                            ->where('id', '!=', $bookingUser->id) // Excluir este mismo usuario
                                            ->count();

                                        if ($currentParticipants < $subgroup->max_participants) {
                                            // Asignar nuevo subgrupo
                                            $bookingUser->course_group_id = $subgroup->course_group_id;
                                            $bookingUser->course_subgroup_id = $subgroup->id;
                                            return true;
                                        }
                                    }
                                    return false;
                                });

                                if (!$capacityAvailable) {
                                    DB::rollBack();
                                    return $this->sendError(
                                        'No hay plazas disponibles en la nueva fecha para el nivel seleccionado.',
                                        ['date' => $date['date'], 'course_date_id' => $date['course_date_id']]
                                    );
                                }
                            }

                            $newMonitorId = isset($date['monitor']) ? $date['monitor']['id'] : null;
                            $previousMonitorId = $bookingUser->monitor_id;
                            $isGroup = ($bookingUser->course && (int) $bookingUser->course->course_type === 1) || $bookingUser->course_subgroup_id;
                            $typePrefix = $isGroup ? 'group' : 'private';

                            $removalPayload = [
                                'booking_id' => $bookingUser->booking_id,
                                'course_id' => $bookingUser->course_id,
                                'course_date_id' => $bookingUser->course_date_id,
                                'date' => $bookingUser->date,
                                'hour_start' => $bookingUser->hour_start,
                                'hour_end' => $bookingUser->hour_end,
                                'client_id' => $bookingUser->client_id,
                                'course_subgroup_id' => $bookingUser->course_subgroup_id,
                                'course_group_id' => $bookingUser->course_group_id,
                                'group_id' => $bookingUser->group_id,
                                'school_id' => $school['id'] ?? null,
                            ];

                            // Actualizar los campos si el BookingUser existe
                            $bookingUser->update([
                                'date' => $date['date'],
                                'course_date_id' => $date['course_date_id'],
                                'hour_start' => $date['startHour'],
                                'hour_end' => $date['endHour'],
                                'price' => $date['price'],
                                'monitor_id' => $newMonitorId,
                                // Otros campos adicionales aqui
                            ]);

                            $assignmentPayload = [
                                'booking_id' => $bookingUser->booking_id,
                                'course_id' => $bookingUser->course_id,
                                'course_date_id' => $date['course_date_id'],
                                'date' => $date['date'],
                                'hour_start' => $date['startHour'],
                                'hour_end' => $date['endHour'],
                                'client_id' => $bookingUser->client_id,
                                'course_subgroup_id' => $bookingUser->course_subgroup_id,
                                'course_group_id' => $bookingUser->course_group_id,
                                'group_id' => $bookingUser->group_id,
                                'school_id' => $school['id'] ?? null,
                            ];

                            if ($previousMonitorId && $previousMonitorId !== $newMonitorId) {
                                $monitorNotificationService->notifyAssignment(
                                    $previousMonitorId,
                                    "{$typePrefix}_removed",
                                    $removalPayload,
                                    $schoolSettings,
                                    auth()->id()
                                );
                            }

                            if ($newMonitorId && $newMonitorId !== $previousMonitorId) {
                                $monitorNotificationService->notifyAssignment(
                                    $newMonitorId,
                                    "{$typePrefix}_assigned",
                                    $assignmentPayload,
                                    $schoolSettings,
                                    auth()->id()
                                );
                            }

                            // 3. Actualizar los extras: eliminamos los existentes y los creamos nuevamente
                            BookingUserExtra::where('booking_user_id', $bookingUser->id)->delete();

                        } else {
                            // Si el bookingUser no se encuentra, podras lanzar un error o manejarlo de otra forma.
                            return $this->sendError('BookingUser not found', [], 404);
                        }
                    }
                    foreach ($date['utilizers'] as $utilizer) {
                        $bookingUser = BookingUser::where('client_id', $utilizer['id'])
                            ->where('date', $date['date']) // Asegurarse de que coincida con la fecha tambin
                            ->first();

                        if ($bookingUser) {
                            foreach ($utilizer['extras'] as $extra) {
                                BookingUserExtra::create([
                                    'booking_user_id' => $bookingUser->id,
                                    'course_extra_id' => $extra['id'],
                                    'quantity' => 1
                                ]);
                            }
                        }
                    }
                }
            }

            // 4. Poner el status en 2 a los BookingUsers que no estan presentes en la solicitud
            $bookingUsersNotInRequest = $bookingUsers->whereNotIn('id', $requestBookingUserIds);
            foreach ($bookingUsersNotInRequest as $bookingUser) {
                if ($bookingUser->monitor_id) {
                    $bookingUser->loadMissing('course');
                    $isGroupCancelled = ($bookingUser->course && (int) $bookingUser->course->course_type === 1) || $bookingUser->course_subgroup_id;
                    $cancelTypePrefix = $isGroupCancelled ? 'group' : 'private';
                    $cancelPayload = [
                        'booking_id' => $bookingUser->booking_id,
                        'course_id' => $bookingUser->course_id,
                        'course_date_id' => $bookingUser->course_date_id,
                        'date' => $bookingUser->date,
                        'hour_start' => $bookingUser->hour_start,
                        'hour_end' => $bookingUser->hour_end,
                        'client_id' => $bookingUser->client_id,
                        'course_subgroup_id' => $bookingUser->course_subgroup_id,
                        'course_group_id' => $bookingUser->course_group_id,
                        'group_id' => $bookingUser->group_id,
                        'school_id' => $school['id'] ?? null,
                    ];
                    $monitorNotificationService->notifyAssignment(
                        $bookingUser->monitor_id,
                        "{$cancelTypePrefix}_removed",
                        $cancelPayload,
                        $schoolSettings,
                        auth()->id()
                    );
                }
                $bookingUser->update(['status' => 2]);
            }

            $booking = Booking::find($bookingId);
            $newPrice = $booking->price_total + $total - $originalPriceTotal;
            $booking->update(['price_total' => $newPrice]);
            // 5. Cambiar el estado de la reserva si al menos un bookingUser fue puesto en status 2
            if ($bookingUsersNotInRequest->count() > 0) {
                $booking->update(['status' => 3]);
            }
            $booking->loadMissing([
                'bookingUsers', 'bookingUsers.client', 'bookingUsers.degree',
                'bookingUsers.monitor', 'bookingUsers.courseSubGroup',
                'bookingUsers.course', 'bookingUsers.courseDate', 'clientMain',
                "user",
                "clientMain",
                "vouchersLogs.voucher",
                "bookingUsers.course.courseDates.courseGroups.courseSubgroups",
                "bookingUsers.course.courseExtras",
                "bookingUsers.bookingUserExtras.courseExtra",
                "bookingUsers.client",
                "bookingUsers.courseDate",
                "bookingUsers.monitor",
                "bookingUsers.degree",
                "payments",
                "bookingLogs"
            ]);

            // MEJORA CRTICA: Crear log de la actualizacin para auditora
            BookingLog::create([
                'booking_id' => $bookingId,
                'action' => 'updated via admin panel',
                'user_id' => auth()->id(),
                'metadata' => json_encode([
                    'group_id' => $groupId,
                    'dates_modified' => count($dates),
                    'cancelled_booking_users' => $bookingUsersNotInRequest->count(),
                    'price_change' => $newPrice - $booking->price_total
                ])
            ]);

            // LOGGING: xito de la actualizacin
            Log::info('BOOKING_UPDATE_SUCCESS', [
                'booking_id' => $bookingId,
                'group_id' => $groupId,
                'user_id' => auth()->id(),
                'price_change' => $newPrice - $booking->price_total,
                'cancelled_count' => $bookingUsersNotInRequest->count(),
                'updated_count' => count($requestBookingUserIds)
            ]);

            DB::commit();
            return $this->sendResponse($booking, 'Reserva actualizada con xito', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // LOGGING: Error detallado
            Log::error('BOOKING_UPDATE_FAILED', [
                'booking_id' => $bookingId,
                'group_id' => $groupId,
                'user_id' => auth()->id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return $this->sendError('Error al actualizar la reserva: ' . $e->getMessage(), 500);
        }
    }

    public function updateMeetingPoint(Request $request, $id) {
        $school = $this->getSchool($request);
        $booking = Booking::where('id', $id)
            ->where('school_id', $school['id'])
            ->first();

        if (!$booking) {
            return $this->sendError('Booking not found or unauthorized', 404);
        }

        $validator = Validator::make($request->all(), [
            'meeting_point' => 'nullable|string|max:255',
            'meeting_point_address' => 'nullable|string|max:255',
            'meeting_point_instructions' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Invalid meeting point data', $validator->errors(), 422);
        }

        $booking->update([
            'meeting_point' => $request->input('meeting_point', $booking->meeting_point),
            'meeting_point_address' => $request->input('meeting_point_address', $booking->meeting_point_address),
            'meeting_point_instructions' => $request->input('meeting_point_instructions', $booking->meeting_point_instructions)
        ]);

        return $this->sendResponse($booking, 'Meeting point updated successfully');
    }

    private function getSchoolSettings($school): array
    {
        $settings = $school->settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    function createBasket($bookingData) {
        // Agrupar por group_id
        $groupedCartItems = $this->groupCartItemsByGroupId($bookingData['cart']);
        $basket = [];

        // Procesar descuentos, reducciones y seguros
        $totalVoucherDiscount = 0;
        if (!empty($bookingData['vouchers'])) {
            foreach ($bookingData['vouchers'] as $voucher) {
                $totalVoucherDiscount += $voucher['bonus']['reducePrice'];
            }
        }

        // Crear un objeto base para el precio total y otros datos comunes
        $basketBase = [
            'payment_method_id' => $bookingData['payment_method_id'],
            'tva' => !empty($bookingData['price_tva']) ? [
                'name' => 'TVA',
                'quantity' => 1,
                'price' => $bookingData['price_tva']
            ] : null,
            'cancellation_insurance' => !empty($bookingData['price_cancellation_insurance']) ? [
                'name' => 'Cancellation Insurance',
                'quantity' => 1,
                'price' => $bookingData['price_cancellation_insurance']
            ] : null,
            'bonus' => !empty($bookingData['vouchers']) ? [
                'total' => count($bookingData['vouchers']),
                'bonuses' => array_map(function($voucher) {
                    return [
                        'name' => $voucher['bonus']['code'],
                        'quantity' => 1,
                        'price' => -$voucher['bonus']['reducePrice']
                    ];
                }, $bookingData['vouchers']),
            ] : null,
            'reduction' => !empty($bookingData['price_reduction']) ? [
                'name' => 'Reduction',
                'quantity' => 1,
                'price' => -$bookingData['price_reduction']
            ] : null,
            // Puedes aadir ms campos comunes aqui
        ];

        foreach ($groupedCartItems as $group) {
            // Calcular el precio base para el curso, sumando y restando extras
            $priceBase = $group['price_base'];
            $totalExtrasPrice = $group['extra_price'];
            $totalPrice = $group['price'];

            // Crear un objeto solo con price_base y extras
            $basketItem = [
                'price_base' => [
                    'name' => $group['course_name'],
                    'quantity' => count($group['items']),
                    'price' => $priceBase
                ],
                'extras' => [
                    'total' => count($group['extras']),
                    'price' => $totalExtrasPrice,
                    'extras' => $group['extras']
                ],
                'price_total' => $totalPrice,
            ];

            // Agregar el objeto base a cada basket item
            $basket[] = array_merge($basketBase, $basketItem);
        }

        // Crear el arreglo final
        $finalBasket = [];
        foreach ($basket as $item) {
            $finalBasket[] = [
                'name' => [1 => $item['price_base']['name']],
                'quantity' => 1,
                'amount' => $item['price_base']['price'] * 100, // Convertir el precio a centavos
            ];

            // Agregar extras al "basket"
            if (isset($item['extras']['extras']) && count($item['extras']['extras']) > 0) {
                foreach ($item['extras']['extras'] as $extra) {
                    $finalBasket[] = [
                        'name' => [1 => 'Extra: ' . $extra['name']],
                        'quantity' => $extra['quantity'],
                        'amount' => $extra['price'] * 100, // Convertir el precio a centavos
                    ];
                }
            }
        }

        // Agregar bonos al "basket"
        if (isset($basket[0]['bonus']['bonuses']) && count($basket[0]['bonus']['bonuses']) > 0) {
            foreach ($basket[0]['bonus']['bonuses'] as $bonus) {
                $finalBasket[] = [
                    'name' => [1 => 'Bono: ' . $bonus['name']],
                    'quantity' => $bonus['quantity'],
                    'amount' => $bonus['price'] * 100, // Convertir el precio a centavos
                ];
            }
        }

        // Agregar el campo "reduction" al "basket"
        if (isset($basket[0]['reduction'])) {
            $finalBasket[] = [
                'name' => [1 => $basket[0]['reduction']['name']],
                'quantity' => $basket[0]['reduction']['quantity'],
                'amount' => $basket[0]['reduction']['price'] * 100, // Convertir el precio a centavos
            ];
        }

        // Agregar "tva" al "basket"
        if (isset($basket[0]['tva']['name'])) {
            $finalBasket[] = [
                'name' => [1 => $basket[0]['tva']['name']],
                'quantity' => $basket[0]['tva']['quantity'],
                'amount' => $basket[0]['tva']['price'] * 100, // Convertir el precio a centavos
            ];
        }

        // Agregar "Boukii Care" al "basket"
        // BOUKII CARE DESACTIVADO -         // BOUKII CARE DESACTIVADO -         if (isset($basket[0]['boukii_care']['name'])) {
        // BOUKII CARE DESACTIVADO -             $finalBasket[] = [
        // BOUKII CARE DESACTIVADO -         // BOUKII CARE DESACTIVADO -                 'name' => [1 => $basket[0]['boukii_care']['name']],
        // BOUKII CARE DESACTIVADO -         // BOUKII CARE DESACTIVADO -                 'quantity' => $basket[0]['boukii_care']['quantity'],
        // BOUKII CARE DESACTIVADO -         // BOUKII CARE DESACTIVADO -                 'amount' => $basket[0]['boukii_care']['price'] * 100, // Convertir el precio a centavos
        // BOUKII CARE DESACTIVADO -             ];
        // BOUKII CARE DESACTIVADO -         }

        // Agregar "Cancellation Insurance" al "basket"
        if (isset($basket[0]['cancellation_insurance']['name'])) {
            $finalBasket[] = [
                'name' => [1 => $basket[0]['cancellation_insurance']['name']],
                'quantity' => $basket[0]['cancellation_insurance']['quantity'],
                'amount' => $basket[0]['cancellation_insurance']['price'] * 100, // Convertir el precio a centavos
            ];
        }

        return $finalBasket; // Retorna el arreglo final del basket
    }

    function groupCartItemsByGroupId($cartItems) {
        $groupedItems = [];

        foreach ($cartItems as $item) {
            $group_id = $item['group_id'];

            if (!isset($groupedItems[$group_id])) {
                $groupedItems[$group_id] = [
                    'group_id' => $group_id,
                    'course_name' => $item['course_name'],
                    'price_base' =>  $item['price_base'],
                    'extra_price' =>  $item['extra_price'],
                    'price' =>  $item['price'],
                    'extras' => [],
                    'items' => [],
                ];
            }

            // Sumar extras
            if (!empty($item['extras'])) {
                foreach ($item['extras'] as $extra) {
                    $extraPrice = $extra['price'] ;
                    $groupedItems[$group_id]['extras'][] = [
                        'course_extra_id' => $extra['course_extra_id'],
                        'name' => $extra['name'],
                        'price' => $extraPrice,
                        'quantity' => 1,
                    ];
                }
            }

            // Guardar los detalles de cada tem en el grupo
            $groupedItems[$group_id]['items'][] = $item;
        }

        return $groupedItems;
    }


    /**
     * @OA\Post(
     *      path="/admin/bookings/checkbooking",
     *      summary="checkOverlapBooking",
     *      tags={"Admin"},
     *      description="Check overlap booking for a client",
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
    public function checkClientBookingOverlap(Request $request): JsonResponse
    {
        $overlapBookingUsers = [];
        $bookingUserIds = $request->input('bookingUserIds', []);

        foreach ($request->bookingUsers as $bookingUser) {
            if (BookingUser::hasOverlappingBookings($bookingUser, $bookingUserIds)) {
                $overlapBookingUsers[] = $bookingUser;
            }
        }

        if (count($overlapBookingUsers)) {
            return $this->sendResponse($overlapBookingUsers, 'Client has overlapping bookings', 409);
        }

        return $this->sendResponse([], 'Client has no overlapping bookings');
    }

    /**
     * @OA\Post(
     *      path="/admin/bookings/payments/{id}",
     *      summary="payBooking",
     *      tags={"Admin"},
     *      description="Pay specific booking",
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
    public function payBooking(Request $request, $id): JsonResponse
    {
        $school = $this->getSchool($request);

        // MEJORA CRTICA: Validacin de entrada
        $request->validate([
            'payment_method_id' => 'sometimes|integer|in:1,2,3,4',
        ]);

        // MEJORA CRTICA: Validar que la reserva existe y pertenece a la escuela
        $booking = Booking::where('id', $id)
            ->where('school_id', $school['id'])
            ->with(['clientMain', 'bookingUsers'])
            ->first();

        if (!$booking) {
            Log::warning('PAY_BOOKING_NOT_FOUND', [
                'booking_id' => $id,
                'school_id' => $school['id'],
                'user_id' => auth()->id()
            ]);
            return $this->sendError('Booking not found or access denied', [], 404);
        }

        // MEJORA CRTICA: Verificar que la reserva no est ya pagada
        if ($booking->paid) {
            Log::warning('PAY_BOOKING_ALREADY_PAID', [
                'booking_id' => $id,
                'user_id' => auth()->id()
            ]);
            return $this->sendError('Esta reserva ya est pagada');
        }

        // MEJORA CRTICA: Verificar que la reserva no est cancelada
        if ($booking->status == 2) {
            Log::warning('PAY_BOOKING_CANCELLED', [
                'booking_id' => $id,
                'user_id' => auth()->id()
            ]);
            return $this->sendError('No se puede procesar pago de una reserva cancelada');
        }

        $paymentMethod = $request->get('payment_method_id') ?? $booking->payment_method_id;

        // MEJORA CRTICA: Logging del intento de pago
        Log::info('PAY_BOOKING_ATTEMPT', [
            'booking_id' => $id,
            'payment_method' => $paymentMethod,
            'user_id' => auth()->id(),
            'school_id' => $school['id'],
            'booking_total' => $booking->price_total,
            'client_email' => $booking->clientMain->email ?? null,
            'timestamp' => now(),
            'ip' => request()->ip()
        ]);

        // MEJORA CRTICA: Usar transaccin para actualizar mtodo de pago
        DB::beginTransaction();
        try {
            $previousPaymentMethod = $booking->payment_method_id;
            $booking->payment_method_id = $paymentMethod;
            $booking->save();

            // Crear log del cambio de mtodo de pago
            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'payment_method_updated',
                'user_id' => auth()->id(),
                'metadata' => json_encode([
                    'previous_method' => $previousPaymentMethod,
                    'new_method' => $paymentMethod
                ])
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PAY_BOOKING_UPDATE_FAILED', [
                'booking_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Error actualizando mtodo de pago: ' . $e->getMessage(), 500);
        }


        if ($paymentMethod == 1) {
            Log::warning('PAY_BOOKING_UNSUPPORTED_METHOD', [
                'booking_id' => $id,
                'payment_method' => $paymentMethod,
                'user_id' => auth()->id()
            ]);
            return $this->sendError('Payment method not supported for this booking');
        }

        if ($paymentMethod == 2) {
            try {
                $payrexxLink = PayrexxHelpers::createGatewayLink(
                    $school,
                    $booking,
                    $request,
                    $booking->clientMain,
                    'panel'
                );

                if ($payrexxLink) {
                    // LOGGING: xito creando link de pago
                    Log::info('PAY_BOOKING_GATEWAY_SUCCESS', [
                        'booking_id' => $id,
                        'payment_method' => $paymentMethod,
                        'user_id' => auth()->id(),
                        'link_created' => true
                    ]);

                    // Crear log del enlace de pago
                    BookingLog::create([
                        'booking_id' => $booking->id,
                        'action' => 'payment_gateway_link_created',
                        'user_id' => auth()->id(),
                        'metadata' => json_encode([
                            'payment_method' => $paymentMethod,
                            'link_type' => 'gateway'
                        ])
                    ]);

                    return $this->sendResponse($payrexxLink, 'Link retrieved successfully');
                }

                Log::error('PAY_BOOKING_GATEWAY_FAILED', [
                    'booking_id' => $id,
                    'payment_method' => $paymentMethod,
                    'user_id' => auth()->id(),
                    'link_created' => false
                ]);

                return $this->sendError('Link could not be created');

            } catch (\Exception $e) {
                Log::error('PAY_BOOKING_GATEWAY_EXCEPTION', [
                    'booking_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->sendError('Error creating payment link: ' . $e->getMessage(), 500);
            }
        }

        if ($paymentMethod == 3) {

            $payrexxLink = PayrexxHelpers::createPayLink(
                $school,
                $booking,
                $request,
                $booking->clientMain
            );

            if (strlen($payrexxLink) > 1) {

                // Send by email
                try {
                    $bookingData = $booking->fresh();   // To retrieve its generated PayrexxReference
                    $logData = [
                        'booking_id' => $booking->id,
                        'action' => 'send_pay_link',
                        'user_id' => $booking->user_id,
                        'description' => 'Booking pay link sent',
                    ];

                    BookingLog::create($logData);
                    dispatch(function () use ($school, $booking, $bookingData, $payrexxLink) {
                        // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                        try {
                            \Mail::to($booking->clientMain->email)
                                ->send(new BookingPayMailer(
                                    $school,
                                    $bookingData,
                                    $booking->clientMain,
                                    $payrexxLink
                                ));
                        } catch (\Exception $e) {
                            Log::channel('payrexx')->error('PayrexxHelpers sendMailing payBooking Booking ID=' . $booking->id);
                            Log::channel('payrexx')->error($e->getMessage());
                        }
                    });
                } catch (\Exception $e) {
                    Log::channel('payrexx')->error('PayrexxHelpers sendPayEmail payBooking Booking ID=' . $booking->id);
                    Log::channel('payrexx')->error($e->getMessage());
                    return $this->sendError('Link could not be created');
                }



                return $this->sendResponse([], 'Mail sent correctly');

            }
            return $this->sendError('Link could not be created');

        }

        return $this->sendError('Invalid payment method');
    }

    public function mailBooking(Request $request, $id): JsonResponse
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return $this->sendError('Booking not found', [], 404);
        }

        try {
            if($request['is_info']) {
                Mail::to($booking->clientMain->email)
                    ->send(new BookingInfoMailer($booking->school, $booking, $booking->clientMain));
            } else {
                Mail::to($booking->clientMain->email)
                    ->send(new BookingCreateMailer($booking->school, $booking, $booking->clientMain, $request['paid']));
            }
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::debug('BookingControllerMail->createBooking BookingCreateMailer: ' .
                $ex->getMessage());
            return $this->sendError('Error sending mail: '. $ex->getMessage(), 400);
        }

        return $this->sendResponse([], 'Mail sent correctly');
    }

    /**
     * @OA\Post(
     *      path="/admin/bookings/refunds/{id}",
     *      summary="refundBooking",
     *      tags={"Admin"},
     *      description="Refund specific booking",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="ID of the booking to refund",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="amount",
     *          in="query",
     *          description="Amount to refund",
     *          required=true,
     *          @OA\Schema(type="number", format="float")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful refund",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean",
     *                  example=true
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="boolean",
     *                  example= true
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  example="Refund completed successfully"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Booking not found",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean",
     *                  example=false
     *              ),
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  example="Booking not found"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Invalid request",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean",
     *                  example=false
     *              ),
     *              @OA\Property(
     *                   property="error",
     *                   type="string",
     *                   description="Error message"
     *               )
     *          )
     *      )
     * )
     */
    public function refundBooking(Request $request, $id): JsonResponse
    {
        $school = $this->getSchool($request);
        $booking = Booking::with('payments')->find($id);
        $amountToRefund = $request->get('amount');

        if (!$booking) {
            return $this->sendError('Booking not found', 404);
        }

        if (!is_numeric($amountToRefund) || $amountToRefund <= 0) {
            return $this->sendError('Invalid amount', 400);
        }

        $refund = PayrexxHelpers::refundTransaction($booking, $amountToRefund);

        if ($refund) {
            return $this->sendResponse(['refund' => $refund], 'Refund completed successfully');
        }

        return $this->sendError('Refund failed', 500);
    }

    /**
     * @OA\Post(
     *      path="/admin/bookings/cancel",
     *      summary="cancelBooking",
     *      tags={"Admin"},
     *      description="Cancel specific booking or group of bookingIds",
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
    public function cancelBookings(Request $request): JsonResponse
    {
        // Obtiene la escuela
        $school = $this->getSchool($request);

        // MEJORA CRTICA: Validacin de entrada
        $request->validate([
            'bookingUsers' => 'required|array|min:1',
            'bookingUsers.*' => 'integer|exists:booking_users,id',
            'sendEmails' => 'sometimes|boolean',
            'cancelReason' => 'sometimes|string|max:500'
        ]);

        // MEJORA CRTICA: Logging del intento de cancelacin
        Log::info('BOOKING_CANCEL_ATTEMPT', [
            'booking_users_ids' => $request->bookingUsers,
            'user_id' => auth()->id(),
            'school_id' => $school['id'],
            'send_emails' => $request->input('sendEmails', true),
            'cancel_reason' => $request->input('cancelReason', 'No especificado'),
            'timestamp' => now(),
            'ip' => request()->ip()
        ]);

        // Obtiene los BookingUsers de la solicitud con validacin de permisos
        $bookingUsers = BookingUser::whereIn('id', $request->bookingUsers)
            ->whereHas('booking', function($query) use ($school) {
                $query->where('school_id', $school['id']);
            })
            ->get();

        // Verifica si existen BookingUsers
        if ($bookingUsers->isEmpty()) {
            Log::warning('BOOKING_CANCEL_NOT_FOUND', [
                'requested_ids' => $request->bookingUsers,
                'school_id' => $school['id']
            ]);
            return $this->sendError('Booking users not found or access denied', [], 404);
        }

        // MEJORA CRTICA: Verificar que todos los BookingUsers pertenecen a la misma reserva
        $uniqueBookingIds = $bookingUsers->pluck('booking_id')->unique();
        if ($uniqueBookingIds->count() > 1) {
            Log::error('BOOKING_CANCEL_MULTIPLE_BOOKINGS', [
                'booking_ids' => $uniqueBookingIds->toArray(),
                'booking_users_ids' => $request->bookingUsers
            ]);
            return $this->sendError('Los BookingUsers deben pertenecer a la misma reserva');
        }

        // MEJORA CRTICA: Verificar que ningn BookingUser ya est cancelado
        $alreadyCancelled = $bookingUsers->where('status', 2);
        if ($alreadyCancelled->isNotEmpty()) {
            Log::warning('BOOKING_CANCEL_ALREADY_CANCELLED', [
                'cancelled_ids' => $alreadyCancelled->pluck('id')->toArray()
            ]);
            return $this->sendError('Algunos BookingUsers ya estn cancelados');
        }

        // Obtiene la reserva asociada
        $booking = $bookingUsers[0]->booking;

        // Carga las relaciones necesarias
        $booking->loadMissing([
            'bookingUsers', 'bookingUsers.client', 'bookingUsers.degree',
            'bookingUsers.monitor', 'bookingUsers.courseSubGroup',
            'bookingUsers.course', 'bookingUsers.courseDate', 'clientMain',
            "user",
            "clientMain",
            "vouchersLogs.voucher",
            "bookingUsers.course.courseDates.courseGroups.courseSubgroups",
            "bookingUsers.course.courseExtras",
            "bookingUsers.bookingUserExtras.courseExtra",
            "bookingUsers.client",
            "bookingUsers.courseDate",
            "bookingUsers.monitor",
            "bookingUsers.degree",
            "payments",
            "bookingLogs"
        ]);

        // MEJORA CRTICA: Usar transaccin para operaciones atmicas
        DB::beginTransaction();
        try {
            $cancelledPriceTotal = 0;

            // Actualiza el status de los bookingUsers a cancelado (status = 2)
            foreach ($bookingUsers as $bookingUser) {
                $cancelledPriceTotal += $bookingUser->price;
                $bookingUser->status = 2;
                $bookingUser->save();

                // MEJORA CRTICA: Liberar la plaza en cursos colectivos
                if ($bookingUser->course_subgroup_id) {
                    Log::info('BOOKING_CANCEL_CAPACITY_FREED', [
                        'booking_user_id' => $bookingUser->id,
                        'course_subgroup_id' => $bookingUser->course_subgroup_id,
                        'client_id' => $bookingUser->client_id,
                        'date' => $bookingUser->date
                    ]);
                }
            }

            // Verificar si quedan bookingUsers activos (status distinto de 2)
            $activeBookingUsers = $booking->bookingUsers()->where('status', '!=', 2)->exists();

            $previousStatus = $booking->status;

            // Si no hay ms bookingUsers activos, cambia el status de la reserva a 2 (completamente cancelada)
            if (!$activeBookingUsers) {
                $booking->status = 2; // Completamente cancelado
            } else {
                $booking->status = 3; // Parcialmente cancelado
            }

            // MEJORA CRTICA: Recalcular precio solo si no est pagado
            if(!$booking->paid) {
                $booking->reloadPrice();
            }

            $booking->save();

            // MEJORA CRTICA: Crear log detallado de la cancelacin
            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'cancelled via admin panel',
                'user_id' => auth()->id(),
                'metadata' => json_encode([
                    'cancelled_booking_users' => $bookingUsers->pluck('id')->toArray(),
                    'cancelled_price_total' => $cancelledPriceTotal,
                    'previous_status' => $previousStatus,
                    'new_status' => $booking->status,
                    'cancel_reason' => $request->input('cancelReason', 'No especificado'),
                    'send_emails' => $request->input('sendEmails', true)
                ])
            ]);

            DB::commit();

            // LOGGING: xito de la cancelacin
            Log::info('BOOKING_CANCEL_SUCCESS', [
                'booking_id' => $booking->id,
                'cancelled_booking_users' => $bookingUsers->pluck('id')->toArray(),
                'cancelled_count' => $bookingUsers->count(),
                'cancelled_price_total' => $cancelledPriceTotal,
                'previous_status' => $previousStatus,
                'new_status' => $booking->status,
                'user_id' => auth()->id()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // LOGGING: Error detallado
            Log::error('BOOKING_CANCEL_FAILED', [
                'booking_users_ids' => $request->bookingUsers,
                'booking_id' => $booking->id ?? null,
                'user_id' => auth()->id(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return $this->sendError('Error al cancelar la reserva: ' . $e->getMessage(), 500);
        }

        // Flag para enviar correos, por defecto true
        $sendEmails = $request->input('sendEmails', true);

        if ($sendEmails) {
            // Enviar correo al comprador principal (clientMain)
            dispatch(function () use ($school, $booking, $bookingUsers) {
                $buyerUser = $booking->clientMain;

                // N.B. try-catch porque algunos usuarios de prueba ingresan emails inexistentes
                try {
                    \Mail::to($buyerUser->email)
                        ->send(new BookingCancelMailer(
                            $school,
                            $booking,
                            $bookingUsers,
                            $buyerUser,
                            null
                        ));
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::debug('BookingController->cancelBookingFull BookingCancelMailer: ' . $ex->getMessage());
                }
            })->afterResponse();
        }

        return $this->sendResponse($booking, 'Cancel completed successfully');
    }

    private function resolveMeetingPointFromCourses(array $courseIds): array
    {
        $defaults = [
            'meeting_point' => null,
            'meeting_point_address' => null,
            'meeting_point_instructions' => null,
        ];

        $uniqueCourseIds = array_values(array_unique(array_filter($courseIds)));
        if (empty($uniqueCourseIds)) {
            return $defaults;
        }

        $courses = Course::whereIn('id', $uniqueCourseIds)
            ->get(['id', 'meeting_point', 'meeting_point_address', 'meeting_point_instructions']);

        if ($courses->isEmpty()) {
            return $defaults;
        }

        $meetingData = $courses->map(function ($course) {
            return [
                'meeting_point' => $course->meeting_point,
                'meeting_point_address' => $course->meeting_point_address,
                'meeting_point_instructions' => $course->meeting_point_instructions,
            ];
        });

        $withMeeting = $meetingData->filter(fn ($mp) => !empty($mp['meeting_point']));

        if ($withMeeting->isEmpty()) {
            return $defaults;
        }

        if ($meetingData->count() === 1) {
            return $withMeeting->first();
        }

        $first = $withMeeting->first();
        $allHaveMeeting = $withMeeting->count() === $meetingData->count();
        $allSame = $allHaveMeeting && $withMeeting->every(function ($mp) use ($first) {
            return $mp['meeting_point'] === $first['meeting_point']
                && $mp['meeting_point_address'] === $first['meeting_point_address']
                && $mp['meeting_point_instructions'] === $first['meeting_point_instructions'];
        });

        return $allSame ? $first : $defaults;
    }
}

