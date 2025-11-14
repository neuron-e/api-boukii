<?php

namespace App\Services\Payrexx;

use App\Models\Booking;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Payrexx\Payrexx;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\Models\Request\Transaction as TransactionRequest;
use Payrexx\PayrexxException;

/**
 * Servicio de Payrexx - REFACTORIZADO
 *
 * Responsabilidades:
 * - Gestionar gateways de pago de Payrexx
 * - Crear enlaces de pago
 * - Recuperar transacciones
 * - Analizar consistencia de pagos
 */
class PayrexxService
{
    /**
     * Crear un enlace de gateway de Payrexx
     */
    public function createGatewayLink(School $schoolData, Booking $bookingData, float $totalAmount, ?string $redirectTo = null): string
    {
        if (empty($schoolData->getPayrexxInstance()) || empty($schoolData->getPayrexxKey())) {
            Log::warning('Configuración de Payrexx incompleta', [
                'school_id' => $schoolData->id,
                'has_instance' => !empty($schoolData->getPayrexxInstance()),
                'has_key' => !empty($schoolData->getPayrexxKey())
            ]);
            return '';
        }

        try {
            $gateway = $this->prepareGateway($bookingData, $totalAmount, $redirectTo);
            $payrexx = $this->createPayrexxClient($schoolData);
            $createdGateway = $payrexx->create($gateway);

            if ($createdGateway) {
                Log::channel('payrexx')->info('Gateway creado exitosamente', [
                    'booking_id' => $bookingData->id,
                    'amount' => $totalAmount
                ]);
                return $createdGateway->getLink();
            }

            Log::channel('payrexx')->warning('Gateway creado pero sin enlace', [
                'booking_id' => $bookingData->id
            ]);
            return '';

        } catch (\Exception $e) {
            Log::channel('payrexx')->error('Error creando gateway de Payrexx', [
                'booking_id' => $bookingData->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return '';
        }
    }

    /**
     * Recuperar una transacción de Payrexx
     */
    public function retrieveTransaction(string $payrexxInstance, string $payrexxKey, int $transactionId): ?object
    {
        try {
            $transactionRequest = new TransactionRequest();
            $transactionRequest->setId($transactionId);

            $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
            $payrexx = new Payrexx(
                $payrexxInstance,
                $payrexxKey,
                '',
                $apiBaseDomain
            );

            $transaction = $payrexx->getOne($transactionRequest);

            Log::channel('payrexx')->info('Transacción recuperada exitosamente', [
                'transaction_id' => $transactionId
            ]);

            return $transaction;

        } catch (PayrexxException $e) {
            Log::channel('payrexx')->error('Error recuperando transacción de Payrexx', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crear enlace de pago directo de Payrexx
     */
    public function createDirectPayLink(School $schoolData, Booking $bookingData, float $totalAmount): string
    {
        // Similar a createGatewayLink pero para pagos directos
        // Implementar según documentación de Payrexx para paylinks
        Log::info('Creación de paylink directo solicitada', [
            'school_id' => $schoolData->id,
            'booking_id' => $bookingData->id,
            'amount' => $totalAmount
        ]);

        // Placeholder - implementar según especificaciones de Payrexx paylinks
        return '';
    }

    /**
     * Preparar configuración del gateway
     */
    private function prepareGateway(Booking $bookingData, float $totalAmount, ?string $redirectTo): GatewayRequest
    {
        $gateway = new GatewayRequest();

        // Configuración básica
        $gateway->setAmount($totalAmount * 100); // Payrexx usa centavos
        $gateway->setCurrency('EUR');
        $gateway->setSuccessRedirectUrl($redirectTo . '?status=success');
        $gateway->setFailedRedirectUrl($redirectTo . '?status=failed');
        $gateway->setCancelRedirectUrl($redirectTo . '?status=cancel');

        // Configurar referencia
        $gateway->setReferenceId($bookingData->id);

        // Configurar validez según origen
        if ($bookingData->source === 'web') {
            $gateway->setValidity(15); // 15 minutos para web
        } else {
            $gateway->setValidity(60); // 60 minutos para otros orígenes
        }

        // Configurar información del cliente si está disponible
        if ($bookingData->clientMain) {
            $customerFields = [
                'email' => $bookingData->clientMain->email,
                'forename' => $bookingData->clientMain->name,
                'surname' => $bookingData->clientMain->surname ?? ''
            ];

            if (method_exists($gateway, 'setFields')) {
                $gateway->setFields($customerFields);
            } elseif (method_exists($gateway, 'addField')) {
                foreach ($customerFields as $key => $value) {
                    $gateway->addField($key, $value);
                }
            }
        }

        Log::channel('payrexx')->info('Gateway preparado', [
            'booking_id' => $bookingData->id,
            'amount_cents' => $totalAmount * 100,
            'currency' => 'EUR',
            'validity_minutes' => $bookingData->source === 'web' ? 15 : 60
        ]);

        return $gateway;
    }

    /**
     * Crear cliente de Payrexx
     */
    private function createPayrexxClient(School $schoolData): ?Payrexx
    {
        try {
            $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');

            return new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                $apiBaseDomain
            );
        } catch (PayrexxException $e) {
            return null;
        }
    }
}
