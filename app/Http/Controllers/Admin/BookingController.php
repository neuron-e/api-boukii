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
use App\Models\BookingUsers2;
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
use App\Services\CourseAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Payrexx\Payrexx;
use Response;
use Validator;

;

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

        // VALIDACIÓN CRÍTICA: Verificar coherencia cliente-participantes
        if (isset($data['client_main_id']) && isset($data['cart'])) {
            $clientMainId = $data['client_main_id'];
            $mainClient = Client::with('utilizers')->find($clientMainId);

            if (!$mainClient) {
                return $this->sendError('Cliente principal no encontrado');
            }

            // Obtener IDs válidos: cliente principal + sus utilizers
            $validClientIds = [$clientMainId];
            foreach ($mainClient->utilizers as $utilizer) {
                $validClientIds[] = $utilizer->id;
            }

            // Verificar que todos los participantes sean válidos
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

        $basketJson = json_encode($basketData); // Asegúrate de que no haya problemas con la conversión a JSON

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
                'currency' => $data['cart'][0]['currency'] // Si todas las líneas tienen la misma moneda
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

                // MEJORA CRÍTICA: Verificación y asignación atómica de subgrupos para cursos colectivos
                if ($cartItem['course_type'] == 1) {
                    // LOGGING: Registrar intento de reserva para análisis de concurrencia
                    Log::info('BOOKING_CONCURRENCY_ATTEMPT', [
                        'course_date_id' => $cartItem['course_date_id'],
                        'degree_id' => $cartItem['degree_id'],
                        'client_id' => $cartItem['client_id'],
                        'timestamp' => now(),
                        'user_agent' => request()->userAgent()
                    ]);

                    // SOLUCI�N CONCURRENCIA: Usar transacci�n con lock pessimista
                    $availabilityService = app(CourseAvailabilityService::class);
                    $dateForAvailability = $cartItem['date']
                        ?? ($courseDate && $courseDate->date ? Carbon::parse($courseDate->date)->format('Y-m-d') : null);

                    $subgroupAssigned = DB::transaction(function () use ($cartItem, $bookingUser, $availabilityService, $dateForAvailability) {
                        // 1. Obtener todos los subgrupos candidatos con LOCK FOR UPDATE
                        $candidateSubgroups = CourseSubgroup::where('course_date_id', $cartItem['course_date_id'])
                            ->where('degree_id', $cartItem['degree_id'])
                            ->lockForUpdate()
                            ->get();

                        // 2. Verificar disponibilidad real contando BookingUsers activos
                        foreach ($candidateSubgroups as $subgroup) {
                            $effectiveMax = $dateForAvailability
                                ? $availabilityService->getMaxParticipants($subgroup, $dateForAvailability)
                                : $subgroup->max_participants;

                            if ($effectiveMax !== null && $effectiveMax <= 0) {
                                continue;
                            }

                            $currentParticipantsQuery = BookingUser::where('course_subgroup_id', $subgroup->id)
                                ->where('status', 1)
                                ->whereHas('booking', function ($query) {
                                    $query->where('status', '!=', 2);
                                });

                            if ($dateForAvailability) {
                                $currentParticipantsQuery->whereDate('date', $dateForAvailability);
                            }

                            $currentParticipants = $currentParticipantsQuery->count();

                            // 3. Verificaci�n at�mica: �hay espacio disponible?
                            if ($effectiveMax === null || $currentParticipants < $effectiveMax) {
                                // 4. �XITO: Asignar inmediatamente mientras tenemos el lock
                                $bookingUser->course_group_id = $subgroup->course_group_id;
                                $bookingUser->course_subgroup_id = $subgroup->id;

                                // LOGGING: Registro exitoso de asignaci�n
                                Log::info('BOOKING_CONCURRENCY_SUCCESS', [
                                    'subgroup_id' => $subgroup->id,
                                    'participants_before' => $currentParticipants,
                                    'max_participants' => $effectiveMax,
                                    'client_id' => $cartItem['client_id'],
                                    'date' => $dateForAvailability
                                ]);

                                return true; // Subgrupo asignado exitosamente
                            }
                        }

                        // LOGGING: No hay subgrupos disponibles
                        Log::warning('BOOKING_CONCURRENCY_FULL', [
                            'course_date_id' => $cartItem['course_date_id'],
                            'degree_id' => $cartItem['degree_id'],
                            'date' => $dateForAvailability,
                            'total_subgroups_checked' => $candidateSubgroups->count()
                        ]);

                        return false; // No hay subgrupos disponibles
                    });
                    // 4. Verificar resultado de la asignaci�n at�mica
                    if (!$subgroupAssigned) {
                        DB::rollBack();
                        return $this->sendError(
                            'No hay plazas disponibles en el nivel ' . $cartItem['degree_id'] .
                            ' para la fecha solicitada. El curso est� completo.',
                            ['course_date_id' => $cartItem['course_date_id'], 'degree_id' => $cartItem['degree_id']]
                        );
                    }
                }

                $bookingUser->save();

                // MEJORA CRÍTICA: Liberar la plaza en cursos colectivos
                if ($bookingUser->course_subgroup_id) {
                    Log::info('BOOKING_CANCEL_CAPACITY_FREED', [
                        'booking_user_id' => $bookingUser->id,
                        'course_subgroup_id' => $bookingUser->course_subgroup_id,
                        'client_id' => $bookingUser->client_id,
                        'date' => $bookingUser->date
                    ]);

                    // NUEVO: Invalidar cache de disponibilidad al liberar plaza
                    $subgroup = CourseSubgroup::find($bookingUser->course_subgroup_id);
                    if ($subgroup && $bookingUser->date) {
                        $subgroup->invalidateAvailabilityCache($bookingUser->date);
                    }
                }
            }

            // Verificar si quedan bookingUsers activos (status distinto de 2)
            $activeBookingUsers = $booking->bookingUsers()->where('status', '!=', 2)->exists();

            $previousStatus = $booking->status;

            // Si no hay más bookingUsers activos, cambia el status de la reserva a 2 (completamente cancelada)
            if (!$activeBookingUsers) {
                $booking->status = 2; // Completamente cancelado
            } else {
                $booking->status = 3; // Parcialmente cancelado
            }

            // MEJORA CRÍTICA: Recalcular precio solo si no está pagado
            if(!$booking->paid) {
                $booking->reloadPrice();
            }

            $booking->save();

            // MEJORA CRÍTICA: Crear log detallado de la cancelación
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

            // LOGGING: Éxito de la cancelación
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
}







