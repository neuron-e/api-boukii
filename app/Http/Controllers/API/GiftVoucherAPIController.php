<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateGiftVoucherAPIRequest;
use App\Http\Requests\API\UpdateGiftVoucherAPIRequest;
use App\Http\Resources\API\GiftVoucherResource;
use App\Models\GiftVoucher;
use App\Repositories\GiftVoucherRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class GiftVoucherAPIController
 */
class GiftVoucherAPIController extends AppBaseController
{
    /** @var  GiftVoucherRepository */
    private $giftVoucherRepository;

    public function __construct(GiftVoucherRepository $giftVoucherRepo)
    {
        $this->giftVoucherRepository = $giftVoucherRepo;
    }

    /**
     * @OA\Get(
     *      path="/gift-vouchers",
     *      summary="getGiftVoucherList",
     *      tags={"GiftVoucher"},
     *      description="Get all Gift Vouchers",
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
     *                  @OA\Items(ref="#/components/schemas/GiftVoucher")
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
        $giftVouchers = $this->giftVoucherRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id'),
            null,
            $request->get('onlyTrashed', false)
        );

        return $this->sendResponse($giftVouchers, 'Gift Vouchers retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/gift-vouchers/templates",
     *      summary="getGiftVoucherTemplates",
     *      tags={"GiftVoucher"},
     *      description="Get available gift voucher templates",
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
     *                  type="object"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function templates(): JsonResponse
    {
        $templates = GiftVoucher::getAvailableTemplates();

        return $this->sendResponse($templates, 'Templates retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/gift-vouchers/pending-delivery",
     *      summary="getPendingDeliveryGiftVouchers",
     *      tags={"GiftVoucher"},
     *      description="Get gift vouchers pending delivery",
     *      @OA\Parameter(
     *          name="school_id",
     *          in="query",
     *          description="Filter by school ID",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
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
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/GiftVoucher")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function pendingDelivery(Request $request): JsonResponse
    {
        $query = GiftVoucher::where('is_delivered', false)
            ->where('is_paid', true)
            ->where(function($q) {
                $q->whereNull('delivery_date')
                  ->orWhere('delivery_date', '<=', now());
            });

        if ($request->has('school_id')) {
            $query->where('school_id', $request->input('school_id'));
        }

        $giftVouchers = $query->with(['school', 'purchasedBy', 'voucher'])
            ->orderBy('delivery_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->sendResponse($giftVouchers, 'Pending delivery gift vouchers retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/gift-vouchers",
     *      summary="createGiftVoucher",
     *      tags={"GiftVoucher"},
     *      description="Create Gift Voucher",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/GiftVoucher")
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
     *                  ref="#/components/schemas/GiftVoucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateGiftVoucherAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        // Validate school_id matches user's school if provided
        if ($request->user()) {
            $school = $this->getSchool($request);
            if ($school && isset($input['school_id']) && $input['school_id'] != $school->id) {
                return $this->sendError('School ID does not match user school', [], 403);
            }
        }

        $giftVoucher = $this->giftVoucherRepository->create($input);

        return $this->sendResponse($giftVoucher, 'Gift Voucher saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/gift-vouchers/{id}",
     *      summary="getGiftVoucherItem",
     *      tags={"GiftVoucher"},
     *      description="Get Gift Voucher",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of GiftVoucher",
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
     *                  ref="#/components/schemas/GiftVoucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Gift Voucher not found"
     *      )
     * )
     */
    public function show($id, Request $request): JsonResponse
    {
        /** @var GiftVoucher $giftVoucher */
        $giftVoucher = $this->giftVoucherRepository->find($id, with: $request->get('with', []));

        if (empty($giftVoucher)) {
            return $this->sendError('Gift Voucher not found');
        }

        return $this->sendResponse($giftVoucher, 'Gift Voucher retrieved successfully');
    }

    /**
     * @OA\Get(
     *      path="/gift-vouchers/{id}/summary",
     *      summary="getGiftVoucherSummary",
     *      tags={"GiftVoucher"},
     *      description="Get Gift Voucher summary with detailed information",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of GiftVoucher",
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
     *                  type="object"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Gift Voucher not found"
     *      )
     * )
     */
    public function summary(int $id): JsonResponse
    {
        $giftVoucher = GiftVoucher::with(['school', 'purchasedBy', 'redeemedBy', 'voucher'])->find($id);

        if (!$giftVoucher) {
            return $this->sendError('Gift Voucher not found', null, 404);
        }

        return $this->sendResponse($giftVoucher->getSummary(), 'Gift Voucher summary retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/gift-vouchers/{id}",
     *      summary="updateGiftVoucher",
     *      tags={"GiftVoucher"},
     *      description="Update Gift Voucher",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of GiftVoucher",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/GiftVoucher")
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
     *                  ref="#/components/schemas/GiftVoucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Gift Voucher not found"
     *      )
     * )
     */
    public function update($id, UpdateGiftVoucherAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var GiftVoucher $giftVoucher */
        $giftVoucher = $this->giftVoucherRepository->find($id, with: $request->get('with', []));

        if (empty($giftVoucher)) {
            return $this->sendError('Gift Voucher not found');
        }

        // Validate school_id matches user's school if provided
        if ($request->user() && isset($input['school_id'])) {
            $school = $this->getSchool($request);
            if ($school && $input['school_id'] != $school->id && $giftVoucher->school_id != $school->id) {
                return $this->sendError('School ID does not match user school', [], 403);
            }
        }

        $giftVoucher = $this->giftVoucherRepository->update($input, $id);

        return $this->sendResponse($giftVoucher, 'Gift Voucher updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/gift-vouchers/{id}",
     *      summary="deleteGiftVoucher",
     *      tags={"GiftVoucher"},
     *      description="Delete Gift Voucher",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of GiftVoucher",
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
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Gift Voucher not found"
     *      )
     * )
     */
    public function destroy($id): JsonResponse
    {
        /** @var GiftVoucher $giftVoucher */
        $giftVoucher = $this->giftVoucherRepository->find($id);

        if (empty($giftVoucher)) {
            return $this->sendError('Gift Voucher not found');
        }

        $giftVoucher->delete();

        return $this->sendSuccess('Gift Voucher deleted successfully');
    }

    /**
     * @OA\Post(
     *      path="/gift-vouchers/{id}/redeem",
     *      summary="redeemGiftVoucher",
     *      tags={"GiftVoucher"},
     *      description="Redeem gift voucher and create voucher for client",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of GiftVoucher",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(
     *            required={"client_id"},
     *            @OA\Property(
     *                property="client_id",
     *                type="integer",
     *                description="ID of the client redeeming the gift voucher"
     *            )
     *        )
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
     *                  ref="#/components/schemas/GiftVoucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Gift Voucher cannot be redeemed"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Gift Voucher not found"
     *      )
     * )
     */
    public function redeem(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer|exists:clients,id'
        ]);

        $giftVoucher = GiftVoucher::find($id);

        if (!$giftVoucher) {
            return $this->sendError('Gift Voucher not found', null, 404);
        }

        if (!$giftVoucher->canBeRedeemed()) {
            $reasons = [];

            if (!$giftVoucher->is_paid) {
                $reasons[] = 'Gift voucher is not paid';
            }

            if (!$giftVoucher->is_delivered) {
                $reasons[] = 'Gift voucher has not been delivered';
            }

            if ($giftVoucher->is_redeemed) {
                $reasons[] = 'Gift voucher has already been redeemed';
            }

            if ($giftVoucher->trashed()) {
                $reasons[] = 'Gift voucher has been deleted';
            }

            return $this->sendError('Gift voucher cannot be redeemed', ['reasons' => $reasons], 400);
        }

        $success = $giftVoucher->redeem($request->input('client_id'));

        if ($success) {
            return $this->sendResponse(
                $giftVoucher->load(['voucher', 'redeemedBy', 'school']),
                'Gift voucher redeemed successfully'
            );
        }

        return $this->sendError('Error redeeming gift voucher', null, 500);
    }

    /**
     * @OA\Post(
     *      path="/gift-vouchers/{id}/deliver",
     *      summary="deliverGiftVoucher",
     *      tags={"GiftVoucher"},
     *      description="Mark gift voucher as delivered manually",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of GiftVoucher",
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
     *                  ref="#/components/schemas/GiftVoucher"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Gift Voucher already delivered"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Gift Voucher not found"
     *      )
     * )
     */
    public function deliver(int $id): JsonResponse
    {
        $giftVoucher = GiftVoucher::find($id);

        if (!$giftVoucher) {
            return $this->sendError('Gift Voucher not found', null, 404);
        }

        if ($giftVoucher->is_delivered) {
            return $this->sendError('Gift voucher has already been delivered', null, 400);
        }

        $success = $giftVoucher->markAsDelivered();

        if ($success) {
            return $this->sendResponse(
                $giftVoucher->load(['school', 'purchasedBy']),
                'Gift voucher marked as delivered successfully'
            );
        }

        return $this->sendError('Error marking gift voucher as delivered', null, 500);
    }
}
