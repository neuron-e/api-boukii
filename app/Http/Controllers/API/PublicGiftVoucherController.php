<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\API\CreatePublicGiftVoucherRequest;
use App\Models\GiftVoucher;
use App\Services\PublicGiftVoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     *                  @OA\Property(property="url", type="string", example="https://payrexx.com/..."),
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
        DB::beginTransaction();
        try {
            // 1. Crear voucher pendiente
            $voucher = $this->service->createPendingVoucher($request->validated());

            // 2. Crear gateway de pago Payrexx
            $paymentUrl = $this->service->createPayrexxGateway($voucher);

            DB::commit();

            Log::info('Public gift voucher purchase initiated', [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
                'amount' => $voucher->amount
            ]);

            return $this->sendResponse([
                'url' => $paymentUrl,
                'voucher_id' => $voucher->id,
                'code' => $voucher->code
            ], 'Gift voucher created successfully. Please proceed to payment.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating public gift voucher', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return $this->sendError(
                'Error creating gift voucher: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *      path="/api/public/gift-vouchers/verify/{code}",
     *      summary="verifyGiftVoucherCode",
     *      tags={"Public Gift Voucher"},
     *      description="Verificar validez de un código de gift voucher (sin autenticación)",
     *      @OA\Parameter(
     *          name="code",
     *          in="path",
     *          description="Gift voucher code (ej: GV-ABCD-1234)",
     *          required=true,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="valid", type="boolean", example=true),
     *              @OA\Property(property="code", type="string", example="GV-ABCD-1234"),
     *              @OA\Property(property="balance", type="number", example=50.00),
     *              @OA\Property(property="currency", type="string", example="EUR"),
     *              @OA\Property(property="status", type="string", example="active"),
     *              @OA\Property(property="expires_at", type="string", example="2026-10-29"),
     *              @OA\Property(property="recipient_name", type="string", example="Juan Pérez"),
     *              @OA\Property(property="sender_name", type="string", example="María García")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Gift voucher not found")
     * )
     */
    public function verify(string $code): JsonResponse
    {
        $result = $this->service->verifyCode($code);

        if ($result === null) {
            return $this->sendError('Gift voucher not found', [], 404);
        }

        Log::info('Gift voucher code verified', [
            'code' => $code,
            'valid' => $result['valid']
        ]);

        return $this->sendResponse($result, 'Gift voucher code verified');
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
        Log::info('Payrexx webhook received for gift voucher', [
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
                Log::warning('Invalid referenceId format in webhook', ['referenceId' => $referenceId]);
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
                Log::info('Gift voucher payment failed or cancelled', [
                    'voucher_id' => $voucherId,
                    'status' => $status,
                    'transaction_id' => $transactionId
                ]);

                // Opcionalmente, cancelar el voucher
                $this->service->cancelVoucher($voucherId);
            }

            return $this->sendResponse([], 'Webhook processed');

        } catch (\Exception $e) {
            Log::error('Error processing Payrexx webhook for gift voucher', [
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
