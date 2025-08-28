<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Http\Controllers\PayrexxHelpers;
use App\Http\Controllers\PayyoHelpers;
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
                'has_boukii_care' => $data['has_boukii_care'],
                'has_cancellation_insurance' => $data['has_cancellation_insurance'],
                'has_reduction' => $data['has_reduction'],
                'price_total' => $data['price_total'],
                'price_reduction' => $data['price_reduction'],
                'price_tva' => $data['price_tva'],
                'price_boukii_care' => $data['price_boukii_care'],
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

                // Verificación y asignación de subgrupos si es necesario
                if ($cartItem['course_type'] == 1) {
                    $subgroup = CourseSubgroup::where('course_date_id', $cartItem['course_date_id'])
                        ->where('degree_id', $cartItem['degree_id'])
                        ->whereHas('bookingUsers', function ($query) {
                            $query->where('status', 1);
                        }, '<', DB::raw('max_participants'))
                        ->first();

                    if ($subgroup) {
                        $bookingUser->course_group_id = $subgroup->course_group_id;
                        $bookingUser->course_subgroup_id = $subgroup->id;
                    } else {
                        DB::rollBack();
                        return $this->sendError('Not subgroups available for the degree: ' . $cartItem['degree_id']);
                    }
                }

                $bookingUser->save();

                $client = Client::find($cartItem['client_id']);
                $course = Course::find($cartItem['course_id']);

                if ($course) {
                    $sportId = $course->sport_id;

                    $existingClientSport = $client->clientSports()
                        ->where('sport_id', $sportId)
                        ->where('school_id', $school['id'])
                        ->first();

                    if (!$existingClientSport) {
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

            // Crear un registro de pago si el método de pago es 1 o 4
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
                    'status' => 'paid', // Puedes ajustar el estado según tu lógica
                    'notes' => $data['selectedPaymentOption'] ?? null,
                    'payrexx_reference' => null, // Aquí puedes integrar Payrexx si lo necesitas
                    'payrexx_transaction' => null,
                    'payyo_reference' => null,
                    'payyo_transaction' => null
                ]);
            }


            DB::commit();
            return $this->sendResponse($booking, 'Reserva creada con éxito', 201);

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

        // Crear un registro de pago si el método de pago es 1 o 4
        if (in_array($data['payment_method_id'], [1, 4])) {

            $remainingAmount = $data['price_total'] - $voucherAmount;

            Payment::create([
                'booking_id' => $booking->id,
                'school_id' => $school['id'],
                'amount' => $remainingAmount,
                'status' => 'paid', // Puedes ajustar el estado según tu lógica
                'notes' => $data['selectedPaymentOption'],
                'payrexx_reference' => null, // Aquí puedes integrar Payrexx si lo necesitas
                'payrexx_transaction' => null,
                'payyo_reference' => null,
                'payyo_transaction' => null
            ]);
        }

        return $this->sendResponse($booking, 'Booking updated successfully');

    }

    public function update(Request $request) {
        $school = $this->getSchool($request);

        //TODO: Check OVERLAP
        $groupId = $request->group_id;
        $bookingId = $request->booking_id;
        $dates = $request->dates;
        $total = $request->total;

        DB::beginTransaction();
        try {

            $bookingUsers = BookingUser::where('booking_id', $bookingId)
                ->where('group_id', $groupId)
                ->get();

            $originalPriceTotal = $bookingUsers[0]->price;
            // Lista para almacenar los IDs de los booking_users que están presentes en la solicitud
            $requestBookingUserIds = [];

            // 2. Iterar sobre los datos recibidos y actualizar o crear los BookingUsers
            foreach ($dates as $date) {
                if(!$date['selected']) {
                    foreach ($date['booking_users'] as $bookingUserData) {
                        $requestBookingUserIds[] = $bookingUserData['id'];

                        // Buscar el BookingUser
                        $bookingUser = BookingUser::find($bookingUserData['id']);


                        if ($bookingUser) {
                            // Actualizar los campos si el BookingUser existe
                            $bookingUser->update([
                                'date' => $date['date'],
                                'course_date_id' => $date['course_date_id'],
                                'hour_start' => $date['startHour'],
                                'hour_end' => $date['endHour'],
                                'price' => $date['price'],
                                'monitor_id' => isset($date['monitor']) ? $date['monitor']['id'] : null,  // Verificar si monitor existe
                                // Otros campos adicionales aquí
                            ]);

                            // 3. Actualizar los extras: eliminamos los existentes y los creamos nuevamente
                            BookingUserExtra::where('booking_user_id', $bookingUser->id)->delete();

                        } else {
                            // Si el bookingUser no se encuentra, podrías lanzar un error o manejarlo de otra forma.
                            return $this->sendError('BookingUser not found', [], 404);
                        }
                    }
                    foreach ($date['utilizers'] as $utilizer) {
                        $bookingUser = BookingUser::where('client_id', $utilizer['id'])
                            ->where('date', $date['date']) // Asegurarse de que coincida con la fecha también
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

            // 4. Poner el status en 2 a los BookingUsers que no están presentes en la solicitud
            $bookingUsersNotInRequest = $bookingUsers->whereNotIn('id', $requestBookingUserIds);
            foreach ($bookingUsersNotInRequest as $bookingUser) {
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
            DB::commit();
            return $this->sendResponse($booking, 'Reserva actualizada con éxito', 201);

        } catch (\Exception $e) {
            Log::error('Error: ', $e->getTrace());
            DB::rollBack();
            return $this->sendError('Error al actualizar la reserva: ' . $e->getMessage(), 500);
        }
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
            // Puedes añadir más campos comunes aquí
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
        if (isset($basket[0]['boukii_care']['name'])) {
            $finalBasket[] = [
                'name' => [1 => $basket[0]['boukii_care']['name']],
                'quantity' => $basket[0]['boukii_care']['quantity'],
                'amount' => $basket[0]['boukii_care']['price'] * 100, // Convertir el precio a centavos
            ];
        }

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

            // Guardar los detalles de cada ítem en el grupo
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
        $booking = Booking::find($id);
        $paymentMethod = $request->get('payment_method_id') ?? $booking->payment_method_id;


        if (!$booking) {
            return $this->sendError('Booking not found', [], 404);
        }

        $booking->payment_method_id = $paymentMethod;
        $booking->save();


        if ($paymentMethod == 1) {
            return $this->sendError('Payment method not supported for this booking');
        }

        if ($paymentMethod == 2) {

            if ($school->payment_provider === 'payyo') {
                $payLink = PayyoHelpers::createPayLink(
                    $school,
                    $booking,
                    $request->all(),
                    $booking->clientMain,
                    'panel'
                );

                if ($payLink) {
                    return $this->sendResponse($payLink, 'Link retrieved successfully');
                }

                return $this->sendError('Link could not be created');
            }

            $payrexxLink = PayrexxHelpers::createGatewayLink(
                $school,
                $booking,
                $request,
                $booking->clientMain,
                'panel'
            );

            if ($payrexxLink) {
                return $this->sendResponse($payrexxLink, 'Link retrieved successfully');
            }

            return $this->sendError('Link could not be created');
        }

        if ($paymentMethod == 3) {

            if ($school->payment_provider === 'payyo') {
                $payLink = PayyoHelpers::createPayLink(
                    $school,
                    $booking,
                    $request->all(),
                    $booking->clientMain
                );

                if (strlen($payLink) > 1) {
                    try {
                        $bookingData = $booking->fresh();
                        $logData = [
                            'booking_id' => $booking->id,
                            'action' => 'send_pay_link',
                            'user_id' => $booking->user_id,
                            'description' => 'Booking pay link sent',
                        ];

                        BookingLog::create($logData);
                        dispatch(function () use ($school, $booking, $bookingData, $payLink) {
                            try {
                                \Mail::to($booking->clientMain->email)
                                    ->send(new BookingPayMailer(
                                        $school,
                                        $bookingData,
                                        $booking->clientMain,
                                        $payLink
                                    ));
                            } catch (\Exception $e) {
                                Log::channel('payyo')->error('PayyoHelpers sendMailing payBooking Booking ID=' . $booking->id);
                                Log::channel('payyo')->error($e->getMessage());
                            }
                        });
                    } catch (\Exception $e) {
                        Log::channel('payyo')->error('PayyoHelpers sendPayEmail payBooking Booking ID=' . $booking->id);
                        Log::channel('payyo')->error($e->getMessage());
                        return $this->sendError('Link could not be created');
                    }

                    return $this->sendResponse([], 'Mail sent correctly');
                }

                return $this->sendError('Link could not be created');
            }

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

        // Obtiene los BookingUsers de la solicitud
        $bookingUsers = BookingUser::whereIn('id', $request->bookingUsers)->get();

        // Verifica si existen BookingUsers
        if ($bookingUsers->isEmpty()) {
            return $this->sendError('Booking users not found', [], 404);
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

        // Actualiza el status de los bookingUsers a cancelado (status = 2)
        foreach ($bookingUsers as $bookingUser) {
            $bookingUser->status = 2;
            $bookingUser->save();
        }

        // Restar el precio del primer bookingUser al price_total de la reserva
/*        $firstBookingUserPrice = $bookingUsers[0]->price;
        Log::debug('Payments: ', $booking->payments->toArray());
        $lastPayment = $booking->payments->last(); // Obtener el último pago
        if ($lastPayment && $lastPayment->status != 'no_refund') {
            $booking->price_total -= $firstBookingUserPrice;
        }*/

        // Verificar si quedan bookingUsers activos (status distinto de 2)
        $activeBookingUsers = $booking->bookingUsers()->where('status', '!=', 2)->exists();

        // Si no hay más bookingUsers activos, cambia el status de la reserva a 2 (completamente cancelada)
        if (!$activeBookingUsers) {
            $booking->status = 2; // Completamente cancelado
        } else {
            $booking->status = 3; // Parcialmente cancelado
        }

        if(!$booking->paid) {
            $booking->reloadPrice();
        }

        $booking->save();

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
