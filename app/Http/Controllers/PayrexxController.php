<?php

namespace App\Http\Controllers;

use App\Mail\GiftVoucherDeliveredMail;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\Client;
use App\Models\GiftVoucher;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use Payrexx\Models\Response\Transaction as TransactionResponse;
use App\Models\Voucher;


class PayrexxController
{
    /**
     * Process the data returned from Payrexx after an operation
     * As of 2022-10, we're only interested in Transactions with status=confirmed
     * (i.e. not refunds)
     *
     * @link https://developers.payrexx.com/docs/transaction
     *
     * @return an empty page with "OK"
     */
    public function processNotification(Request $request)
    {
        // 0. Log Payrexx response
        Log::channel('webhooks')->debug('processNotification');
        Log::channel('webhooks')->debug(print_r($request->all(), 1));

        // Can be Booking, Voucher or GiftVoucher
        try {
            // 1. Pick their Transaction data
            $data = $request->transaction;
            if ($data && is_array($data) && isset($data['status'])) {
                // 2. Pick related Booking from our database:
                // we sent its ReferenceID when the payment was requested
                $referenceID = $data['invoice']['paymentLink']['referenceId']
                    ?? $data['invoice']['referenceId']
                    ?? $data['referenceId']
                    ?? $data['invoice']['number']
                    ?? '';

                $referenceID = trim($referenceID);
                $statusRaw = (string) ($data['status'] ?? '');
                $statusNormalized = strtolower($statusRaw);
                $transactionID = intval($data['id'] ?? -1);

                Log::channel('webhooks')->debug('ReferenceID: ' . $referenceID );

                $booking = (strlen($referenceID) > 2)
                    ? Booking::withTrashed()->with(['school', 'bookingUsers'])
                        ->where('payrexx_reference', '=', $referenceID)
                        ->first()
                    : null;

                if ($booking) {
                    if ($statusRaw !== TransactionResponse::CONFIRMED) {
                        $this->logPayrexxBookingStatus($booking, $referenceID, $transactionID, $statusNormalized);
                        if ($this->shouldCreatePaymentLog($statusNormalized)) {
                            $this->createPayrexxPaymentLog($booking, $referenceID, $statusNormalized, $data);
                        }
                        return response()->json(['status' => 'received', 'type' => 'booking']);
                    }

                    // Continue if still unpaid and user chose Payrexx (i.e. BoukiiPay or Online payment methods) - else ignore
                    if (!$booking->paid &&
                        ($booking->payment_method_id == 2 || $booking->payment_method_id == 3 || $booking->payment_method_id == Booking::ID_INVOICE)) {
                        // 3. Pick its related School and its Payrexx credentials...
                        $schoolData = $booking->school;
                        if ($schoolData && $schoolData->getPayrexxInstance() && $schoolData->getPayrexxKey()) {
                            // ...to check that it's a legitimate Transaction:
                            // we can't assert that this Notification really came from Payrexx
                            // (or was faked by someone who just did a POST to our URL)
                            // because it has no special signature.
                            // So we just pick its ID and ask Payrexx for the details
                            $data2 = PayrexxHelpers::retrieveTransaction(
                                $schoolData->getPayrexxInstance(),
                                $schoolData->getPayrexxKey(),
                                $transactionID
                            );

                            if ($data2 && $data2->getStatus() === TransactionResponse::CONFIRMED) {
                                if ($this->isInvoiceTransaction($data, $data2) && (int) $booking->payment_method_id !== Booking::ID_INVOICE) {
                                    $booking->payment_method_id = Booking::ID_INVOICE;
                                }
                                if ($booking->trashed()) {
                                    $booking->restore(); // Restaurar la reserva eliminada
                                    foreach($booking->bookingUsers as $bookinguser){
                                        if($bookinguser->trashed()) {
                                            $bookinguser->restore();
                                        }
                                    }
                                }
                                $buyerUser = Client::find($booking->client_main_id);
                                if ($booking->payment_method_id == 2 && $booking->source == 'web') {
                                    $pendingVouchers = $booking->vouchersLogs()->where('status', 'pending')->orderBy('created_at', 'desc')->get();

                                    if ($pendingVouchers->isNotEmpty()) {
                                        // Toma el último log pendiente
                                        $lastVoucherLog = $pendingVouchers->first();

                                        // Encuentra el voucher asociado al último log
                                        $voucher = Voucher::find($lastVoucherLog->voucher_id);

                                        if ($voucher) {
                                            // Resta el amount del último log al remaining_balance del voucher
                                            $voucher->remaining_balance -= abs($lastVoucherLog->amount);
                                            $voucher->save();

                                            // Actualiza el estado del último log a 'confirmed'
                                            $lastVoucherLog->status = null;
                                            $lastVoucherLog->save();
                                        }

                                        // Elimina los demás logs pendientes
                                        $otherLogs = $pendingVouchers->slice(1); // Excluye el primer log
                                        foreach ($otherLogs as $log) {
                                            $log->delete();
                                        }
                                    }
                                    // As of 2022-10-25 tell buyer user by email at this point, even before payment, and continue
                                    app(BookingConfirmationService::class)
                                        ->sendConfirmation($booking, true);
                                }

                                // Everything seems to fit, so mark booking as paid,
                                // storing some Transaction info for future refunds
                                // N.B: as of 2022-10-08 field $data2->invoice->totalAmount is null
                                // (at least on Test mode)
                                // fallback to $data->amount
                                // (which might been faked)
                                $booking->paid = true;
                                $booking->setPayrexxTransaction([
                                    'id' => $transactionID,
                                    'time' => $data2->getTime(),
                                    'totalAmount' => $data2->getInvoice()['totalAmount'] ?? $data['amount'],
                                    'refundedAmount' => $data2->getInvoice()['refundedAmount'] ?? 0,
                                    'currency' => $data2->getInvoice()['currencyAlpha3'],
                                    'brand' => $data2->getPayment()['brand'],
                                    'referenceId' => $referenceID
                                ]);

                                $booking->paid_total = $booking->paid_total +
                                    ($data2->getInvoice()['totalAmount'] ?? $data['amount']) / 100;

                                $isInvoicePayment = (int) $booking->payment_method_id === Booking::ID_INVOICE;
                                $payment = new Payment();
                                $payment->booking_id = $booking->id;
                                $payment->school_id = $booking->school_id;
                                $payment->amount = ($data2->getInvoice()['totalAmount'] ?? $data['amount']) / 100;
                                $payment->status = $isInvoicePayment ? 'invoice_paid' : 'paid';
                                $payment->notes = $isInvoicePayment ? 'Invoice paid via Payrexx' : 'Boukii Pay';
                                $payment->payrexx_reference = $referenceID;
                                $payment->payrexx_transaction = $booking->payrexx_transaction;
                                $payment->save();

                                $booking->save();
                            } else {
                                Log::channel('webhooks')->warning('Payrexx webhook transaction could not be verified for booking', [
                                    'booking_id' => $booking->id,
                                    'reference' => $referenceID,
                                    'transaction_id' => $transactionID,
                                    'school_id' => $schoolData->id ?? null,
                                ]);
                            }
                        }
                    }

                } else {
                    if ($statusRaw !== TransactionResponse::CONFIRMED) {
                        Log::channel('webhooks')->info('Payrexx webhook ignored (non-confirmed, no booking match)', [
                            'reference' => $referenceID,
                            'transaction_id' => $transactionID,
                            'status' => $statusNormalized,
                        ]);
                        return response()->json(['status' => 'ignored']);
                    }
                    // Si no se encontró un booking, buscar primero voucher
                    $voucher = (strlen($referenceID) > 2)
                        ? Voucher::with('school')->where('payrexx_reference', '=', $referenceID)->first()
                        : null;

                    if ($voucher) {
                        // Procesar Voucher legacy
                        if (!$voucher->payed) {
                        $schoolData = $voucher->school;
                        if ($schoolData && $schoolData->getPayrexxInstance() && $schoolData->getPayrexxKey()) {
                            $transactionID = intval($data['id'] ?? -1);
                            $data2 = PayrexxHelpers::retrieveTransaction(
                                $schoolData->getPayrexxInstance(),
                                $schoolData->getPayrexxKey(),
                                $transactionID
                            );

                            if ($data2 && $data2->getStatus() === TransactionResponse::CONFIRMED) {

                                $buyerUser = User::find($booking->client_main_id);
                                if ($booking->payment_method_id == 2 && $booking->source == 'web') {
                                    // As of 2022-10-25 tell buyer user by email at this point, even before payment, and continue
                                    app(BookingConfirmationService::class)
                                        ->sendConfirmation($booking, true);
                                }

                                $voucher->payed = true;
                                $voucher->setPayrexxTransaction([
                                    'id' => $transactionID,
                                    'time' => $data2->getTime(),
                                    'totalAmount' => $data2->getInvoice()['totalAmount'] ?? $data['amount'],
                                    'refundedAmount' => $data2->getInvoice()['refundedAmount'] ?? 0,
                                    'currency' => $data2->getInvoice()['currencyAlpha3'],
                                    'brand' => $data2->getPayment()['brand'],
                                    'referenceId' => $referenceID
                                ]);

                                $voucher->save();
                            } else {
                                Log::channel('webhooks')->warning('Payrexx webhook transaction could not be verified for voucher', [
                                    'voucher_id' => $voucher->id,
                                    'reference' => $referenceID,
                                    'transaction_id' => $transactionID,
                                    'school_id' => $schoolData->id ?? null,
                                ]);
                            }
                        }
                    }
                    } else {
                        // Si no se encontró voucher, buscar gift voucher
                        $giftVoucher = (strlen($referenceID) > 2)
                            ? GiftVoucher::where('payrexx_reference', '=', $referenceID)->first()
                            : null;

                        if ($giftVoucher) {
                            // Procesar Gift Voucher
                            if (!$giftVoucher->is_paid) {
                                $schoolData = $giftVoucher->school;
                                if ($schoolData && $schoolData->getPayrexxInstance() && $schoolData->getPayrexxKey()) {
                                    $transactionID = intval($data['id'] ?? -1);
                                    $data2 = PayrexxHelpers::retrieveTransaction(
                                        $schoolData->getPayrexxInstance(),
                                        $schoolData->getPayrexxKey(),
                                        $transactionID
                                    );

                                    if ($data2 && $data2->getStatus() === TransactionResponse::CONFIRMED) {
                                        // Activar el gift voucher
                                        $giftVoucher->update([
                                            'status' => 'active',
                                            'is_paid' => true,
                                            'payment_confirmed_at' => now(),
                                        ]);

                                        // Guardar datos de transacción
                                        $giftVoucher->setPayrexxTransaction([
                                            'id' => $transactionID,
                                            'time' => $data2->getTime(),
                                            'totalAmount' => $data2->getInvoice()['totalAmount'] ?? $data['amount'],
                                            'refundedAmount' => $data2->getInvoice()['refundedAmount'] ?? 0,
                                            'currency' => $data2->getInvoice()['currencyAlpha3'],
                                            'brand' => $data2->getPayment()['brand'],
                                            'referenceId' => $referenceID
                                        ]);
                                        $giftVoucher->save();

                                        // Enviar email al destinatario
                                        try {
                                            $recipientLocale = $giftVoucher->recipient_locale ?? $giftVoucher->buyer_locale ?? config('app.locale', 'en');

                                            Mail::to($giftVoucher->recipient_email)
                                                ->send(new GiftVoucherDeliveredMail($giftVoucher, $schoolData, $recipientLocale));

                                            $giftVoucher->update([
                                                'email_sent_at' => now(),
                                                'is_delivered' => true,
                                                'delivered_at' => now()
                                            ]);

                                            Log::channel('webhooks')->info('Gift Voucher email sent successfully', [
                                                'voucher_id' => $giftVoucher->id,
                                                'code' => $giftVoucher->code,
                                                'recipient' => $giftVoucher->recipient_email
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::channel('webhooks')->error('Failed to send gift voucher email', [
                                                'voucher_id' => $giftVoucher->id,
                                                'error' => $e->getMessage()
                                            ]);
                                        }

                                        Log::channel('webhooks')->info('Gift Voucher activated via Payrexx webhook', [
                                            'voucher_id' => $giftVoucher->id,
                                            'code' => $giftVoucher->code,
                                            'amount' => $giftVoucher->amount
                                        ]);

                                        return response()->json(['status' => 'success', 'type' => 'gift_voucher']);
                                    } else {
                                        Log::channel('webhooks')->warning('Payrexx webhook transaction could not be verified for gift voucher', [
                                            'gift_voucher_id' => $giftVoucher->id,
                                            'reference' => $referenceID,
                                            'transaction_id' => $transactionID,
                                            'school_id' => $schoolData->id ?? null,
                                        ]);
                                    }
                                }
                            }
                        } else {
                            // No se encontró ninguna entidad
                            throw new \Exception('No Booking, Voucher or GiftVoucher found with payrexx_reference: ' . $referenceID);
                        }
                    }
                }

            }
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('processNotification');
            Log::channel('webhooks')->error($e->getMessage());
        }

        return response()->make('OK');
    }

    private function restoreBookingForPayrexx(Booking $booking): void
    {
        if ($booking->trashed()) {
            $booking->restore();
            foreach ($booking->bookingUsers as $bookinguser) {
                if ($bookinguser->trashed()) {
                    $bookinguser->restore();
                }
            }
        }
    }

    private function logPayrexxBookingStatus(Booking $booking, string $referenceID, int $transactionID, string $status): void
    {
        try {
            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'payrexx_status_' . ($status ?: 'unknown'),
                'user_id' => $booking->client_main_id,
            ]);
        } catch (\Exception $e) {
            Log::channel('webhooks')->warning('Payrexx webhook booking log failed', [
                'booking_id' => $booking->id,
                'reference' => $referenceID,
                'transaction_id' => $transactionID,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('webhooks')->info('Payrexx webhook received for booking (non-confirmed)', [
            'booking_id' => $booking->id,
            'reference' => $referenceID,
            'transaction_id' => $transactionID,
            'status' => $status,
            'school_id' => $booking->school_id ?? null,
        ]);
    }

    private function createPayrexxPaymentLog(Booking $booking, string $referenceID, string $status, array $payload): void
    {
        if ($booking->paid) {
            return;
        }

        if (!in_array((int) $booking->payment_method_id, [2, 3, Booking::ID_INVOICE], true)) {
            return;
        }

        // Detect invoice transaction from payload for pending/waiting statuses
        $isInvoice = $this->isInvoiceTransactionFromPayload($payload);

        // If it's an invoice transaction, update the booking's payment method
        if ($isInvoice && (int) $booking->payment_method_id !== Booking::ID_INVOICE) {
            $booking->payment_method_id = Booking::ID_INVOICE;
            $booking->save();

            Log::channel('webhooks')->info('Invoice payment detected, updated payment_method_id', [
                'booking_id' => $booking->id,
                'reference' => $referenceID,
                'status' => $status,
            ]);
        }

        // For invoice transactions, use 'invoice_sent' status instead of generic pending
        $mappedStatus = $isInvoice ? 'invoice_sent' : $this->mapPayrexxStatusToPaymentStatus($status);
        $amount = isset($payload['amount']) ? ((float) $payload['amount']) / 100 : null;

        $existing = Payment::where('booking_id', $booking->id)
            ->where('payrexx_reference', $referenceID)
            ->where('status', $mappedStatus)
            ->when($amount !== null, function ($query) use ($amount) {
                return $query->where('amount', $amount);
            })
            ->latest()
            ->first();

        if ($existing) {
            return;
        }

        $payment = new Payment();
        $payment->booking_id = $booking->id;
        $payment->school_id = $booking->school_id;
        $payment->amount = $amount ?? 0;
        $payment->status = $mappedStatus;
        $payment->notes = $isInvoice ? 'Invoice sent via Payrexx' : ('Payrexx ' . ($status ?: 'unknown'));
        $payment->payrexx_reference = $referenceID;
        $payment->payrexx_transaction = $booking->payrexx_transaction;
        $payment->save();
    }

    private function mapPayrexxStatusToPaymentStatus(string $status): string
    {
        $normalized = strtolower($status);

        if (in_array($normalized, ['waiting', 'authorized', 'pending', 'reserved'], true)) {
            return 'pending';
        }

        if (in_array($normalized, ['cancelled', 'canceled', 'failed', 'expired', 'refunded', 'chargeback'], true)) {
            return $normalized;
        }

        return $normalized ?: 'pending';
    }

    private function shouldCreatePaymentLog(string $status): bool
    {
        return $this->shouldLogPaymentStatus($status);
    }

    private function shouldLogPaymentStatus(string $status): bool
    {
        return in_array($status, ['waiting', 'authorized', 'pending', 'reserved', 'accepted'], true);
    }

    private function isInvoiceTransaction(array $payload, ?TransactionResponse $transaction): bool
    {
        $payment = $payload['payment'] ?? [];
        $candidates = [
            $payment['method'] ?? null,
            $payment['brand'] ?? null,
            $payment['type'] ?? null,
            $payment['name'] ?? null,
        ];

        if ($transaction) {
            $transactionPayment = $transaction->getPayment();
            if (is_array($transactionPayment)) {
                $candidates[] = $transactionPayment['brand'] ?? null;
                $candidates[] = $transactionPayment['method'] ?? null;
                $candidates[] = $transactionPayment['type'] ?? null;
                $candidates[] = $transactionPayment['name'] ?? null;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $value = strtolower((string) $candidate);
            if ($value === '') {
                continue;
            }
            if (str_contains($value, 'invoice') || str_contains($value, 'rechnung') || str_contains($value, 'facture')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect invoice transaction from payload only (for pending/waiting status webhooks)
     */
    private function isInvoiceTransactionFromPayload(array $payload): bool
    {
        $payment = $payload['payment'] ?? [];
        $candidates = [
            $payment['method'] ?? null,
            $payment['brand'] ?? null,
            $payment['type'] ?? null,
            $payment['name'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }
            $value = strtolower((string) $candidate);
            if ($value === '') {
                continue;
            }
            // Invoice, Rechnung (German), Facture (French)
            if (str_contains($value, 'invoice') || str_contains($value, 'rechnung') || str_contains($value, 'facture')) {
                return true;
            }
        }

        return false;
    }
}
