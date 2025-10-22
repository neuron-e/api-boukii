<?php

namespace App\Http\Controllers\BookingPage;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\PayrexxHelpers;
use App\Http\Resources\API\BookingResource;
use App\Mail\BookingCancelMailer;
use App\Mail\BookingPayMailer;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingUser;
use App\Models\BookingUserExtra;
use App\Models\Client;
use App\Models\Course;
use App\Models\CourseExtra;
use App\Models\CourseSubgroup;
use App\Models\Degree;
use App\Models\MonitorNwd;
use App\Models\MonitorSportsDegree;
use App\\Models\\Voucher;\nuse App\\Models\\DiscountCode;
use App\Models\VouchersLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Response;\nuse App\\Services\\DiscountCodeService;\nuse Illuminate\\Support\\Arr;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class BookingController extends SlugAuthController
{

    /**
     * @OA\Post(
     *      path="/slug/bookings",
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
        // Iniciar una transacción
        DB::beginTransaction();

        try {
            $data = $request->all();

            $grossPriceTotal = (float) Arr::get($data, 'price_total', 0);
            $discountCodeId = Arr::get($data, 'discount_code_id');
            $discountCodeAmount = (float) Arr::get($data, 'discount_code_amount', 0);

            // Crear la reserva (Booking)
            $booking = Booking::create([
                'school_id' => Arr::get($data, 'school_id'),
                'client_main_id' => Arr::get($data, 'client_main_id'),
                'price_total' => $grossPriceTotal,
                'has_tva' => Arr::get($data, 'has_tva'),
                'price_tva' => Arr::get($data, 'price_tva'),
                'has_boukii_care' => Arr::get($data, 'has_boukii_care'),
                'price_boukii_care' => Arr::get($data, 'price_boukii_care'),
                'has_cancellation_insurance' => Arr::get($data, 'has_cancellation_insurance'),
                'price_cancellation_insurance' => Arr::get($data, 'price_cancellation_insurance'),
                'basket' => Arr::get($data, 'basket'),
                'source' => Arr::get($data, 'source'),
                'status' => 1,
                'currency' => 'CHF',
                'discount_code_id' => $discountCodeId,
                'discount_code_value' => $discountCodeAmount,
            ]);

            // Crear BookingUser para cada detalle
            $groupId = 1; // Inicia el contador de grupo
            $bookingUsers = []; // Para almacenar los objetos BookingUser

            foreach ($data['cart'] as $cartItem) {
                foreach ($cartItem['details'] as $detail) {
                    if (array_key_exists('course_subgroup_id', $detail) && $detail['course_subgroup_id']) {
                        $courseSubgroup = CourseSubgroup::find($detail['course_subgroup_id']);
                        if ($courseSubgroup) {
                            $monitorId = $courseSubgroup->monitor_id;
                            $degreeId = $courseSubgroup->degree_id;
                        }
                    } else {
                        $monitorId = $detail['monitor_id'] ?? null;
                        $degreeId = $detail['degree_id'] ?? null;
                    }

                    $bookingUser = new BookingUser([
                        'school_id' => $detail['school_id'],
                        'booking_id' => $booking->id,
                        'client_id' => $detail['client_id'],
                        'price' => $detail['price'],
                        'currency' => $detail['currency'],
                        'course_id' => $detail['course_id'],
                        'course_date_id' => $detail['course_date_id'],
                        'course_group_id' => $detail['course_group_id'],
                        'course_subgroup_id' => $detail['course_subgroup_id'],
                        'monitor_id' => $monitorId,
                        'degree_id' => $degreeId,
                        'date' => $detail['date'],
                        'hour_start' => $detail['hour_start'],
                        'hour_end' => $detail['hour_end'],
                        'group_id' => $groupId,
                        'accepted' => array_key_exists('course_subgroup_id', $detail) && $detail['course_subgroup_id'],
                        'deleted_at' => now(),
                    ]);

                    $bookingUser->save();
                    $bookingUsers[] = $bookingUser;

                    if (isset($detail['extra']) && is_array($detail['extra'])) {
                        foreach ($detail['extra'] as $extra) {
                            BookingUserExtra::create([
                                'booking_user_id' => $bookingUser->id,
                                'course_extra_id' => $extra['id'],
                                'quantity' => 1
                            ]);
                        }
                    }
                }
                $groupId++; // Incrementar el `group_id` para el siguiente `cartItem`
            }
            $booking->deleted_at = now();

            if (!empty($data['vouchers'])) {
                foreach ($data['vouchers'] as $voucherData) {
                    $voucher = Voucher::find($voucherData['id']);
                    if ($voucher) {
                        $remaining_balance = $voucher->remaining_balance - $voucherData['reducePrice'];
                        $voucher->update(['remaining_balance' => $remaining_balance, 'payed' => $remaining_balance <= 0]);

                        VouchersLog::create([
                            'voucher_id' => $voucher->id,
                            'booking_id' => $booking->id,
                            'amount' => $voucherData['reducePrice']
                        ]);
                    }
                }
                if (Arr::get($data, 'voucherAmount', 0) >= max(0, $grossPriceTotal - $discountCodeAmount)) {
                    $booking->deleted_at = null;

                    $booking->paid = true;
                    foreach ($bookingUsers as $bookingUser) {
                        $bookingUser->deleted_at = null;
                        $bookingUser->save();
                    }
                }
            }
            $booking->save();
            $client = Client::find($data['client_main_id'])->load('user');

            if ($discountCodeId) {
                $userId = $client->user->id ?? null;
                if ($userId) {
                    app(DiscountCodeService::class)->recordCodeUsage($discountCodeId, $userId, $booking->id, $discountCodeAmount);
                }
            }

            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'page_created',
                'user_id' => $client->user->id,
            ]);

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Reserva creada con éxito', 'booking_id' => $booking->id], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::debug('BookingPage/BookingController store: ',
                $e->getTrace());
            // Revertir la transacción si ocurre un error
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la reserva', 'error' => $e->getMessage()], 500);
        }
    }



    /**
     * @OA\Post(
     *      path="/slug/bookings/checkbooking",
     *      summary="checkOverlapBooking",
     *      tags={"BookingPage"},
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
        $clientIds = [];
        $highestDegreeId = 0;
        $date = null;
        $startTime = null;
        $endTime = null;

        // Obtiene información común para todos los bookingUsers
        foreach ($request->bookingUsers as $bookingUser) {
            if ($bookingUser['course']['course_type'] == 2) {
                $clientIds[] = $bookingUser['client']['id'];

                // Verificar si degree_id existe antes de acceder
                $highestDegreeId = isset($bookingUser['degree_id']) ? $bookingUser['degree_id'] : 0;

                // Obtener el degree_id más alto solo una vez
                if ($highestDegreeId === 0) {
                    $sportId = $bookingUser['course']['sport_id'];

                    if (!empty($bookingUser['client']['sports']) && is_array($bookingUser['client']['sports'])) {
                        $clientDegrees = $bookingUser['client']['sports'];

                        foreach ($clientDegrees as $clientDegree) {
                            if (
                                isset($clientDegree['pivot']['sport_id'], $clientDegree['pivot']['degree_id']) &&
                                $clientDegree['pivot']['sport_id'] == $sportId &&
                                $clientDegree['pivot']['degree_id'] > $highestDegreeId
                            ) {
                                $highestDegreeId = $clientDegree['pivot']['degree_id'];
                            }
                        }
                    }
                }

                // Obtener la fecha, hora de inicio y hora de fin solo una vez
                if ($date === null) {
                    $date = $bookingUser['date'] ?? null;
                    $startTime = $bookingUser['hour_start'] ?? null;
                    $endTime = $bookingUser['hour_end'] ?? null;
                }
            }



            if (BookingUser::hasOverlappingBookings($bookingUser, [])) {
                return $this->sendError('Client has booking on that date');
            }
        }

        if($request->bookingUsers[0]['course']['course_type'] == 2) {
            $degreeOrder = Degree::find($highestDegreeId)->degree_order ?? null;

           // dd($highestDegreeId);

            // Crear el array con las condiciones necesarias
            $monitorAvailabilityData = [
                'date' => $date,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'clientIds' => $clientIds,
                'sportId' => $bookingUser['course']['sport_id']
            ];

            // Solo añadir 'minimumDegreeId' si existe 'degreeOrder'
            if ($degreeOrder !== null) {
                $monitorAvailabilityData['minimumDegreeId'] = $degreeOrder;
            }

            $monitorAvailabilityRequest = new Request($monitorAvailabilityData);

            if (empty($this->getMonitorsAvailable($monitorAvailabilityRequest))) {
                return $this->sendError('No monitor available on that date');
            }
        }

        return $this->sendResponse([], 'Client has not overlaps bookings');
    }

    public function getMonitorsAvailable(Request $request): array
    {
        $school = $this->school;

        $isAnyAdultClient = false;
        $clientLanguages = [];

        if ($request->has('clientIds') && is_array($request->clientIds)) {
            foreach ($request->clientIds as $clientId) {
                $client = Client::find($clientId);
                if ($client) {
                    $clientAge = Carbon::parse($client->birth_date)->age;
                    if ($clientAge >= 18) {
                        $isAnyAdultClient = true;
                    }

                    // Agregar idiomas del cliente al array de idiomas
                    for ($i = 1; $i <= 6; $i++) {
                        $languageField = 'language' . $i . '_id';
                        if (!empty($client->$languageField)) {
                            $clientLanguages[] = $client->$languageField;
                        }
                    }
                }
            }
        }

        $clientLanguages = array_unique($clientLanguages);

        // Paso 1: Obtener todos los monitores que tengan el deporte y grado requerido.
        $eligibleMonitors =
            MonitorSportsDegree::whereHas('monitorSportAuthorizedDegrees', function ($query) use ($school, $request) {
                $query->where('school_id', $school->id);

                // Solo aplicar la condición de degree_order si minimumDegreeId no es null
                if (!is_null($request->minimumDegreeId)) {
                    $query->whereHas('degree', function ($q) use ($request) {
                        $q->where('degree_order', '>=', $request->minimumDegreeId);
                    });
                }
            })
                ->where('sport_id', $request->sportId)
                ->when($isAnyAdultClient, function ($query) {
                    return $query->where('allow_adults', true);
                })
                ->with(['monitor' => function ($query) use ($school, $clientLanguages) {
                    $query->whereHas('monitorsSchools', function ($subQuery) use ($school) {
                        $subQuery->where('school_id', $school->id)
                            ->where('active_school', 1);
                    });

                    // Filtrar monitores por idioma si clientLanguages está presente
                    if (!empty($clientLanguages)) {
                        $query->where(function ($query) use ($clientLanguages) {
                            $query->orWhereIn('language1_id', $clientLanguages)
                                ->orWhereIn('language2_id', $clientLanguages)
                                ->orWhereIn('language3_id', $clientLanguages)
                                ->orWhereIn('language4_id', $clientLanguages)
                                ->orWhereIn('language5_id', $clientLanguages)
                                ->orWhereIn('language6_id', $clientLanguages);
                        });
                    }
                }])
                ->get()
                ->pluck('monitor');



        $busyMonitors = BookingUser::whereDate('date', $request->date)
            ->where(function ($query) use ($request) {
                $query->whereTime('hour_start', '<', Carbon::createFromFormat('H:i', $request->endTime))
                    ->whereTime('hour_end', '>', Carbon::createFromFormat('H:i', $request->startTime))
                    ->where('status', 1);
            })->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2); // La Booking no debe tener status 2
            })
            ->pluck('monitor_id')
            ->merge(MonitorNwd::whereDate('start_date', '<=', $request->date)
                ->whereDate('end_date', '>=', $request->date)
                ->where(function ($query) use ($request) {
                    // Aquí incluimos la lógica para verificar si es un día entero
                    $query->where('full_day', true)
                        ->orWhere(function ($timeQuery) use ($request) {
                            $timeQuery->whereTime('start_time', '<',
                                Carbon::createFromFormat('H:i', $request->endTime))
                                ->whereTime('end_time', '>', Carbon::createFromFormat('H:i', $request->startTime));
                        });
                })
                ->pluck('monitor_id'))
            ->merge(CourseSubgroup::whereHas('courseDate', function ($query) use ($request) {
                $query->whereDate('date', $request->date)
                    ->whereTime('hour_start', '<', Carbon::createFromFormat('H:i', $request->endTime))
                    ->whereTime('hour_end', '>', Carbon::createFromFormat('H:i', $request->startTime));
            })
                ->pluck('monitor_id'))
            ->unique();

        // Paso 3: Filtrar los monitores elegibles excluyendo los ocupados.
        $availableMonitors = $eligibleMonitors->whereNotIn('id', $busyMonitors);

        // Eliminar los elementos nulos
        $availableMonitors = array_filter($availableMonitors->toArray());


        // Reindexar el array para eliminar las claves
        $availableMonitors = array_values($availableMonitors);


        // Paso 4: Devolver los monitores disponibles.
        return $availableMonitors;

    }

        private function areMonitorsAvailable($monitors, $date, $startTime, $endTime): bool
    {
        foreach ($monitors as $monitor) {
            if (!Monitor::isMonitorBusy($monitor->id, $date, $startTime, $endTime)) {
                return true; // Hay al menos un monitor disponible
            }
        }
        return false; // Ningún monitor está disponible en el rango
    }

    /**
     * @OA\Post(
     *      path="/slug/bookings/payments/{id}",
     *      summary="payBooking",
     *      tags={"BookingPage"},
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
        $school = $this->school;
        $booking = Booking::withTrashed()->find($id);
        $paymentMethod = 2;

        if (!$booking) {
            return $this->sendError('Booking not found');
        }

        $booking->payment_method_id = $paymentMethod;
        $booking->save();

        $payrexxLink = PayrexxHelpers::createGatewayLink(
            $school,
            $booking,
            $request,
            $booking->clientMain,
            $request->redirectUrl
        );

        if ($payrexxLink) {
            return $this->sendResponse($payrexxLink, 'Link retrieved successfully');
        }

        return $this->sendError('Link could not be created. Booking has been removed.');


    }

    /**
     * @OA\Post(
     *      path="/slug/bookings/refunds/{id}",
     *      summary="refundBooking",
     *      tags={"BookingPage"},
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
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/Booking")
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
     *                  example={"message": "Booking not found"}
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
     *                  property="error",
     *                  type="string",
     *                  example={"message": "Invalid amount"}
     *              )
     *          )
     *      )
     * )
     */
    public function refundBooking(Request $request, $id): JsonResponse
    {
        $school = $this->school;
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
     *      path="/slug/bookings/cancel",
     *      summary="cancelBooking",
     *      tags={"BookingPage"},
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
        $school = $this->school;


        $bookingUsers = BookingUser::whereIn('id', $request->bookingUsers)->get();
        $booking = $bookingUsers[0]->booking;

        if (!$bookingUsers) {
            return $this->sendError('Booking users not found', [], 404);
        }

        $booking->loadMissing(['bookingUsers', 'bookingUsers.client', 'bookingUsers.degree',
            'bookingUsers.monitor', 'bookingUsers.courseSubGroup', 'bookingUsers.course',
            'bookingUsers.courseDate', 'clientMain']);

        /*        foreach ($bookingUsers as $bookingUser) {
                    $bookingUser->status = 2;
                    $bookingUser->save();
                }*/

        // Tell buyer user by email
        dispatch(function () use ($school, $booking, $bookingUsers) {
            $buyerUser = $booking->clientMain;

            // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
            try
            {
                \Mail::to($buyerUser->email)
                    ->send(new BookingCancelMailer(
                        $school,
                        $booking,
                        $bookingUsers,
                        $buyerUser,
                        null
                    ));
            }
            catch (\Exception $ex)
            {
                \Illuminate\Support\Facades\Log::debug('BookingController->cancelBookingFull BookingCancelMailer: ' . $ex->getMessage());
            }
        })->afterResponse();

        return $this->sendResponse([], 'Cancel completed successfully');

    }

}


