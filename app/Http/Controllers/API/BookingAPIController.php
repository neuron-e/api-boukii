<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateBookingAPIRequest;
use App\Http\Requests\API\UpdateBookingAPIRequest;
use App\Http\Resources\API\BookingResource;
use App\Mail\BookingCreateMailer;
use App\Mail\BookingInfoUpdateMailer;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\Client;
use App\Models\DiscountCode;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Services\DiscountCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;

/**
 * Class BookingController
 */

class BookingAPIController extends AppBaseController
{
    private BookingRepository $bookingRepository;
    private DiscountCodeService $discountCodeService;

    public function __construct(BookingRepository $bookingRepo, DiscountCodeService $discountCodeService)
    {
        $this->bookingRepository = $bookingRepo;
        $this->discountCodeService = $discountCodeService;
    }

    /**
     * @OA\Get(
     *      path="/bookings",
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
        $bookings = $this->bookingRepository->all(
            searchArray: $request->except([
                'skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order',
                'orderColumn', 'page', 'with', 'isMultiple', 'course_types',
                'course_type', 'finished', 'all'
            ]),
            search: $request->get('search'),
            skip: $request->get('skip'),
            limit: $request->get('limit'),
            pagination: $request->get('perPage', 10),
            with: $request->get('with', []),
            order: $request->get('order', 'desc'),
            orderColumn: $request->get('orderColumn', 'id'),
            additionalConditions: function ($query) use ($request) {
                $this->applyStatusFilter($query, $request);
                $this->applyIsMultipleFilter($query, $request);
                $this->applyCourseTypeFilter($query, $request);
                $this->applyCourseIdFilter($query, $request);
                $this->applyFinishedFilter($query, $request);
            }
        );

        return $this->sendResponse($bookings, 'Bookings retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/bookings",
     *      summary="createBooking",
     *      tags={"Booking"},
     *      description="Create Booking",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Booking")
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
     *                  ref="#/components/schemas/Booking"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateBookingAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $grossPrice = (float) Arr::get($input, 'price_total', 0);
        $discountCodeAmount = 0.0;
        $discountCodeModel = null;

        $client = null;
        if ($clientId = Arr::get($input, 'client_main_id')) {
            $client = Client::with('user')->find($clientId);
        }

        $rawDiscountCode = Arr::get($input, 'discount_code');
        $discountCodeId = Arr::get($input, 'discount_code_id');

        if ($rawDiscountCode || $discountCodeId) {
            $discountCodeModel = $rawDiscountCode
                ? DiscountCode::where('code', strtoupper($rawDiscountCode))->first()
                : DiscountCode::find($discountCodeId);

            if (!$discountCodeModel) {
                return $this->sendError('Discount code not found', [
                    'discount_code' => ['El código indicado no existe'],
                ], 422);
            }

            $validationPayload = $this->buildAdminDiscountPayload($input, $client, $grossPrice);
            $validation = $this->discountCodeService->getValidationDetails($discountCodeModel->code, $validationPayload);

            if (!$validation['valid']) {
                return $this->sendError('Invalid discount code', [$validation['message']], 422);
            }

            if (!empty(Arr::get($input, 'vouchers', [])) && !$discountCodeModel->stackable) {
                return $this->sendError('El código promocional no se puede combinar con bonos', [
                    'discount_code' => ['Este código no permite combinarse con bonos en la misma reserva'],
                ], 422);
            }

            $discountCodeAmount = (float) $validation['discount_amount'];
            $input['discount_code_id'] = $discountCodeModel->id;
            $input['discount_code_value'] = $discountCodeAmount;
            $input['price_total'] = max(0, $grossPrice - $discountCodeAmount);

            if (array_key_exists('pending_amount', $input)) {
                $input['pending_amount'] = max(0, (float) $input['pending_amount'] - $discountCodeAmount);
            }
        } else {
            $input['discount_code_id'] = null;
            $input['discount_code_value'] = 0;
        }

        unset($input['discount_code']);

        $booking = $this->bookingRepository->create($input);

        if ($discountCodeModel && $client && $client->user) {
            $this->discountCodeService->recordCodeUsage(
                $discountCodeModel->id,
                $client->user->id,
                $booking->id,
                $discountCodeAmount
            );
        }

        $logData = [
            'booking_id' => $booking->id,
            'action' => 'created by api',
            'user_id' => $booking->user_id,
            'description' => 'Booking created',
        ];

        BookingLog::create($logData);

        return $this->sendResponse(new BookingResource($booking), 'Booking saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/bookings/{id}",
     *      summary="getBookingItem",
     *      tags={"Booking"},
     *      description="Get Booking",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Booking",
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
     *                  ref="#/components/schemas/Booking"
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
        /** @var Booking $booking */
        $booking = $this->bookingRepository->find($id, with: $request->get('with', []));

        if (empty($booking)) {
            return $this->sendError('Booking not found');
        }

        return $this->sendResponse($booking, 'Booking retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/bookings/{id}",
     *      summary="updateBooking",
     *      tags={"Booking"},
     *      description="Update Booking",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Booking",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/Booking")
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
     *                  ref="#/components/schemas/Booking"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateBookingAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Booking $booking */
        $booking = $this->bookingRepository->find($id, with: $request->get('with', []));

        if (empty($booking)) {
            return $this->sendError('Booking not found');
        }

        $booking = $this->bookingRepository->update($input, $id);

        if($request->has('send_mail') && $request->input('send_mail')) {
            dispatch(function () use ($booking) {
                // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                try {
                    Mail::to($booking->clientMain->email)->send(new BookingInfoUpdateMailer($booking->school, $booking, $booking->clientMain));
                } catch (\Exception $ex) {
                    \Illuminate\Support\Facades\Log::debug('Admin/COurseController BookingInfoUpdateMailer: ',
                        $ex->getTrace());
                }
            })->afterResponse();
        }

        return $this->sendResponse($booking, 'Booking updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/bookings/{id}",
     *      summary="deleteBooking",
     *      tags={"Booking"},
     *      description="Delete Booking",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of Booking",
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
        /** @var Booking $booking */
        $booking = $this->bookingRepository->find($id);
        if (empty($booking)) {
            return $this->sendError('Booking not found');
        }

        $booking->delete();

        return $this->sendSuccess('Booking deleted successfully');
    }

    /**
     * Build booking data payload used to validate discount codes from admin/API requests.
     *
     * @param array $input
     * @param Client|null $client
     * @param float $grossPrice
     * @return array
     */
    private function buildAdminDiscountPayload(array $input, ?Client $client, float $grossPrice): array
    {
        $courseIds = [];
        $degreeIds = [];
        $sportIds = [];

        $possibleCourseValues = array_merge(
            Arr::wrap(Arr::get($input, 'course_ids', [])),
            Arr::wrap(Arr::get($input, 'course_id'))
        );

        foreach ($possibleCourseValues as $courseId) {
            if ($courseId !== null) {
                $courseIds[] = $courseId;
            }
        }

        $possibleDegreeValues = array_merge(
            Arr::wrap(Arr::get($input, 'degree_ids', [])),
            Arr::wrap(Arr::get($input, 'degree_id'))
        );

        foreach ($possibleDegreeValues as $degreeId) {
            if ($degreeId !== null) {
                $degreeIds[] = $degreeId;
            }
        }

        $possibleSportValues = array_merge(
            Arr::wrap(Arr::get($input, 'sport_ids', [])),
            Arr::wrap(Arr::get($input, 'sport_id'))
        );

        foreach ($possibleSportValues as $sportId) {
            if ($sportId !== null) {
                $sportIds[] = $sportId;
            }
        }

        $cartItems = Arr::wrap(Arr::get($input, 'cart', []));
        foreach ($cartItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $this->collectIdsFromCartNode($item, $courseIds, $degreeIds, $sportIds);

            $details = Arr::get($item, 'details', []);
            if (is_array($details)) {
                foreach ($details as $detail) {
                    if (is_array($detail)) {
                        $this->collectIdsFromCartNode($detail, $courseIds, $degreeIds, $sportIds);
                    }
                }
            }
        }

        $courseIds = $this->normalizeIdArray($courseIds);
        $degreeIds = $this->normalizeIdArray($degreeIds);
        $sportIds = $this->normalizeIdArray($sportIds);

        return [
            'school_id' => Arr::get($input, 'school_id'),
            'course_id' => count($courseIds) === 1 ? $courseIds[0] : null,
            'course_ids' => $courseIds,
            'sport_id' => count($sportIds) === 1 ? $sportIds[0] : null,
            'sport_ids' => $sportIds,
            'degree_id' => count($degreeIds) === 1 ? $degreeIds[0] : null,
            'degree_ids' => $degreeIds,
            'amount' => $grossPrice,
            'user_id' => $client?->user?->id,
            'client_id' => $client?->id,
        ];
    }

    private function collectIdsFromCartNode(array $node, array &$courseIds, array &$degreeIds, array &$sportIds): void
    {
        $courseId = Arr::get($node, 'course_id');
        if ($courseId !== null) {
            $courseIds[] = $courseId;
        }

        $degreeId = Arr::get($node, 'degree_id');
        if ($degreeId !== null) {
            $degreeIds[] = $degreeId;
        }

        $sportId = Arr::get($node, 'sport_id');
        if ($sportId !== null) {
            $sportIds[] = $sportId;
        }
    }

    private function normalizeIdArray(array $values): array
    {
        $filtered = array_filter($values, static function ($value) {
            return $value !== null && $value !== '' && is_numeric($value);
        });

        return array_values(array_unique(array_map('intval', $filtered)));
    }

    private function applyStatusFilter($query, Request $request): void
    {
        if (!$request->has('all')) {
            $query->where('status', '!=', 3);
        }
    }

    private function applyIsMultipleFilter($query, Request $request): void
    {
        if ($request->has('isMultiple')) {
            $isMultiple = filter_var($request->get('isMultiple'), FILTER_VALIDATE_BOOLEAN);

            $query->whereHas('bookingUsers', function ($subQuery) use ($isMultiple) {
                $subQuery->select('booking_id')
                    ->groupBy('booking_id')
                    ->havingRaw($isMultiple ? 'COUNT(DISTINCT client_id) > 1' : 'COUNT(DISTINCT client_id) = 1');
            });
        }
    }

    private function applyCourseTypeFilter($query, Request $request): void
    {
        if ($request->has('course_types')) {
            $courseTypes = $request->get('course_types');
            $query->whereHas('bookingUsers.course', function ($subQuery) use ($courseTypes) {
                $subQuery->whereIn('course_type', $courseTypes);
            });
        }

        if ($request->has('course_type')) {
            $courseType = $request->get('course_type');
            $query->whereHas('bookingUsers.course', function ($subQuery) use ($courseType) {
                $subQuery->where('course_type', $courseType);
            });
        }
    }

    private function applyCourseIdFilter($query, Request $request): void
    {
        if ($request->has('courseId') || $request->has('course_id')) {
            $courseId = $request->get('courseId') ?? $request->get('course_id');
            $query->whereHas('bookingUsers', function ($subQuery) use ($courseId) {
                $subQuery->where('course_id', $courseId);
            });
        }
    }

    private function applyFinishedFilter($query, Request $request): void
    {
        if ($request->has('finished') && !$request->has('all')) {
            $today = now()->format('Y-m-d H:i:s');
            $isFinished = $request->get('finished') == 1;

            $query->whereDoesntHave('bookingUsers', function ($subQuery) use ($today, $isFinished) {
                $subQuery->where(function ($dateQuery) use ($today, $isFinished) {
                    if ($isFinished) {
                        // Filtrar reservas finalizadas
                        $dateQuery->where('date', '<=', $today)
                            ->orWhere(function ($hourQuery) use ($today) {
                                $hourQuery->where('date', $today)
                                    ->where('hour_end', '<=', $today);
                            });
                    } else {
                        // Filtrar reservas no finalizadas
                        $dateQuery->where('date', '>', $today)
                            ->orWhere(function ($hourQuery) use ($today) {
                                $hourQuery->where('date', $today)
                                    ->where('hour_end', '>', $today);
                            });
                    }
                });
            });
        }
    }
}
