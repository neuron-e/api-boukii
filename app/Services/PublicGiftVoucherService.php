<?php

namespace App\Services;

use App\Models\GiftVoucher;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\GiftVoucherDeliveredMail;
use Payrexx\Payrexx;
use Payrexx\Communicator;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\PayrexxException;

/**
 * Servicio para gestión pública de Gift Vouchers
 * Maneja la creación, pago y activación de gift vouchers desde usuarios públicos
 */
class PublicGiftVoucherService
{
    /**
     * Crear un gift voucher pendiente (antes del pago)
     */
    public function createPendingVoucher(array $data): GiftVoucher
    {
        $voucherData = [
            'code' => $this->generateUniqueCode(),
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'balance' => 0,
            'recipient_email' => $data['recipient_email'],
            'recipient_name' => $data['recipient_name'],
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'recipient_locale' => $data['recipient_locale'] ?? null,
            'sender_name' => $data['sender_name'] ?? ($data['buyer_name'] ?? null),
            'personal_message' => $data['personal_message'] ?? null,
            'school_id' => $data['school_id'],
            'template' => $data['template'] ?? 'default',
            'delivery_date' => $data['delivery_date'] ?? null,
            'status' => 'pending',
            'is_paid' => false,
            'is_delivered' => false,
            'is_redeemed' => false,
            'created_by' => 'public_purchase',
            'buyer_name' => $data['buyer_name'] ?? null,
            'buyer_email' => $data['buyer_email'] ?? null,
            'buyer_phone' => $data['buyer_phone'] ?? null,
            'buyer_locale' => $data['buyer_locale'] ?? null,
            'notes' => $data['notes'] ?? 'Public purchase - awaiting payment',
        ];

        $giftVoucher = GiftVoucher::create($voucherData);

        Log::channel('vouchers')->info('Gift voucher pendiente creado', [
            'gift_voucher_id' => $giftVoucher->id,
            'code' => $giftVoucher->code,
            'amount' => $giftVoucher->amount,
            'currency' => $giftVoucher->currency
        ]);

        return $giftVoucher;
    }

    /**
     * Generar código único para gift voucher
     */
    public function generateUniqueCode(): string
    {
        return GiftVoucher::generateUniqueCode('GV');
    }

    /**
     * Crear gateway de pago Payrexx para el gift voucher
     */
    public function createPayrexxGateway(GiftVoucher $voucher, array $redirectUrls = []): string
    {
        $school = School::find($voucher->school_id);

        if (!$school) {
            Log::channel('vouchers')->error('School not found for gift voucher', ['voucher_id' => $voucher->id]);
            throw new \Exception('School not found');
        }

        // Verificar configuración de Payrexx
        if (empty($school->getPayrexxInstance()) || empty($school->getPayrexxKey())) {
            Log::channel('vouchers')->error('Payrexx configuration incomplete', [
                'school_id' => $school->id,
                'voucher_id' => $voucher->id
            ]);
            throw new \Exception('Payrexx configuration incomplete for this school');
        }

        try {
            $gateway = $this->prepareGatewayRequest($voucher, $school, $redirectUrls);
            $payrexx = $this->createPayrexxClient($school);
            $createdGateway = $payrexx->create($gateway);

            if ($createdGateway && $createdGateway->getLink()) {
                $paymentUrl = $createdGateway->getLink();

                // Guardar referencia del gateway (opcional)
                $voucher->update([
                    'payment_reference' => "payrexx_gateway_{$createdGateway->getId()}"
                ]);

                Log::channel('vouchers')->info('Payrexx gateway created for gift voucher', [
                    'voucher_id' => $voucher->id,
                    'gateway_id' => $createdGateway->getId(),
                    'payment_url' => $paymentUrl
                ]);

                return $paymentUrl;
            }

            throw new \Exception('Failed to create Payrexx gateway');

        } catch (PayrexxException $e) {
            $reason = method_exists($e, 'getReason') ? $e->getReason() : null;
            Log::channel('vouchers')->error('Payrexx exception creating gateway', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'reason' => $reason,
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            $humanMessage = $reason ?: $e->getMessage();
            throw new \Exception('Payment gateway error: ' . $humanMessage);
        }
    }

    /**
     * Confirmar pago y activar gift voucher
     */
    public function confirmPayment(int $voucherId, ?string $transactionId = null): bool
    {
        $voucher = GiftVoucher::find($voucherId);

        if (!$voucher) {
            Log::channel('vouchers')->error('Gift voucher not found for payment confirmation', ['voucher_id' => $voucherId]);
            return false;
        }

        // Verificar que esté en estado pending
        if ($voucher->status !== 'pending') {
            Log::channel('vouchers')->warning('Gift voucher is not in pending status', [
                'voucher_id' => $voucherId,
                'current_status' => $voucher->status
            ]);
            return false;
        }

        DB::beginTransaction();
        try {
            // Actualizar transacción ID si se proporciona
            if ($transactionId) {
                $voucher->payrexx_transaction_id = $transactionId;
            }

            // Activar el voucher
            $voucher->activate();

            // Cargar relación con school
            $school = School::find($voucher->school_id);

            // Enviar email al destinatario con el gift voucher
            if ($school) {
                $this->sendGiftVoucherEmail($voucher, $school);
            }

            DB::commit();

            Log::channel('vouchers')->info('Gift voucher payment confirmed and activated', [
                'voucher_id' => $voucherId,
                'code' => $voucher->code,
                'transaction_id' => $transactionId
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('vouchers')->error('Error confirming gift voucher payment', [
                'voucher_id' => $voucherId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Enviar email del gift voucher al destinatario y comprador
     */
    private function sendGiftVoucherEmail(GiftVoucher $voucher, School $school): void
    {
        try {
            // Cargar el voucher generado (si existe)
            $voucher->loadMissing(['voucher']);

            // Determinar locale del destinatario
            $recipientLocale = $voucher->recipient_locale ?? $voucher->buyer_locale ?? config('app.locale', 'en');

            // Enviar email al destinatario
            Mail::to($voucher->recipient_email)->send(
                new GiftVoucherDeliveredMail($voucher, $school, $recipientLocale)
            );

            // Si hay comprador con email diferente, enviar copia
            if ($voucher->buyer_email && $voucher->buyer_email !== $voucher->recipient_email) {
                $buyerLocale = $voucher->buyer_locale ?? $recipientLocale;
                Mail::to($voucher->buyer_email)->send(
                    new GiftVoucherDeliveredMail($voucher, $school, $buyerLocale)
                );
            }

            // Marcar como entregado
            $voucher->update(['is_delivered' => true]);

            Log::channel('vouchers')->info('Gift voucher emails sent successfully', [
                'voucher_id' => $voucher->id,
                'recipient_email' => $voucher->recipient_email,
                'buyer_email' => $voucher->buyer_email
            ]);

        } catch (\Exception $e) {
            Log::channel('vouchers')->error('Error sending gift voucher emails', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No fallar el proceso completo si falla el email
        }
    }
    /**
     * Enviar email del gift voucher al destinatario y comprador
     */
    

    /**
     * Verificar validez de un código de gift voucher
     */
    public function verifyCode(string $code, ?string $slug = null, ?int $voucherId = null): ?array
    {
        $query = GiftVoucher::with('school')->where('code', $code);

        if ($voucherId) {
            $query->where('id', $voucherId);
        }

        if ($slug) {
            $query->whereHas('school', function ($schoolQuery) use ($slug) {
                $schoolQuery->where('slug', $slug);
            });
        }

        /** @var GiftVoucher|null $voucher */
        $voucher = $query->first();

        if (!$voucher) {
            return null;
        }

        return [
            'id' => $voucher->id,
            'valid' => $voucher->isValid(),
            'code' => $voucher->code,
            'amount' => $voucher->amount,
            'balance' => $voucher->balance,
            'currency' => $voucher->currency,
            'status' => $voucher->status,
            'expires_at' => $voucher->expires_at?->format('Y-m-d'),
            'is_expired' => $voucher->expires_at && $voucher->expires_at->isPast(),
            'recipient_name' => $voucher->recipient_name,
            'sender_name' => $voucher->sender_name
        ];
    }

    public function cancelPendingVoucher(int $voucherId, string $code, ?string $schoolSlug = null): bool
    {
        $query = GiftVoucher::where('id', $voucherId)
            ->where('code', $code);

        if ($schoolSlug) {
            $query->whereHas('school', function ($schoolQuery) use ($schoolSlug) {
                $schoolQuery->where('slug', $schoolSlug);
            });
        }

        $voucher = $query->first();

        if (!$voucher) {
            return false;
        }

        if ($voucher->is_paid || $voucher->status !== 'pending') {
            return false;
        }

        return (bool) $voucher->delete();
    }

    /**
     * Preparar request de gateway para Payrexx
     */
    private function prepareGatewayRequest(GiftVoucher $voucher, School $school, array $redirectUrls = []): GatewayRequest
    {
        $gateway = new GatewayRequest();

        $gateway->setAmount($voucher->amount * 100);
        $gateway->setCurrency($voucher->currency);

        $defaultBase = env('APP_URL') . '/gift-voucher-payment-result';
        $successUrl = $redirectUrls['success'] ?? $defaultBase . '?status=success&code=' . $voucher->code;
        $failedUrl = $redirectUrls['failed'] ?? $defaultBase . '?status=failed&code=' . $voucher->code;
        $cancelUrl = $redirectUrls['cancel'] ?? $defaultBase . '?status=cancel&code=' . $voucher->code;

        $gateway->setSuccessRedirectUrl($successUrl);
        $gateway->setFailedRedirectUrl($failedUrl);
        $gateway->setCancelRedirectUrl($cancelUrl);

        $gateway->setReferenceId("GV-{$voucher->id}");
        $gateway->setValidity(30);

        $customerFields = [
            'email' => $voucher->recipient_email,
            'forename' => $voucher->sender_name ?? '',
            'surname' => ''
        ];

        if (method_exists($gateway, 'setFields')) {
            $gateway->setFields($customerFields);
        } elseif (method_exists($gateway, 'addField')) {
            foreach ($customerFields as $key => $value) {
                $gateway->addField($key, $value);
            }
        }

        $gateway->setPurpose("Gift Voucher: {$voucher->code}");

        Log::channel('vouchers')->info('Gateway request prepared', [
            'voucher_id' => $voucher->id,
            'amount_cents' => $voucher->amount * 100,
            'currency' => $voucher->currency,
            'success_url' => $successUrl
        ]);

        return $gateway;
    }

    /**
     * Crear cliente de Payrexx
     */
    private function createPayrexxClient(School $school): Payrexx
    {
        $apiBaseDomain = config('services.payrexx.base_domain', Communicator::API_URL_BASE_DOMAIN);

        return new Payrexx(
            $school->getPayrexxInstance(),
            $school->getPayrexxKey(),
            '',
            $apiBaseDomain
        );
    }

    /**
     * Cancelar gift voucher (si no ha sido pagado aún)
     */
    public function cancelVoucher(int $voucherId): bool
    {
        $voucher = GiftVoucher::find($voucherId);

        if (!$voucher) {
            return false;
        }

        // Solo se puede cancelar si está en pending
        if ($voucher->status !== 'pending') {
            Log::channel('vouchers')->warning('Cannot cancel gift voucher - not in pending status', [
                'voucher_id' => $voucherId,
                'current_status' => $voucher->status
            ]);
            return false;
        }

        $voucher->status = 'cancelled';
        $voucher->save();

        Log::channel('vouchers')->info('Gift voucher cancelled', ['voucher_id' => $voucherId]);

        return true;
    }
}

