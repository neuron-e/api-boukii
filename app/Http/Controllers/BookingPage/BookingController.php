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
use App\Http\Services\BookingPriceSnapshotService;
use App\Models\Client;
use App\Models\Course;
use App\Models\CourseExtra;
use App\Models\CourseDate;
use App\Models\CourseGroup;
use App\Models\CourseSubgroup;
use App\Models\Monitor;
use App\Models\Degree;
use App\Models\DiscountCode;
use App\Models\MonitorNwd;
use App\Models\MonitorSportsDegree;
use App\Models\Voucher;
use App\Models\VouchersLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CourseAvailabilityService;
use App\Services\CriticalErrorNotifier;
use App\Services\DiscountCodeService;
use App\Services\MonitorNotificationService;
use Illuminate\Support\Arr;
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
        // Iniciar una transaccion
        DB::beginTransaction();

        try {
            $data = $request->all();

            $client = Client::with('user')->find(Arr::get($data, 'client_main_id'));
            if (!$client || !$client->user) {
                DB::rollBack();
                $this->logBookingError('booking.errors.client_main_invalid', [
                    'client_main_id' => Arr::get($data, 'client_main_id'),
                    'school_id' => Arr::get($data, 'school_id'),
                ]);
                return response()->json([
                    'message' => 'booking.errors.client_main_invalid',
                    'errors' => ['client_main_id' => ['booking.errors.client_main_required']]
                ], 422);
            }

            $grossPriceTotal = (float) Arr::get($data, 'price_total_before_discount_code', Arr::get($data, 'amount', Arr::get($data, 'price_total', 0)));
            $discountCodeId = Arr::get($data, 'discount_code_id');
            $discountCodeAmount = 0.0;

            $cartItems = Arr::get($data, 'cart', []);
            $courseIds = [];
            $degreeIds = [];
            $courseTypeCache = [];
            $courseIdByCourseDateCache = [];

            foreach ($cartItems as $cartItem) {
                $details = Arr::get($cartItem, 'details', []);
                foreach ($details as $detail) {
                    $courseType = Arr::get($detail, "course.course_type", Arr::get($detail, "course_type"));
                    if ($courseType === null) {
                        $courseId = Arr::get($detail, 'course_id', Arr::get($detail, 'course.id'));
                        $courseDateId = Arr::get($detail, 'course_date_id');

                        if (!$courseId && $courseDateId) {
                            if (!array_key_exists($courseDateId, $courseIdByCourseDateCache)) {
                                $courseIdByCourseDateCache[$courseDateId] = CourseDate::whereKey($courseDateId)->value('course_id');
                            }
                            $courseId = $courseIdByCourseDateCache[$courseDateId] ?? null;
                        }

                        if ($courseId) {
                            if (!array_key_exists($courseId, $courseTypeCache)) {
                                $courseTypeCache[$courseId] = Course::whereKey($courseId)->value('course_type');
                            }
                            $courseType = $courseTypeCache[$courseId];
                        }
                    }
                    if ((int) $courseType === 2) {
                        $timingError = $this->validatePrivateTiming([
                            "course_date_id" => $detail["course_date_id"] ?? null,
                            "date" => $detail["date"] ?? null,
                            "hour_start" => $detail["hour_start"] ?? null,
                            "hour_end" => $detail["hour_end"] ?? null,
                        ]);
                        if ($timingError) {
                            DB::rollBack();
                            $this->logBookingError($timingError, [
                                'course_date_id' => $detail["course_date_id"] ?? null,
                                'date' => $detail["date"] ?? null,
                                'hour_start' => $detail["hour_start"] ?? null,
                                'hour_end' => $detail["hour_end"] ?? null,
                                'school_id' => Arr::get($data, 'school_id'),
                            ]);
                            return response()->json([
                                "message" => $timingError,
                                "errors" => ["time" => [$timingError]]
                            ], 422);
                        }
                        $overbookingError = $this->validatePrivateOverbooking(
                            $detail["date"] ?? null,
                            $detail["hour_start"] ?? null,
                            $detail["hour_end"] ?? null,
                            $detail["course"]["sport_id"] ?? $detail["course_sport_id"] ?? null
                        );
                        if ($overbookingError) {
                            DB::rollBack();
                            $this->logBookingError($overbookingError, [
                                'date' => $detail["date"] ?? null,
                                'hour_start' => $detail["hour_start"] ?? null,
                                'hour_end' => $detail["hour_end"] ?? null,
                                'sport_id' => $detail["course"]["sport_id"] ?? $detail["course_sport_id"] ?? null,
                                'school_id' => Arr::get($data, 'school_id'),
                            ]);
                            return response()->json([
                                "message" => $overbookingError,
                                "errors" => ["overbooking" => [$overbookingError]]
                            ], 422);
                        }
                    }
                    if (!empty($detail['course_id'])) {
                        $courseIds[] = (int) $detail['course_id'];
                    }

                    if (!empty($detail['degree_id'])) {
                        $degreeIds[] = (int) $detail['degree_id'];
                    } elseif (!empty($detail['course_subgroup_id'])) {
                        $subgroupDegree = CourseSubgroup::where('id', $detail['course_subgroup_id'])->value('degree_id');
                        if ($subgroupDegree) {
                            $degreeIds[] = (int) $subgroupDegree;
                        }
                    }
                }
            }

            $courseIds = array_values(array_unique(array_filter($courseIds)));
            $degreeIds = array_values(array_unique(array_filter($degreeIds)));
            $sportIds = [];

            if (!empty($courseIds)) {
                $sportIds = Course::whereIn('id', $courseIds)
                    ->pluck('sport_id')
                    ->filter()
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values()
                    ->all();
            }

            $discountCode = null;
            $vouchersPayload = Arr::get($data, 'vouchers', []);

            if ($discountCodeId) {
                $discountCode = DiscountCode::lockForUpdate()->find($discountCodeId);
                if (!$discountCode) {
                    DB::rollBack();
                    $this->logBookingError('booking.errors.discount_code_not_found', [
                        'discount_code_id' => $discountCodeId,
                        'school_id' => Arr::get($data, 'school_id'),
                    ]);
                    return response()->json([
                        'message' => 'booking.errors.discount_code_not_found',
                        'errors' => ['discount_code' => ['booking.errors.discount_code_not_found']]
                    ], 422);
                }

                if (!empty($vouchersPayload) && !$discountCode->stackable) {
                    DB::rollBack();
                    $this->logBookingError('booking.errors.discount_code_not_stackable', [
                        'discount_code_id' => $discountCode->id,
                        'voucher_count' => count($vouchersPayload),
                        'school_id' => Arr::get($data, 'school_id'),
                    ]);
                    return response()->json([
                        'message' => 'booking.errors.discount_code_not_stackable',
                        'errors' => ['discount_code' => ['booking.errors.discount_code_not_stackable']]
                    ], 422);
                }

                $validationPayload = [
                    'school_id' => Arr::get($data, 'school_id'),
                    'course_id' => count($courseIds) === 1 ? $courseIds[0] : null,
                    'course_ids' => $courseIds,
                    'sport_id' => count($sportIds) === 1 ? $sportIds[0] : null,
                    'sport_ids' => $sportIds,
                    'degree_id' => count($degreeIds) === 1 ? $degreeIds[0] : null,
                    'degree_ids' => $degreeIds,
                    'amount' => $grossPriceTotal,
                    'user_id' => $client->user->id,
                    'client_id' => $client->id,
                    'discount_code_amount' => Arr::get($data, 'discount_code_amount', 0),
                ];

                $validation = app(DiscountCodeService::class)->validateCode($discountCode->code, $validationPayload);

                if (!$validation['valid']) {
                    DB::rollBack();
                    $this->logBookingError('booking.errors.discount_code_validation_failed', [
                        'discount_code_id' => $discountCodeId,
                        'school_id' => Arr::get($data, 'school_id'),
                    ]);
                    return response()->json([
                        'message' => 'booking.errors.discount_code_validation_failed',
                        'errors' => ['booking.errors.discount_code_validation_failed']
                    ], 422);
                }

                $discountCodeAmount = (float) $validation['discount_amount'];
                $discountCodeId = $discountCode->id;
            }

            $requestTotal = Arr::get($data, 'price_total');
            $frontEndTotal = $requestTotal !== null ? max(0, (float) $requestTotal) : null;
            $calculatedTotal = max(0, $grossPriceTotal - $discountCodeAmount);
            $basketPriceTotal = $this->getBasketPriceTotal(Arr::get($data, 'basket'));
            $basketZeroOverride = $this->basketIndicatesZeroPrice(Arr::get($data, 'basket'));

            $netPriceTotal = $frontEndTotal ?? $calculatedTotal;
            if ($basketPriceTotal !== null) {
                $netPriceTotal = min($netPriceTotal, max(0, $basketPriceTotal));
            } elseif ($basketZeroOverride) {
                $netPriceTotal = 0;
            }

            $zeroTotalBooking = $netPriceTotal <= 0 || $basketZeroOverride;

            $paymentMethodId = Arr::get($data, 'payment_method_id');
            if (!$paymentMethodId) {
                $paymentMethodId = Arr::get($data, 'basket.payment_method_id');
            }
            if (!$paymentMethodId && is_string(Arr::get($data, 'basket'))) {
                $decodedBasket = json_decode(Arr::get($data, 'basket'), true);
                if (is_array($decodedBasket)) {
                    $paymentMethodId = $decodedBasket['payment_method_id'] ?? null;
                }
            }

            $showUnpaidBooking = in_array((int) $paymentMethodId, [Booking::ID_BOUKIIPAY, Booking::ID_ONLINE], true);
            $shouldSoftDelete = !$zeroTotalBooking && !$showUnpaidBooking;

            $meetingPointData = $this->resolveMeetingPointFromCourses($courseIds);

            // Crear la reserva (Booking)
            $booking = Booking::create([
                'school_id' => Arr::get($data, 'school_id'),
                'client_main_id' => Arr::get($data, 'client_main_id'),
                'price_total' => $netPriceTotal,
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
                'payment_method_id' => $paymentMethodId,
                'discount_code_id' => $discountCodeId,
                'discount_code_value' => $discountCodeAmount,
                'meeting_point' => Arr::get($data, 'meeting_point', $meetingPointData['meeting_point']),
                'meeting_point_address' => Arr::get($data, 'meeting_point_address', $meetingPointData['meeting_point_address']),
                'meeting_point_instructions' => Arr::get($data, 'meeting_point_instructions', $meetingPointData['meeting_point_instructions']),
            ]);

            // Crear BookingUser para cada detalle
            $groupId = 1; // Inicia el contador de grupo
            $bookingUsers = []; // Para almacenar los objetos BookingUser
            $availabilityService = app(CourseAvailabilityService::class);
            $courseGroupCache = [];
            $courseSubgroupCache = [];
            $courseDateCache = [];

            foreach ($cartItems as $cartItem) {
                foreach ($cartItem['details'] as $detail) {
                    $monitorId = $detail['monitor_id'] ?? null;
                    $degreeId = $detail['degree_id'] ?? null;
                    $courseGroupId = Arr::get($detail, 'course_group_id');
                    $courseSubgroupId = Arr::get($detail, 'course_subgroup_id');
                    $courseType = (int) ($detail['course']['course_type'] ?? $detail['course_type'] ?? 0);

                    $normalizedDate = null;
                    $rawDate = $detail['date'] ?? null;
                    if ($rawDate) {
                        $normalizedDate = Carbon::parse($rawDate)->format('Y-m-d');
                    } elseif (!empty($detail['course_date_id'])) {
                        if (!isset($courseDateCache[$detail['course_date_id']])) {
                            $courseDateCache[$detail['course_date_id']] = CourseDate::find($detail['course_date_id']);
                        }
                        $courseDateRecord = $courseDateCache[$detail['course_date_id']];
                        if ($courseDateRecord && $courseDateRecord->date) {
                            $normalizedDate = $courseDateRecord->date instanceof Carbon
                                ? $courseDateRecord->date->format('Y-m-d')
                                : Carbon::parse($courseDateRecord->date)->format('Y-m-d');
                        }
                    }

                    $courseSubgroup = null;
                    if ($courseSubgroupId) {
                        if (!isset($courseSubgroupCache[$courseSubgroupId])) {
                            $courseSubgroupCache[$courseSubgroupId] = CourseSubgroup::find($courseSubgroupId);
                        }
                        $courseSubgroup = $courseSubgroupCache[$courseSubgroupId];
                        if (!$courseSubgroup) {
                            DB::rollBack();
                            $this->logBookingError('booking.errors.subgroup_not_found', [
                                'course_subgroup_id' => $courseSubgroupId,
                                'course_group_id' => $courseGroupId,
                                'school_id' => Arr::get($data, 'school_id'),
                            ]);
                            return response()->json([
                                'message' => 'booking.errors.subgroup_not_found',
                                'errors' => ['course_subgroup_id' => ["booking.errors.subgroup_not_found"]],
                            ], 422);
                        }

                        $monitorId = $courseSubgroup->monitor_id ?? $monitorId;
                        $degreeId = $courseSubgroup->degree_id ?? $degreeId;
                        $courseGroupId = $courseSubgroup->course_group_id;
                    }

                    $courseGroup = null;
                    if ($courseGroupId) {
                        if (!isset($courseGroupCache[$courseGroupId])) {
                            $courseGroupCache[$courseGroupId] = CourseGroup::find($courseGroupId);
                        }
                        $courseGroup = $courseGroupCache[$courseGroupId];
                        if (!$courseGroup) {
                            DB::rollBack();
                            $this->logBookingError('booking.errors.group_not_found', [
                                'course_group_id' => $courseGroupId,
                                'school_id' => Arr::get($data, 'school_id'),
                            ]);
                            return response()->json([
                                'message' => 'booking.errors.group_not_found',
                                'errors' => ['course_group_id' => ["booking.errors.group_not_found"]],
                            ], 422);
                        }

                        if (!empty($detail['course_date_id']) && $courseGroup->course_date_id !== $detail['course_date_id']) {
                            DB::rollBack();
                            $this->logBookingError('booking.errors.group_date_mismatch', [
                                'course_group_id' => $courseGroupId,
                                'course_date_id' => $detail['course_date_id'],
                                'school_id' => Arr::get($data, 'school_id'),
                            ]);
                            return response()->json([
                                'message' => 'booking.errors.group_date_mismatch',
                                'errors' => ['course_group_id' => ["booking.errors.group_date_mismatch"]],
                            ], 422);
                        }

                        if (!$degreeId) {
                            $degreeId = $courseGroup->degree_id;
                        }
                    } else {
                        $courseGroupId = null;
                    }

                    if ($courseType === 1 && $courseSubgroup && $normalizedDate) {
                        $availableSlots = $availabilityService->getAvailableSlots($courseSubgroup, $normalizedDate);

                        if ($availableSlots <= 0) {
                            Log::channel('bookings')->warning('BOOKING_PAGE_COLLECTIVE_FULL', [
                                'course_id' => $detail['course_id'] ?? null,
                                'course_date_id' => $detail['course_date_id'] ?? null,
                                'course_subgroup_id' => $courseSubgroupId,
                                'degree_id' => $degreeId,
                                'date' => $normalizedDate,
                            ]);

                            DB::rollBack();
                            $this->logBookingError('booking.errors.subgroup_full', [
                                'course_id' => $detail['course_id'] ?? null,
                                'course_date_id' => $detail['course_date_id'] ?? null,
                                'course_subgroup_id' => $courseSubgroupId,
                                'degree_id' => $degreeId,
                                'date' => $normalizedDate,
                                'school_id' => Arr::get($data, 'school_id'),
                            ]);
                            return response()->json([
                                'message' => 'booking.errors.subgroup_full',
                                'errors' => [
                                    'course_subgroup_id' => [
                                        "booking.errors.subgroup_full"
                                    ],
                                ],
                            ], 422);
                        }
                    }

                    $bookingUser = new BookingUser([
                        'school_id' => $detail['school_id'],
                        'booking_id' => $booking->id,
                        'client_id' => $detail['client_id'],
                        'price' => $detail['price'],
                        'currency' => $detail['currency'],
                        'course_id' => $detail['course_id'],
                        'course_date_id' => $detail['course_date_id'],
                        'course_group_id' => $courseGroupId,
                        'course_subgroup_id' => $courseSubgroupId,
                        'monitor_id' => $monitorId,
                        'degree_id' => $degreeId,
                        'date' => $detail['date'],
                        'hour_start' => $detail['hour_start'],
                        'hour_end' => $detail['hour_end'],
                        'group_id' => $groupId,
                        'accepted' => !empty($courseSubgroupId),
                        'deleted_at' => $shouldSoftDelete ? now() : null,
                    ]);

                    $bookingUser->save();
                    $bookingUsers[] = $bookingUser;

                    if (isset($detail['extra']) && is_array($detail['extra'])) {
                        foreach ($detail['extra'] as $extra) {
                            // Only create BookingUserExtra if the ID is numeric (CourseExtra from database)
                            // School settings extras have string IDs like 'FOR-65307287' and are not CourseExtras
                            if (isset($extra['id']) && is_numeric($extra['id'])) {
                                BookingUserExtra::create([
                                    'booking_user_id' => $bookingUser->id,
                                    'course_extra_id' => (int) $extra['id'],
                                    'quantity' => 1
                                ]);
                            }
                        }
                    }
                }
                $groupId++; // Incrementar el `group_id` para el siguiente `cartItem`
            }
            $booking->deleted_at = $shouldSoftDelete ? now() : null;
            if ($zeroTotalBooking) {
                $booking->paid = true;
                $booking->paid_total = 0;
                // Reactivar todos los booking_users si estaban marcados
                foreach ($bookingUsers as $bookingUser) {
                    $bookingUser->deleted_at = null;
                    $bookingUser->save();
                }
            }

            if (!empty($vouchersPayload)) {
                $voucherApplications = [];
                $voucherErrors = [];
                $totalVoucherApplied = 0.0;

                foreach ($vouchersPayload as $voucherData) {
                    $voucherId = Arr::get($voucherData, 'id');
                    $voucherCode = Arr::get($voucherData, 'code', (string) $voucherId);
                    $amount = (float) Arr::get($voucherData, 'reducePrice', 0);

                    if (!$voucherId) {
                        $voucherErrors[] = 'booking.errors.voucher_missing_id';
                        continue;
                    }

                    /** @var Voucher|null $voucher */
                    $voucher = Voucher::where('id', $voucherId)->lockForUpdate()->first();
                    if (!$voucher) {
                        $voucherErrors[] = 'booking.errors.voucher_not_found';
                        continue;
                    }

                    $currentErrors = [];

                    if ((int) $voucher->school_id !== (int) $booking->school_id) {
                        $currentErrors[] = 'booking.errors.voucher_wrong_school';
                    }

                    if ($amount <= 0) {
                        $currentErrors[] = 'booking.errors.voucher_amount_invalid';
                    }

                    if ($amount > $voucher->remaining_balance) {
                        $currentErrors[] = 'booking.errors.voucher_insufficient_balance';
                    }

                    if (!$voucher->payed) {
                        $currentErrors[] = 'booking.errors.voucher_not_active';
                    }

                    if (!$voucher->canBeUsed()) {
                        if ($voucher->isExpired()) {
                            $currentErrors[] = 'booking.errors.voucher_expired';
                        }
                        if (!$voucher->hasBalance()) {
                            $currentErrors[] = 'booking.errors.voucher_no_balance';
                        }
                        if ($voucher->hasReachedMaxUses()) {
                            $currentErrors[] = 'booking.errors.voucher_max_uses';
                        }
                        if ($voucher->trashed()) {
                            $currentErrors[] = 'booking.errors.voucher_inactive';
                        }
                    } elseif (!$voucher->canBeUsedByClient($booking->client_main_id)) {
                        $currentErrors[] = 'booking.errors.voucher_not_allowed';
                    }

                    if (!empty($currentErrors)) {
                        $voucherErrors = array_merge($voucherErrors, $currentErrors);
                        continue;
                    }

                    $voucherApplications[] = [
                        'voucher' => $voucher,
                        'amount' => $amount,
                    ];
                    $totalVoucherApplied += $amount;
                }

                if (!empty($voucherErrors)) {
                    DB::rollBack();
                    $this->logBookingError('booking.errors.voucher_validation_failed', [
                        'voucher_error_count' => count($voucherErrors),
                        'school_id' => Arr::get($data, 'school_id'),
                    ]);
                    return response()->json([
                        'message' => 'booking.errors.voucher_validation_failed',
                        'errors' => array_values(array_unique($voucherErrors)),
                    ], 422);
                }

                foreach ($voucherApplications as $application) {
                    /** @var Voucher $voucher */
                    $voucher = $application['voucher'];
                    $amount = $application['amount'];

                    if (!$voucher->use($amount)) {
                        DB::rollBack();
                        $this->logBookingError('booking.errors.voucher_apply_failed', [
                            'voucher_id' => $voucher->id,
                            'school_id' => Arr::get($data, 'school_id'),
                        ]);
                        return response()->json([
                            'message' => 'booking.errors.voucher_apply_failed',
                            'errors' => ['booking.errors.voucher_apply_failed'],
                        ], 500);
                    }

                    VouchersLog::create([
                        'voucher_id' => $voucher->id,
                        'booking_id' => $booking->id,
                        'amount' => $amount,
                        'status' => 'used',
                    ]);
                }

                if ($totalVoucherApplied >= $netPriceTotal) {
                    $booking->deleted_at = null;
                    $booking->paid = true;

                    foreach ($bookingUsers as $bookingUser) {
                        $bookingUser->deleted_at = null;
                        $bookingUser->save();
                    }
                }
            }

            if ($booking->price_total <= 0) {
                $booking->deleted_at = null;
                $booking->paid = true;
                $booking->paid_total = 0;

                foreach ($bookingUsers as $bookingUser) {
                    if ($bookingUser->deleted_at !== null) {
                        $bookingUser->deleted_at = null;
                        $bookingUser->save();
                    }
                }
            }

            $booking->save();

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

            $this->notifyMonitorAssignments($bookingUsers);

            // Confirmar la transacciÃ³n
            $booking->loadMissing([
                'bookingUsers.course',
                'bookingUsers.bookingUserExtras.courseExtra',
                'vouchersLogs.voucher',
                'payments'
            ]);

            app(BookingPriceSnapshotService::class)->createSnapshotFromBasket(
                $booking,
                null,
                'basket_import',
                'Snapshot created on booking creation'
            );

            DB::commit();


            return response()->json(['message' => 'booking.success.created', 'booking_id' => $booking->id], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::channel('bookings')->debug('BookingPage/BookingController store: ',
                $e->getTrace());
            $this->logBookingError('booking.errors.create_failed', [
                'school_id' => Arr::get($data ?? [], 'school_id'),
                'client_main_id' => Arr::get($data ?? [], 'client_main_id'),
            ], $e);
            // Revertir la transacciÃ³n si ocurre un error
            DB::rollBack();

            app(\App\Services\CriticalErrorNotifier::class)->notify(
                'Booking page creation failed',
                [
                    'school_id' => \Illuminate\Support\Arr::get($data ?? [], 'school_id'),
                    'client_main_id' => \Illuminate\Support\Arr::get($data ?? [], 'client_main_id'),
                ],
                $e
            );

            return response()->json(['message' => 'booking.errors.create_failed', 'error' => $e->getMessage()], 500);
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
        $bookingUserIds = is_array($request->input('bookingUserIds')) ? $request->input('bookingUserIds') : [];

        // Obtiene informaciÃ³n comÃºn para todos los bookingUsers
        foreach ($request->bookingUsers as $bookingUser) {
            if ($bookingUser['course']['course_type'] == 2) {
                $timingError = $this->validatePrivateTiming($bookingUser);
                if ($timingError) {
                    return $this->sendError($timingError, [], 422);
                }
                $overbookingError = $this->validatePrivateOverbooking(
                    $date,
                    $startTime,
                    $endTime,
                    $bookingUser['course']['sport_id'] ?? null
                );
                if ($overbookingError) {
                    return $this->sendError($overbookingError, [], 422);
                }
                $clientIds[] = $bookingUser['client']['id'];

                // Verificar si degree_id existe antes de acceder
                $highestDegreeId = isset($bookingUser['degree_id']) ? $bookingUser['degree_id'] : 0;

                // Obtener el degree_id mÃ¡s alto solo una vez
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



            // Check for overlapping bookings and get details (exclude current booking users if provided)
            $overlaps = BookingUser::getOverlappingBookings($bookingUser, $bookingUserIds);
            if (!empty($overlaps)) {
                return $this->sendError(
                    'Client has overlapping booking(s) on that date',
                    ['overlaps' => $overlaps],
                    409  // HTTP 409 Conflict
                );
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

            // Solo aÃ±adir 'minimumDegreeId' si existe 'degreeOrder'
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

                // Solo aplicar la condiciÃ³n de degree_order si minimumDegreeId no es null
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

                    // Filtrar monitores por idioma si clientLanguages estÃ¡ presente
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
                    // AquÃ­ incluimos la lÃ³gica para verificar si es un dÃ­a entero
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
        return false; // NingÃºn monitor estÃ¡ disponible en el rango
    }

    private function getSchoolSettings(): array
    {
        $settings = $this->school->settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    private function getPrivateLeadMinutes(): int
    {
        $settings = $this->getSchoolSettings();
        $value = $settings['booking']['private_min_lead_minutes'] ?? null;

        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }

        return 30;
    }

    private function getPrivateOverbookingLimit(): int
    {
        $settings = $this->getSchoolSettings();
        $value = $settings['booking']['private_overbooking_limit'] ?? null;

        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }

        return 0;
    }

    private function validatePrivateOverbooking(?string $date, ?string $startTime, ?string $endTime, $sportId = null): ?string
    {
        if (!$date || !$startTime || !$endTime) {
            return null;
        }

        $availableMonitors = $this->getMonitorsAvailable(new Request([
            'date' => $date,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'sportId' => $sportId,
            'clientIds' => [],
            'minimumDegreeId' => null,
        ]));

        $availableCount = is_array($availableMonitors) ? count($availableMonitors) : 0;
        $overbookingLimit = $this->getPrivateOverbookingLimit();
        $concurrentBookings = $this->getConcurrentPrivateBookings($date, $startTime, $endTime);

        if ($availableCount + $overbookingLimit <= $concurrentBookings) {
            return 'booking.errors.private_overbooking';
        }

        return null;
    }

    private function getConcurrentPrivateBookings(string $date, string $startTime, string $endTime): int
    {
        return BookingUser::whereDate('date', $date)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereTime('hour_start', '<', Carbon::createFromFormat('H:i', $endTime))
                    ->whereTime('hour_end', '>', Carbon::createFromFormat('H:i', $startTime));
            })
            ->where('status', 1)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2);
            })
            ->whereHas('course', function ($query) {
                $query->where('course_type', 2);
            })
            ->count();
    }

    private function validatePrivateTiming(array $bookingUser): ?string
    {
        $courseDateId = $bookingUser['course_date_id'] ?? null;
        $date = $bookingUser['date'] ?? null;
        $startTime = $bookingUser['hour_start'] ?? null;
        $endTime = $bookingUser['hour_end'] ?? null;

        if (!$courseDateId || !$date || !$startTime || !$endTime) {
            return 'booking.errors.private_timing_invalid';
        }

        /** @var CourseDate|null $courseDate */
        $courseDate = CourseDate::find($courseDateId);
        if (!$courseDate) {
            return 'booking.errors.private_date_invalid';
        }

        $start = Carbon::parse(sprintf('%s %s', $date, $startTime));
        $end = Carbon::parse(sprintf('%s %s', $date, $endTime));

        if ($end->lessThanOrEqualTo($start)) {
            return 'booking.errors.private_end_before_start';
        }

        $courseStart = Carbon::parse(sprintf('%s %s', $courseDate->date->format('Y-m-d'), $courseDate->hour_start));
        $courseEnd = Carbon::parse(sprintf('%s %s', $courseDate->date->format('Y-m-d'), $courseDate->hour_end));

        if ($start->lt($courseStart) || $end->gt($courseEnd)) {
            return 'booking.errors.private_outside_schedule';
        }

        $minStart = Carbon::now()->addMinutes($this->getPrivateLeadMinutes());
        if ($start->lt($minStart)) {
            return 'booking.errors.private_lead_minutes';
        }

        return null;
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
            $this->logBookingError('booking.errors.payment_booking_not_found', [
                'booking_id' => $id,
            ]);
            return $this->sendError('booking.errors.payment_booking_not_found');
        }

        $booking->payment_method_id = $paymentMethod;
        $booking->save();

        $payrexxLink = PayrexxHelpers::createGatewayLink(
            $school,
            $booking,
            $request,
            $booking->clientMain,
            $request->redirectUrl,
            ['restrict_invoice' => true]
        );

        if ($payrexxLink) {
            return $this->sendResponse($payrexxLink, 'Link retrieved successfully');
        }

        $this->logBookingError('booking.errors.payment_link_failed', [
            'booking_id' => $booking->id,
            'school_id' => $booking->school_id ?? null,
        ]);
        return $this->sendError('booking.errors.payment_link_failed');


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
     *          description="booking.errors.payment_booking_not_found",
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
     *                  example={"message": "booking.errors.payment_booking_not_found"}
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
            return $this->sendError('booking.errors.payment_booking_not_found', 404);
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

        $settings = $this->getSchoolSettings();
        $monitorNotificationService = app(MonitorNotificationService::class);
        foreach ($bookingUsers as $bookingUser) {
            if (!$bookingUser->monitor_id) {
                continue;
            }
            $payload = [
                'course_id' => $bookingUser->course_id,
                'course_date_id' => $bookingUser->course_date_id,
                'date' => $bookingUser->date,
                'hour_start' => $bookingUser->hour_start,
                'hour_end' => $bookingUser->hour_end,
                'client_id' => $bookingUser->client_id,
                'booking_id' => $bookingUser->booking_id,
                'group_id' => $bookingUser->group_id,
                'course_subgroup_id' => $bookingUser->course_subgroup_id,
                'course_group_id' => $bookingUser->course_group_id,
                'school_id' => $this->school->id ?? null,
            ];
            $monitorNotificationService->notifyAssignment(
                $bookingUser->monitor_id,
                'booking_cancelled',
                $payload,
                $settings,
                auth()->id()
            );
        }

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
                \Illuminate\Support\Facades\Log::channel('emails')->debug('BookingController->cancelBookingFull BookingCancelMailer: ' . $ex->getMessage());
            }
        })->afterResponse();

        return $this->sendResponse([], 'Cancel completed successfully');

    }

    private function notifyMonitorAssignments(array $bookingUsers): void
    {
        $settings = $this->getSchoolSettings();
        $monitorNotificationService = app(MonitorNotificationService::class);

        foreach ($bookingUsers as $bookingUser) {
            if (!$bookingUser->monitor_id) {
                continue;
            }

            $payload = [
                'course_id' => $bookingUser->course_id,
                'course_date_id' => $bookingUser->course_date_id,
                'date' => $bookingUser->date,
                'hour_start' => $bookingUser->hour_start,
                'hour_end' => $bookingUser->hour_end,
                'client_id' => $bookingUser->client_id,
                'booking_id' => $bookingUser->booking_id,
                'group_id' => $bookingUser->group_id,
                'course_subgroup_id' => $bookingUser->course_subgroup_id,
                'course_group_id' => $bookingUser->course_group_id,
                'school_id' => $this->school->id ?? null,
            ];

            $monitorNotificationService->notifyAssignment(
                $bookingUser->monitor_id,
                'booking_created',
                $payload,
                $settings,
                auth()->id()
            );
        }
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

    private function getBasketPriceTotal($basket): ?float
    {
        $parsed = [];
        if (is_string($basket)) {
            $decoded = json_decode($basket, true);
            $parsed = is_array($decoded) ? $decoded : [];
        } elseif (is_array($basket)) {
            $parsed = $basket;
        }

        $priceTotal = $parsed['price_total'] ?? $parsed['total_price'] ?? null;
        return is_numeric($priceTotal) ? (float) $priceTotal : null;
    }

    private function basketIndicatesZeroPrice($basket): bool
    {
        $parsed = null;
        if (is_string($basket)) {
            $decoded = json_decode($basket, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
            }
        } elseif (is_array($basket)) {
            $parsed = $basket;
        }

        if (is_array($parsed)) {
            $priceTotal = $parsed['price_total'] ?? $parsed['total_price'] ?? null;
            if (is_numeric($priceTotal) && (float) $priceTotal <= 0) {
                return true;
            }
        }

        if (is_string($basket)) {
            $normalized = strtolower(preg_replace('/\\s+/', '', $basket));
            if (strpos($normalized, '"price_total":0') !== false || strpos($normalized, '"total_price":0') !== false) {
                return true;
            }
        }

        return false;
    }

    private function logBookingError(string $event, array $context = [], ?\Throwable $exception = null): void
    {
        $payload = array_merge([
            'event' => $event,
            'school_id' => $this->school?->id ?? null,
            'route' => request()->path(),
            'ip' => request()->ip(),
        ], $context);

        if ($exception) {
            $payload['exception'] = $exception->getMessage();
            $payload['file'] = $exception->getFile();
            $payload['line'] = $exception->getLine();
            Log::channel('bookings')->error('BOOKING_PAGE_EXCEPTION', $payload);
            return;
        }

        Log::channel('bookings')->warning('BOOKING_PAGE_ERROR', $payload);
    }

}











