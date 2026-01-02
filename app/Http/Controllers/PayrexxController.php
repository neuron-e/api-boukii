<?php

namespace App\Http\Controllers;

use App\Mail\GiftVoucherDeliveredMail;
use App\Models\Booking;
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
            if ($data && is_array($data) && isset($data['status']) &&
                $data['status'] === TransactionResponse::CONFIRMED) {
                // 2. Pick related Booking from our database:
                // we sent its ReferenceID when the payment was requested
                $referenceID = $data['invoice']['paymentLink']['referenceId']
                    ?? $data['invoice']['referenceId']
                    ?? $data['referenceId']
                    ?? $data['invoice']['number']
                    ?? '';

                $referenceID = trim($referenceID);

                Log::channel('webhooks')->debug('ReferenceID: ' . $referenceID );

                $booking = (strlen($referenceID) > 2)
                    ? Booking::withTrashed()->with(['school', 'bookingUsers'])
                        ->where('payrexx_reference', '=', $referenceID)
                        ->first()
                    : null;

                if ($booking) {

                    // Continue if still unpaid and user chose Payrexx (i.e. BoukiiPay or Online payment methods) - else ignore
                    if (!$booking->paid &&
                        ($booking->payment_method_id == 2 || $booking->payment_method_id == 3)) {
                        // 3. Pick its related School and its Payrexx credentials...
                        $schoolData = $booking->school;
                        if ($schoolData && $schoolData->getPayrexxInstance() && $schoolData->getPayrexxKey()) {
                            // ...to check that it's a legitimate Transaction:
                            // we can't assert that this Notification really came from Payrexx
                            // (or was faked by someone who just did a POST to our URL)
                            // because it has no special signature.
                            // So we just pick its ID and ask Payrexx for the details
                            $transactionID = intval($data['id'] ?? -1);
                            $data2 = PayrexxHelpers::retrieveTransaction(
                                $schoolData->getPayrexxInstance(),
                                $schoolData->getPayrexxKey(),
                                $transactionID
                            );

                            if ($data2 && $data2->getStatus() === TransactionResponse::CONFIRMED) {
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

                                $payment = new Payment();
                                $payment->booking_id = $booking->id;
                                $payment->school_id = $booking->school_id;
                                $payment->amount = ($data2->getInvoice()['totalAmount'] ?? $data['amount']) / 100;
                                $payment->status = 'paid';
                                $payment->notes = 'Boukii Pay';
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
}


