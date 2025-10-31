<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDiscountCodeAPIRequest;
use App\Http\Requests\API\UpdateDiscountCodeAPIRequest;
use App\Models\DiscountCode;
use App\Repositories\DiscountCodeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Resources\DiscountCodeResource;

/**
 * Class DiscountCodeController
 */

class DiscountCodeAPIController extends AppBaseController
{
    /** @var  DiscountCodeRepository */
    private $discountCodeRepository;

    public function __construct(DiscountCodeRepository $discountCodeRepo)
    {
        $this->discountCodeRepository = $discountCodeRepo;
    }

    /**
     * @OA\Get(
     *      path="/discount-codes",
     *      summary="getDiscountCodeList",
     *      tags={"DiscountCode"},
     *      description="Get all DiscountCodes",
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
     *                  @OA\Items(ref="#/components/schemas/DiscountCode")
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
        $discountCodes = $this->discountCodeRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($discountCodes, 'Discount Codes retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/discount-codes",
     *      summary="createDiscountCode",
     *      tags={"DiscountCode"},
     *      description="Create DiscountCode",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/DiscountCode")
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
     *                  ref="#/components/schemas/DiscountCode"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateDiscountCodeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $discountCode = $this->discountCodeRepository->create($input);

        return $this->sendResponse($discountCode, 'Discount Code saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/discount-codes/{id}",
     *      summary="getDiscountCodeItem",
     *      tags={"DiscountCode"},
     *      description="Get DiscountCode",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of DiscountCode",
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
     *                  ref="#/components/schemas/DiscountCode"
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
        /** @var DiscountCode $discountCode */
        $discountCode = $this->discountCodeRepository->find($id,  with: $request->get('with', []));

        if (empty($discountCode)) {
            return $this->sendError('Discount Code not found');
        }

        return $this->sendResponse($discountCode, 'Discount Code retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/discount-codes/{id}",
     *      summary="updateDiscountCode",
     *      tags={"DiscountCode"},
     *      description="Update DiscountCode",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of DiscountCode",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/DiscountCode")
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
     *                  ref="#/components/schemas/DiscountCode"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateDiscountCodeAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var DiscountCode $discountCode */
        $discountCode = $this->discountCodeRepository->find($id);

        if (empty($discountCode)) {
            return $this->sendError('Discount Code not found');
        }

        $discountCode = $this->discountCodeRepository->update($input, $id);

        return $this->sendResponse(new DiscountCodeResource($discountCode), 'DiscountCode updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/discount-codes/{id}",
     *      summary="deleteDiscountCode",
     *      tags={"DiscountCode"},
     *      description="Delete DiscountCode",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of DiscountCode",
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
        /** @var DiscountCode $discountCode */
        $discountCode = $this->discountCodeRepository->find($id);

        if (empty($discountCode)) {
            return $this->sendError('Discount Code not found');
        }

        $discountCode->delete();

        return $this->sendSuccess('Discount Code deleted successfully');
    }

    /**
     * @OA\Get(
     *      path="/discount-codes/active",
     *      summary="getActiveDiscountCodes",
     *      tags={"DiscountCode"},
     *      description="Get all active discount codes",
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
     *                  @OA\Items(ref="#/components/schemas/DiscountCode")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function active(Request $request): JsonResponse
    {
        $now = now();

        $query = DiscountCode::where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('remaining')
                    ->orWhere('remaining', '>', 0);
            });

        // Filtrar por escuela si se proporciona
        if ($request->has('school_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('school_id')
                    ->orWhere('school_id', $request->school_id);
            });
        }

        $discountCodes = $query->orderBy('created_at', 'desc')->get();

        return $this->sendResponse($discountCodes, 'Active discount codes retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/discount-codes/validate",
     *      summary="validateDiscountCode",
     *      tags={"DiscountCode"},
     *      description="Validate a discount code for a specific booking",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(
     *            @OA\Property(property="code", type="string", example="SUMMER2025"),
     *            @OA\Property(property="school_id", type="integer", example=1),
     *            @OA\Property(property="purchase_amount", type="number", example=100.50),
     *            @OA\Property(property="course_ids", type="array", @OA\Items(type="integer")),
     *            @OA\Property(property="client_id", type="integer")
     *        )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="valid", type="boolean"),
     *                  @OA\Property(property="discount_amount", type="number"),
     *                  @OA\Property(property="message", type="string"),
     *                  @OA\Property(property="discount_code", ref="#/components/schemas/DiscountCode")
     *              ),
     *              @OA\Property(property="message", type="string")
     *          )
     *      )
     * )
     */
    public function validateCode(Request $request): JsonResponse
    {
        $code = $request->input('code');
        $schoolId = $request->input('school_id');
        $purchaseAmount = (float) $request->input('purchase_amount', 0);
        $courseIds = $request->input('course_ids', []);
        $clientId = $request->input('client_id');

        if (!$code) {
            return $this->sendError('Discount code is required', 400);
        }

        /** @var DiscountCode $discountCode */
        $discountCode = DiscountCode::where('code', $code)->first();

        if (!$discountCode) {
            return $this->sendResponse([
                'valid' => false,
                'message' => 'Discount code not found'
            ], 'Validation completed');
        }

        // Verificar si está activo
        if (!$discountCode->isActive()) {
            return $this->sendResponse([
                'valid' => false,
                'message' => 'This discount code is not active'
            ], 'Validation completed');
        }

        // Verificar validez temporal
        if (!$discountCode->isValidForDate()) {
            return $this->sendResponse([
                'valid' => false,
                'message' => 'This discount code is not valid at this time'
            ], 'Validation completed');
        }

        // Verificar usos disponibles
        if (!$discountCode->hasUsesAvailable()) {
            return $this->sendResponse([
                'valid' => false,
                'message' => 'This discount code has no uses remaining'
            ], 'Validation completed');
        }

        // Verificar escuela
        if ($schoolId && !$discountCode->isValidForSchool($schoolId)) {
            return $this->sendResponse([
                'valid' => false,
                'message' => 'This discount code is not valid for this school'
            ], 'Validation completed');
        }

        // Verificar cursos si se proporcionan
        if (!empty($courseIds) && !empty($discountCode->course_ids)) {
            $validForAnyCourse = false;
            foreach ($courseIds as $courseId) {
                if ($discountCode->isValidForCourse($courseId)) {
                    $validForAnyCourse = true;
                    break;
                }
            }

            if (!$validForAnyCourse) {
                return $this->sendResponse([
                    'valid' => false,
                    'message' => 'This discount code is not valid for the selected courses'
                ], 'Validation completed');
            }
        }

        // Verificar monto mínimo
        if (!$discountCode->meetsMinimumPurchase($purchaseAmount)) {
            return $this->sendResponse([
                'valid' => false,
                'message' => 'Minimum purchase amount not met for this discount code',
                'minimum_required' => $discountCode->min_purchase_amount
            ], 'Validation completed');
        }

        // Calcular descuento
        $discountAmount = $discountCode->calculateDiscountAmount($purchaseAmount);

        return $this->sendResponse([
            'valid' => true,
            'discount_amount' => $discountAmount,
            'final_amount' => max(0, $purchaseAmount - $discountAmount),
            'message' => 'Discount code is valid',
            'discount_code' => new DiscountCodeResource($discountCode)
        ], 'Discount code validated successfully');
    }

    /**
     * @OA\Get(
     *      path="/discount-codes/{id}/stats",
     *      summary="getDiscountCodeStats",
     *      tags={"DiscountCode"},
     *      description="Get usage statistics for a discount code",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of DiscountCode",
     *          @OA\Schema(type="integer"),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="total_uses", type="integer"),
     *                  @OA\Property(property="remaining_uses", type="integer"),
     *                  @OA\Property(property="usage_percentage", type="number"),
     *                  @OA\Property(property="total_discount_given", type="number"),
     *                  @OA\Property(property="average_discount", type="number"),
     *                  @OA\Property(property="is_active", type="boolean"),
     *                  @OA\Property(property="is_expired", type="boolean")
     *              ),
     *              @OA\Property(property="message", type="string")
     *          )
     *      )
     * )
     */
    public function stats($id): JsonResponse
    {
        /** @var DiscountCode $discountCode */
        $discountCode = $this->discountCodeRepository->find($id);

        if (empty($discountCode)) {
            return $this->sendError('Discount Code not found');
        }

        // Calcular estadísticas básicas
        $totalUses = $discountCode->total ?? 0;
        $remainingUses = $discountCode->remaining ?? 0;
        $usedCount = $totalUses > 0 ? $totalUses - $remainingUses : 0;
        $usagePercentage = $totalUses > 0 ? ($usedCount / $totalUses) * 100 : 0;

        // Obtener usos desde la tabla de bookings si existe la relación
        $bookingUses = \DB::table('bookings')
            ->where('discount_code_id', $discountCode->id)
            ->whereNotNull('discount_amount')
            ->get();

        $totalDiscountGiven = $bookingUses->sum('discount_amount');
        $averageDiscount = $bookingUses->count() > 0
            ? $totalDiscountGiven / $bookingUses->count()
            : 0;

        $stats = [
            'id' => $discountCode->id,
            'code' => $discountCode->code,
            'name' => $discountCode->name,
            'total_uses' => $totalUses,
            'used_count' => $usedCount,
            'remaining_uses' => $remainingUses,
            'usage_percentage' => round($usagePercentage, 2),
            'total_discount_given' => round($totalDiscountGiven, 2),
            'average_discount' => round($averageDiscount, 2),
            'is_active' => $discountCode->isActive(),
            'is_valid_now' => $discountCode->isValidForDate(),
            'is_expired' => $discountCode->valid_to && $discountCode->valid_to->isPast(),
            'has_uses_available' => $discountCode->hasUsesAvailable(),
            'valid_from' => $discountCode->valid_from?->format('Y-m-d H:i:s'),
            'valid_to' => $discountCode->valid_to?->format('Y-m-d H:i:s'),
            'created_at' => $discountCode->created_at?->format('Y-m-d H:i:s'),
        ];

        return $this->sendResponse($stats, 'Discount code statistics retrieved successfully');
    }
}
