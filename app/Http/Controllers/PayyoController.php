<?php

namespace App\Http\Controllers;

use App\Mail\BookingCreateMailer;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Voucher;

/**
 * Handle Payyo webhooks.
 *
 * This controller mirrors the logic implemented for Payrexx but
 * utilises the PayyoHelpers service to talk with Payyo's API.
 */
class PayyoController
{
    /**
     * Process the data returned from Payyo after an operation.
     * Currently only transactions with status "confirmed" are relevant.
     *
     * @return \Illuminate\Http\Response
     */
    public function processNotification(Request $request)
    {
        // 0. Log Payyo response
        Log::channel('payyo')->debug('processNotification');
        Log::channel('payyo')->debug(print_r($request->all(), 1));

        // Can be Booking or Voucher
        try {
            // 1. Pick their Transaction data
            $data = $request->transaction;
            if ($data && is_array($data) && isset($data['status']) &&
                $data['status'] === 'confirmed') {
                // 2. Pick related Booking from our database:
                // we sent its ReferenceID when the payment was requested
                $referenceID = trim($data['referenceId'] ?? '');

                $booking = (strlen($referenceID) > 2)
                    ? Booking::withTrashed()->with(['school', 'bookingUsers'])
                        ->where('payyo_reference', '=', $referenceID)
                        ->first()
                    : null;

                if ($booking) {

                    // Continue if still unpaid and user chose Payyo (i.e. BoukiiPay or Online payment methods) - else ignore
                    if (!$booking->paid &&
                        ($booking->payment_method_id == 2 || $booking->payment_method_id == 3)) {
                        // 3. Pick its related School and its Payyo credentials...
                        $schoolData = $booking->school;
                        if ($schoolData && $schoolData->getPayyoInstance() && $schoolData->getPayyoKey()) {
                            // ...to check that it's a legitimate Transaction:
                            // we can't assert that this Notification really came from Payyo
                            // (or was faked by someone who just did a POST to our URL)
                            // because it has no special signature.
                            // So we just pick its ID and ask Payyo for the details
                            $transactionID = intval($data['id'] ?? -1);
                            $data2 = PayyoHelpers::retrieveTransaction(
                                $schoolData->getPayyoInstance(),
                                $schoolData->getPayyoKey(),
                                $transactionID
                            );

                            if ($data2 && ($data2['status'] ?? '') === 'confirmed') {
                                if ($booking->trashed()) {
                                    $booking->restore(); // Restaurar la reserva eliminada
                                    foreach ($booking->bookingUsers as $bookinguser) {
                                        if ($bookinguser->trashed()) {
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
                                    dispatch(function () use ($schoolData, $booking, $buyerUser) {
                                        // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                                        try {
                                            \Mail::to($buyerUser->email)
                                                ->send(new BookingCreateMailer(
                                                    $schoolData,
                                                    $booking,
                                                    $buyerUser,
                                                    true
                                                ));
                                        } catch (\Exception $ex) {
                                            \Illuminate\Support\Facades\Log::debug('BookingController->createBooking BookingCreateMailer: '
                                                . $ex->getMessage());
                                        }
                                    })->afterResponse();
                                }

                                // Everything seems to fit, so mark booking as paid,
                                // storing some Transaction info for future refunds
                                // fallback to $data['amount'] (which might been faked)
                                $booking->paid = true;
                                $booking->setPayyoTransaction([
                                    'id' => $transactionID,
                                    'time' => $data2['time'] ?? time(),
                                    'totalAmount' => $data2['amount'] ?? $data['amount'],
                                    'refundedAmount' => $data2['refundedAmount'] ?? 0,
                                    'currency' => $data2['currency'] ?? $booking->currency,
                                    'brand' => $data2['brand'] ?? '',
                                    'referenceId' => $referenceID
                                ]);

                                $booking->paid_total = $booking->paid_total + ($data2['amount'] ?? $data['amount']) / 100;

                                $payment = new Payment();
                                $payment->booking_id = $booking->id;
                                $payment->school_id = $booking->school_id;
                                $payment->amount = ($data2['amount'] ?? $data['amount']) / 100;
                                $payment->status = 'paid';
                                $payment->notes = 'Boukii Pay';
                                $payment->payyo_reference = $referenceID;
                                $payment->setPayyoTransaction($booking->getPayyoTransaction());
                                $payment->save();

                                $booking->save();
                            }
                        }
                    }

                } else {
                    $voucher = (strlen($referenceID) > 2)
                        ? Voucher::with('school')->where('payyo_reference', '=', $referenceID)->first()
                        : null;

                    if (!$voucher) {
                        throw new \Exception('No Booking or Voucher found with payyo_reference: ' . $referenceID);
                    }

                    if (!$voucher->payed) {
                        $schoolData = $voucher->school;
                        if ($schoolData && $schoolData->getPayyoInstance() && $schoolData->getPayyoKey()) {
                            $transactionID = intval($data['id'] ?? -1);
                            $data2 = PayyoHelpers::retrieveTransaction(
                                $schoolData->getPayyoInstance(),
                                $schoolData->getPayyoKey(),
                                $transactionID
                            );

                            if ($data2 && ($data2['status'] ?? '') === 'confirmed') {

                                $buyerUser = User::find($booking->client_main_id);
                                if ($booking->payment_method_id == 2 && $booking->source == 'web') {
                                    // As of 2022-10-25 tell buyer user by email at this point, even before payment, and continue
                                    dispatch(function () use ($schoolData, $booking, $buyerUser) {
                                        // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                                        try {
                                            \Mail::to($buyerUser->email)
                                                ->send(new BookingCreateMailer(
                                                    $schoolData,
                                                    $booking,
                                                    $buyerUser,
                                                    true
                                                ));
                                        } catch (\Exception $ex) {
                                            \Illuminate\Support\Facades\Log::debug('PayyoController->processNotification BookingCreateMailer: '
                                                . $ex->getMessage());
                                        }
                                    })->afterResponse();
                                }

                                $voucher->payed = true;
                                $voucher->setPayyoTransaction([
                                    'id' => $transactionID,
                                    'time' => $data2['time'] ?? time(),
                                    'totalAmount' => $data2['amount'] ?? $data['amount'],
                                    'refundedAmount' => $data2['refundedAmount'] ?? 0,
                                    'currency' => $data2['currency'] ?? $booking->currency,
                                    'brand' => $data2['brand'] ?? '',
                                    'referenceId' => $referenceID
                                ]);

                                $voucher->save();
                            }
                        }
                    }
                }

            }
        } catch (\Exception $e) {
            Log::channel('payyo')->error('processNotification');
            Log::channel('payyo')->error($e->getMessage());
        }

        return response()->make('OK');
    }
}

