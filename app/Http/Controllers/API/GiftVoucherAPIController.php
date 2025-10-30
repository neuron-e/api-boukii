<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreateGiftVoucherAPIRequest;
use App\Http\Requests\API\UpdateGiftVoucherAPIRequest;
use App\Http\Requests\API\PurchaseGiftVoucherRequest;
use App\Http\Resources\API\GiftVoucherResource;
use App\Mail\GiftVoucherDeliveredMail;
use App\Models\GiftVoucher;
use App\Models\School;
use App\Models\Voucher;
use App\Repositories\GiftVoucherRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Class GiftVoucherAPIController
 */
class GiftVoucherAPIController extends AppBaseController
{
    /** @var GiftVoucherRepository */
    private GiftVoucherRepository $giftVoucherRepository;

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
     * Initialize a public gift voucher purchase (no authentication required)
     */
    public function publicPurchase(PurchaseGiftVoucherRequest $request): JsonResponse
    {
        $data = $request->validated();

        $supportedLocales = ['es', 'en', 'fr', 'de', 'it'];
        $buyerLocale = strtolower(substr($data['buyer_locale'] ?? $request->getPreferredLanguage($supportedLocales) ?? config('app.locale', 'en'), 0, 2));
        if (!in_array($buyerLocale, $supportedLocales, true)) {
            $buyerLocale = 'en';
        }

        $recipientLocale = strtolower(substr($data['recipient_locale'] ?? $buyerLocale, 0, 2));
        if (!in_array($recipientLocale, $supportedLocales, true)) {
            $recipientLocale = $buyerLocale;
        }

        $currency = strtoupper($data['currency']);

        $school = School::findOrFail($data['school_id']);

        try {
            $giftVoucher = DB::transaction(function () use ($data, $currency, $buyerLocale, $recipientLocale, $school) {
                $giftVoucherPayload = [
                    'code' => GiftVoucher::generateUniqueCode(),
                    'amount' => $data['amount'],
                    'balance' => $data['amount'],
                    'currency' => $currency,
                    'personal_message' => $data['personal_message'] ?? null,
                    'sender_name' => $data['buyer_name'],
                    'buyer_name' => $data['buyer_name'],
                    'buyer_email' => $data['buyer_email'],
                    'buyer_phone' => $data['buyer_phone'] ?? null,
                    'buyer_locale' => $buyerLocale,
                    'recipient_email' => $data['recipient_email'],
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'] ?? null,
                    'recipient_locale' => $recipientLocale,
                    'template' => $data['template'] ?? 'default',
                    'delivery_date' => $data['delivery_date'] ?? null,
                    'school_id' => $school->id,
                    'status' => 'active',
                    'is_paid' => true,
                    'is_delivered' => false,
                    'is_redeemed' => false,
                    'notes' => 'Public purchase'
                ];

                /** @var GiftVoucher $giftVoucher */
                $giftVoucher = $this->giftVoucherRepository->create($giftVoucherPayload);

                $voucher = Voucher::create([
                    'code' => Voucher::generateUniqueCode('GIFT'),
                    'name' => $data['recipient_name'] . ' Gift Voucher',
                    'description' => $data['personal_message'] ?? null,
                    'quantity' => $data['amount'],
                    'remaining_balance' => $data['amount'],
                    'payed' => true,
                    'is_gift' => true,
                    'is_transferable' => false,
                    'client_id' => null,
                    'buyer_name' => $data['buyer_name'],
                    'buyer_email' => $data['buyer_email'],
                    'buyer_phone' => $data['buyer_phone'] ?? null,
                    'recipient_name' => $data['recipient_name'],
                    'recipient_email' => $data['recipient_email'],
                    'recipient_phone' => $data['recipient_phone'] ?? null,
                    'school_id' => $school->id,
                    'created_by' => 'public-purchase',
                ]);

                $giftVoucher->voucher_id = $voucher->id;
                $giftVoucher->balance = $giftVoucher->amount;
                $giftVoucher->save();

                return $giftVoucher->fresh(['voucher']);
            });

            $this->sendGiftVoucherEmail($giftVoucher, $school);

            return $this->sendResponse([
                'gift_voucher' => new GiftVoucherResource($giftVoucher),
                'voucher_code' => $giftVoucher->code,
                'payment_url' => null
            ], 'Gift voucher purchased successfully');
        } catch (\Throwable $exception) {
            Log::error('Error during public gift voucher purchase', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            return $this->sendError('Unable to process gift voucher purchase', null, 500);
        }
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

    /**
     * Send the gift voucher email to the recipient (and optionally to the buyer)
     */
    private function sendGiftVoucherEmail(GiftVoucher $giftVoucher, School $school): void
    {
        try {
            $giftVoucher->loadMissing(['voucher']);
            $recipientLocale = $giftVoucher->recipient_locale ?? $giftVoucher->buyer_locale ?? config('app.locale', 'en');
            Mail::to($giftVoucher->recipient_email)->send(
                new GiftVoucherDeliveredMail($giftVoucher, $school, $recipientLocale)
            );

            if ($giftVoucher->buyer_email && $giftVoucher->buyer_email !== $giftVoucher->recipient_email) {
                Mail::to($giftVoucher->buyer_email)->send(
                    new GiftVoucherDeliveredMail($giftVoucher, $school, $giftVoucher->buyer_locale ?? $recipientLocale)
                );
            }

            $giftVoucher->markAsDelivered();
        } catch (\Throwable $exception) {
            Log::error('Error sending gift voucher email', [
                'gift_voucher_id' => $giftVoucher->id,
                'error' => $exception->getMessage()
            ]);
        }
    }
}
