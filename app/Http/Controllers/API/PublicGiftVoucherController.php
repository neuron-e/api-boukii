<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreatePublicGiftVoucherRequest;
use App\Http\Resources\API\GiftVoucherResource;
use App\Models\GiftVoucher;
use App\Models\School;
use App\Services\PublicGiftVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador para compras públicas de Gift Vouchers
 * Endpoints sin autenticación para permitir compras desde la web pública
 */
class PublicGiftVoucherController extends AppBaseController
{
    protected PublicGiftVoucherService $service;

    public function __construct(PublicGiftVoucherService $service)
    {
        $this->service = $service;
    }

    /**
     * @OA\Post(
     *      path="/api/public/gift-vouchers/purchase",
     *      summary="purchasePublicGiftVoucher",
     *      tags={"Public Gift Voucher"},
     *      description="Compra pública de gift voucher (sin autenticación)",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(
     *            required={"amount","currency","recipient_email","recipient_name","sender_name","school_id"},
     *            @OA\Property(property="amount", type="number", example=50.00),
     *            @OA\Property(property="currency", type="string", example="EUR"),
     *            @OA\Property(property="recipient_email", type="string", example="destinatario@email.com"),
     *            @OA\Property(property="recipient_name", type="string", example="Juan Pérez"),
     *            @OA\Property(property="sender_name", type="string", example="María García"),
     *            @OA\Property(property="personal_message", type="string", example="Feliz cumpleaños!"),
     *            @OA\Property(property="school_id", type="integer", example=1),
     *            @OA\Property(property="template", type="string", example="birthday"),
     *            @OA\Property(property="delivery_date", type="string", format="date", example="2025-11-15")
     *        )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="url", type="string", example="https://pay.boukii.com/..."),
     *                  @OA\Property(property="voucher_id", type="integer", example=123),
     *                  @OA\Property(property="code", type="string", example="GV-ABCD-1234")
     *              ),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function purchase(CreatePublicGiftVoucherRequest $request): JsonResponse
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

        $school = School::findOrFail($data['school_id']);
        $currency = strtoupper($data['currency'] ?? $school->currency ?? 'CHF');

        try {
            $voucher = $this->service->createPendingVoucher([
                'amount' => $data['amount'],
                'currency' => $currency,
                'recipient_email' => $data['recipient_email'],
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'recipient_locale' => $recipientLocale,
                'sender_name' => $data['sender_name'] ?? $data['buyer_name'],
                'personal_message' => $data['personal_message'] ?? null,
                'school_id' => $school->id,
                'template' => $data['template'] ?? 'default',
                'delivery_date' => $data['delivery_date'] ?? null,
                'buyer_name' => $data['buyer_name'],
                'buyer_email' => $data['buyer_email'],
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'buyer_locale' => $buyerLocale,
            ]);

            $bookingUrl = config('app.booking_url') ?? env('BOOKING_URL', 'http://localhost:4201');
            $redirectQuery = http_build_query([
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
            ]);

        $baseBookingUrl = rtrim($bookingUrl, '/');
        $schoolSlugPath = $school->slug ? '/' . ltrim($school->slug, '/') : '';

        $redirects = [
            'success' => "{$baseBookingUrl}{$schoolSlugPath}/gift-vouchers/success?{$redirectQuery}",
            'failed' => "{$baseBookingUrl}{$schoolSlugPath}/gift-vouchers/failed?{$redirectQuery}",
            'cancel' => "{$baseBookingUrl}{$schoolSlugPath}/gift-vouchers/cancel?{$redirectQuery}",
        ];

            $paymentUrl = $this->service->createPayrexxGateway($voucher, $redirects);

            Log::channel('vouchers')->info('Public gift voucher created awaiting payment', [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
                'payment_url' => $paymentUrl,
            ]);

            return $this->sendResponse([
                'gift_voucher' => new GiftVoucherResource($voucher),
                'voucher_code' => $voucher->code,
                'payment_url' => $paymentUrl,
                'url' => $paymentUrl,
                'status' => 'pending_payment',
            ], 'Gift voucher created successfully. Please complete payment.');

        } catch (\Exception $e) {
            Log::channel('vouchers')->error('Error creating public gift voucher', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($voucher) && $voucher instanceof GiftVoucher) {
                $voucher->delete();
            }

            return $this->sendError(
                'Error creating gift voucher: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    public function verify(string $code, Request $request): JsonResponse
    {
        $schoolSlug = $request->query('school_slug');
        $voucherId = $request->query('voucher_id');
        $result = $this->service->verifyCode($code, $schoolSlug, $voucherId);

        if ($result === null) {
            return $this->sendError('Gift voucher not found', [], 404);
        }

        Log::channel('vouchers')->info('Gift voucher code verified', [
            'code' => $code,
            'valid' => $result['valid']
        ]);

        return $this->sendResponse($result, 'Gift voucher code verified');
    }

    public function cancel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'voucher_id' => 'required|integer',
            'code' => 'required|string',
            'school_slug' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Invalid cancellation request', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $cancelled = $this->service->cancelPendingVoucher(
            $data['voucher_id'],
            $data['code'],
            $data['school_slug'] ?? null
        );

        if (!$cancelled) {
            return $this->sendError('Gift voucher could not be cancelled', [], 404);
        }

        return $this->sendResponse([], 'Gift voucher cancelled successfully');
    }

    /**
     * @OA\Post(
     *      path="/api/webhooks/payrexx/gift-voucher",
     *      summary="payrexxGiftVoucherWebhook",
     *      tags={"Webhooks"},
     *      description="Webhook de Payrexx para confirmar pago de gift voucher",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(
     *            @OA\Property(
     *                property="transaction",
     *                type="object",
     *                @OA\Property(property="id", type="integer"),
     *                @OA\Property(property="status", type="string"),
     *                @OA\Property(property="referenceId", type="string")
     *            )
     *        )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Webhook processed successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=400, description="Invalid webhook data"),
     *      @OA\Response(response=404, description="Gift voucher not found")
     * )
     */
    public function payrexxWebhook(Request $request): JsonResponse
    {
        // Log del webhook recibido
        Log::channel('vouchers')->info('Payrexx webhook received for gift voucher', [
            'payload' => $request->all()
        ]);

        // TODO: Validar firma/token de Payrexx para seguridad
        // Esta validación debe implementarse según la documentación de Payrexx
        // Por ahora, aceptamos el webhook directamente

        try {
            $transaction = $request->input('transaction');

            if (!$transaction) {
                return $this->sendError('Invalid webhook data: missing transaction', [], 400);
            }

            $transactionId = $transaction['id'] ?? null;
            $status = $transaction['status'] ?? null;
            $referenceId = $transaction['referenceId'] ?? null;

            if (!$transactionId || !$status || !$referenceId) {
                return $this->sendError('Invalid webhook data: missing required fields', [], 400);
            }

            // Extraer voucher_id del referenceId (formato: GV-{id})
            if (!preg_match('/^GV-(\d+)$/', $referenceId, $matches)) {
                Log::channel('vouchers')->warning('Invalid referenceId format in webhook', ['referenceId' => $referenceId]);
                return $this->sendError('Invalid referenceId format', [], 400);
            }

            $voucherId = (int) $matches[1];

            // Si el pago fue confirmado, activar el voucher
            if ($status === 'confirmed' || $status === 'waiting') {
                $success = $this->service->confirmPayment($voucherId, (string) $transactionId);

                if ($success) {
                    return $this->sendResponse(
                        ['voucher_id' => $voucherId],
                        'Gift voucher payment confirmed and activated'
                    );
                } else {
                    return $this->sendError('Failed to confirm payment', [], 500);
                }
            }

            // Si el pago falló o fue cancelado, registrar pero no hacer nada
            if ($status === 'declined' || $status === 'cancelled') {
                Log::channel('vouchers')->info('Gift voucher payment failed or cancelled', [
                    'voucher_id' => $voucherId,
                    'status' => $status,
                    'transaction_id' => $transactionId
                ]);

                // Opcionalmente, cancelar el voucher
                $this->service->cancelVoucher($voucherId);
            }

            return $this->sendResponse([], 'Webhook processed');

        } catch (\Exception $e) {
            Log::channel('vouchers')->error('Error processing Payrexx webhook for gift voucher', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            return $this->sendError('Error processing webhook: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/public/gift-vouchers/templates",
     *      summary="getGiftVoucherTemplates",
     *      tags={"Public Gift Voucher"},
     *      description="Obtener templates disponibles para gift vouchers",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="data", type="object"),
     *              @OA\Property(property="message", type="string")
     *          )
     *      )
     * )
     */
    public function templates(): JsonResponse
    {
        $templates = GiftVoucher::getAvailableTemplates();
        return $this->sendResponse($templates, 'Templates retrieved successfully');
    }
}

