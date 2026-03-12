<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Mail\BlankMailer;
use App\Models\Payment;
use App\Models\RentalEvent;
use App\Models\RentalReservation;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\Payrexx;

/**
 * Rental payment endpoints (Phase 4).
 *
 * Supports three payment flows for rental reservations:
 *   1. Manual (cash / card) — immediate registration, no external call
 *   2. Payrexx link — generates a Payrexx gateway link to send or display
 *   3. Deposit management — hold / release / forfeit the deposit
 *
 * "Cobro conjunto" (joint billing): when a rental_reservation has booking_id set,
 * payment is handled through the booking's payment flow. These endpoints only apply
 * to standalone rentals (booking_id = null) OR when the operator explicitly registers
 * a separate payment for a linked rental.
 */
class RentalPaymentController extends AppBaseController
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /admin/rentals/reservations/{id}/payment
    // Register a manual payment (cash | card) or record a completed payrexx link.
    // ─────────────────────────────────────────────────────────────────────────
    public function store(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,payrexx_link,payrexx_invoice,invoice',
            'notes'          => 'nullable|string|max:500',
            'payrexx_reference' => 'nullable|string|max:255',
            'currency'       => 'nullable|string|max:3',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 422);
        }

        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Rental reservation not found', [], 404);
        }

        $schoolId = $reservation->school_id;
        $amount   = (float) $request->input('amount');
        $method   = $request->input('payment_method');
        $notes    = $request->input('notes', '');

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'booking_id'             => null,
                'rental_reservation_id'  => $id,
                'school_id'              => $schoolId,
                'amount'                 => $amount,
                'status'                 => 'paid',
                'payment_method'         => $method,
                'notes'                  => $notes,
                'payrexx_reference'      => $request->input('payrexx_reference'),
            ]);

            $reservation->update(['payment_id' => $payment->id]);

            // Audit event
            RentalEvent::log($id, $schoolId, 'payment_received', [
                'payment_id'     => $payment->id,
                'amount'         => $amount,
                'payment_method' => $method,
            ]);

            DB::commit();

            Log::channel('payments')->info('RENTAL_PAYMENT_REGISTERED', [
                'reservation_id' => $id,
                'payment_id'     => $payment->id,
                'amount'         => $amount,
                'method'         => $method,
                'user_id'        => Auth::id(),
            ]);

            return $this->sendResponse([
                'payment_id'     => $payment->id,
                'amount'         => $amount,
                'payment_method' => $method,
                'status'         => 'paid',
            ], 'Payment registered successfully');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RENTAL_PAYMENT_STORE_FAILED', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->sendError('Failed to register payment: ' . $e->getMessage(), [], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /admin/rentals/reservations/{id}/paylink
    // Create a Payrexx payment link for this rental reservation.
    // ─────────────────────────────────────────────────────────────────────────
    public function createPaylink(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount'       => 'nullable|numeric|min:0.01',
            'client_email' => 'nullable|email|max:255',
            'send_email'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 422);
        }

        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Rental reservation not found', [], 404);
        }

        $school = $this->getSchoolModel($reservation->school_id);
        if (!$school) {
            return $this->sendError('School not found', [], 404);
        }

        if (empty($school->getPayrexxInstance()) || empty($school->getPayrexxKey())) {
            return $this->sendError('Payrexx is not configured for this school', [], 400);
        }

        $amount    = (float) ($request->input('amount') ?? $reservation->total ?? 0);
        $currency  = $reservation->currency ?? 'CHF';
        $clientEmail = $request->input('client_email');
        $sendEmail = (bool) $request->input('send_email', false);

        try {
            $gateway = new GatewayRequest();
            $gateway->setAmount((int) round($amount * 100));
            $gateway->setCurrency(strtoupper($currency));
            $gateway->setPurpose("Rental #{$id}");
            $gateway->setReferenceId($id);
            $gateway->setValidity(60);
            $gateway->setSuccessRedirectUrl(config('app.frontend_url') . '/rentals?status=success&reservation=' . $id);
            $gateway->setCancelRedirectUrl(config('app.frontend_url') . '/rentals?status=cancel&reservation=' . $id);
            $gateway->setFailedRedirectUrl(config('app.frontend_url') . '/rentals?status=failed&reservation=' . $id);

            $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
            $payrexx = new Payrexx(
                $school->getPayrexxInstance(),
                $school->getPayrexxKey(),
                '',
                $apiBaseDomain
            );

            $createdGateway = $payrexx->create($gateway);
            if (!$createdGateway || !$createdGateway->getLink()) {
                return $this->sendError('Failed to generate Payrexx payment link', [], 500);
            }

            $paymentLink = $createdGateway->getLink();

            Log::channel('payments')->info('RENTAL_PAYLINK_CREATED', [
                'reservation_id' => $id,
                'amount'         => $amount,
                'link'           => $paymentLink,
                'user_id'        => Auth::id(),
            ]);

            // Optionally email the link to the client
            if ($sendEmail && !empty($clientEmail)) {
                $this->emailPaylink($school, $id, $amount, $currency, $paymentLink, $clientEmail);
            }

            return $this->sendResponse([
                'payment_link' => $paymentLink,
                'amount'       => $amount,
                'currency'     => $currency,
            ], 'Payment link created');

        } catch (\Throwable $e) {
            Log::error('RENTAL_PAYLINK_FAILED', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->sendError('Failed to create payment link: ' . $e->getMessage(), [], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /admin/rentals/reservations/{id}/deposit
    // Hold (charge), release (refund) or forfeit the deposit.
    //
    // hold   → creates a real Payment record (same methods as rental payment).
    //          If payment_method=payrexx_link, returns a Payrexx link.
    // release → refunds the deposit payment (Payrexx if applicable, else manual).
    // forfeit → marks deposit as kept; the payment record stays paid.
    // ─────────────────────────────────────────────────────────────────────────
    public function manageDeposit(int $id, Request $request): JsonResponse
    {
        $action = $request->input('action');

        $rules = [
            'action'         => 'required|in:hold,release,forfeit',
            'amount'         => $action === 'hold' ? 'required|numeric|min:0.01' : 'nullable|numeric|min:0',
            'payment_method' => $action === 'hold' ? 'required|in:cash,card,payrexx_link,payrexx_invoice,invoice' : 'nullable|string',
            'notes'          => 'nullable|string|max:500',
            'client_email'   => 'nullable|email|max:255',
            'send_email'     => 'nullable|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 422);
        }

        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Rental reservation not found', [], 404);
        }

        if (!Schema::hasColumn('rental_reservations', 'deposit_status')) {
            return $this->sendError('Deposit management is not available (schema outdated)', [], 400);
        }

        return match ($action) {
            'hold'    => $this->depositHold($id, $reservation, $request),
            'release' => $this->depositRelease($id, $reservation, $request),
            'forfeit' => $this->depositForfeit($id, $reservation, $request),
        };
    }

    private function depositHold(int $id, object $reservation, Request $request): JsonResponse
    {
        $amount        = (float) $request->input('amount');
        $method        = $request->input('payment_method');
        $notes         = $request->input('notes', '');
        $clientEmail   = $request->input('client_email');
        $sendEmail     = (bool) $request->input('send_email', false);

        DB::beginTransaction();
        try {
            $paymentStatus  = 'paid';
            $paymentLink    = null;
            $payrexxRef     = null;

            // Payrexx link: generate link, payment stays pending until callback
            if ($method === 'payrexx_link') {
                $school = $this->getSchoolModel($reservation->school_id);
                if (!$school || empty($school->getPayrexxInstance()) || empty($school->getPayrexxKey())) {
                    DB::rollBack();
                    return $this->sendError('Payrexx is not configured for this school', [], 400);
                }

                $currency = $reservation->currency ?? 'CHF';
                $gateway  = new GatewayRequest();
                $gateway->setAmount((int) round($amount * 100));
                $gateway->setCurrency(strtoupper($currency));
                $gateway->setPurpose("Deposit Rental #{$id}");
                $gateway->setReferenceId($id);
                $gateway->setValidity(60);
                $gateway->setSuccessRedirectUrl(config('app.frontend_url') . '/rentals?status=deposit_success&reservation=' . $id);
                $gateway->setCancelRedirectUrl(config('app.frontend_url') . '/rentals?status=deposit_cancel&reservation=' . $id);
                $gateway->setFailedRedirectUrl(config('app.frontend_url') . '/rentals?status=deposit_failed&reservation=' . $id);

                $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
                $payrexx       = new Payrexx($school->getPayrexxInstance(), $school->getPayrexxKey(), '', $apiBaseDomain);
                $created       = $payrexx->create($gateway);

                if (!$created || !$created->getLink()) {
                    DB::rollBack();
                    return $this->sendError('Failed to generate Payrexx deposit link', [], 500);
                }

                $paymentLink   = $created->getLink();
                $payrexxRef    = $created->getId();
                $paymentStatus = 'pending';

                if ($sendEmail && !empty($clientEmail)) {
                    $this->emailPaylink($school, $id, $amount, $currency, $paymentLink, $clientEmail);
                }
            }

            // Create the deposit payment record
            $payment = Payment::create([
                'rental_reservation_id' => $id,
                'school_id'             => $reservation->school_id,
                'booking_id'            => null,
                'amount'                => $amount,
                'status'                => $paymentStatus,
                'payment_method'        => $method,
                'payment_type'          => 'deposit',
                'notes'                 => $notes,
                'payrexx_reference'     => $payrexxRef,
            ]);

            $reservation->update([
                'deposit_amount'     => $amount,
                'deposit_status'     => 'held',
                'deposit_payment_id' => $payment->id,
            ]);

            RentalEvent::log($id, $reservation->school_id, 'deposit_hold', [
                'payment_id'     => $payment->id,
                'amount'         => $amount,
                'payment_method' => $method,
            ]);

            DB::commit();

            Log::channel('payments')->info('RENTAL_DEPOSIT_HELD', [
                'reservation_id' => $id,
                'payment_id'     => $payment->id,
                'amount'         => $amount,
                'method'         => $method,
                'user_id'        => Auth::id(),
            ]);

            return $this->sendResponse([
                'deposit_status'     => 'held',
                'deposit_amount'     => $amount,
                'deposit_payment_id' => $payment->id,
                'payment_method'     => $method,
                'payment_status'     => $paymentStatus,
                'payment_link'       => $paymentLink,
            ], 'Deposit held successfully');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RENTAL_DEPOSIT_HOLD_FAILED', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->sendError('Failed to hold deposit: ' . $e->getMessage(), [], 500);
        }
    }

    private function depositRelease(int $id, object $reservation, Request $request): JsonResponse
    {
        $depositPaymentId = $reservation->deposit_payment_id ?? null;
        $payrexxRefunded  = false;
        $payrexxError     = null;

        DB::beginTransaction();
        try {
            if ($depositPaymentId) {
                $payment = Payment::find($depositPaymentId);

                if ($payment && !empty($payment->payrexx_reference)) {
                    try {
                        $school = $this->getSchoolModel($reservation->school_id);
                        if ($school && !empty($school->getPayrexxInstance()) && !empty($school->getPayrexxKey())) {
                            $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
                            $payrexx       = new Payrexx($school->getPayrexxInstance(), $school->getPayrexxKey(), '', $apiBaseDomain);
                            $gatewayReq    = new GatewayRequest();
                            $gatewayReq->setId((int) $payment->payrexx_reference);
                            $payrexx->delete($gatewayReq);
                            $payrexxRefunded = true;
                        }
                    } catch (\Throwable $e) {
                        $payrexxError = $e->getMessage();
                        Log::warning('RENTAL_DEPOSIT_PAYREXX_REFUND_FAILED', [
                            'reservation_id' => $id,
                            'payment_id'     => $depositPaymentId,
                            'error'          => $payrexxError,
                        ]);
                    }
                }

                if ($payment) {
                    $payment->status = 'refunded';
                    $payment->notes  = trim(($payment->notes ?? '') . "\nDeposit released: " . now()->toDateTimeString());
                    $payment->save();
                }
            }

            $reservation->update(['deposit_status' => 'released']);

            RentalEvent::log($id, $reservation->school_id, 'deposit_release', [
                'deposit_payment_id' => $depositPaymentId,
                'payrexx_refunded'   => $payrexxRefunded,
                'payrexx_error'      => $payrexxError,
                'notes'              => $request->input('notes'),
            ]);

            DB::commit();

            return $this->sendResponse([
                'deposit_status'       => 'released',
                'payrexx_refunded'     => $payrexxRefunded,
                'manual_action_needed' => $depositPaymentId && !$payrexxRefunded && !empty($payrexxError),
            ], 'Deposit released' . ($payrexxRefunded ? ' and Payrexx refund processed.' : '.'));

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RENTAL_DEPOSIT_RELEASE_FAILED', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->sendError('Failed to release deposit: ' . $e->getMessage(), [], 500);
        }
    }

    private function depositForfeit(int $id, object $reservation, Request $request): JsonResponse
    {
        // Forfeit = keep the deposit money. Payment record stays 'paid'.
        $reservation->update(['deposit_status' => 'forfeited']);

        RentalEvent::log($id, $reservation->school_id, 'deposit_forfeit', [
            'deposit_payment_id' => $reservation->deposit_payment_id ?? null,
            'notes'              => $request->input('notes'),
        ]);

        Log::channel('payments')->info('RENTAL_DEPOSIT_FORFEITED', [
            'reservation_id' => $id,
            'user_id'        => Auth::id(),
        ]);

        return $this->sendResponse([
            'deposit_status' => 'forfeited',
        ], 'Deposit forfeited successfully');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /admin/rentals/reservations/{id}/refund
    // Attempt a Payrexx refund; always marks the local payment as 'refunded'.
    // ─────────────────────────────────────────────────────────────────────────
    public function refund(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0.01',
            'refund_method' => 'nullable|in:cash,card,payrexx,voucher',
            'notes' => 'nullable|string|max:500',
            'voucher_name' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray(), 422);
        }

        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Rental reservation not found', [], 404);
        }

        if (empty($reservation->payment_id)) {
            return $this->sendError('No payment recorded for this reservation', [], 400);
        }

        $payment = Payment::find($reservation->payment_id);
        if (!$payment) {
            return $this->sendError('Payment record not found', [], 404);
        }

        $paidTotal = (float) Payment::query()
            ->where('rental_reservation_id', $id)
            ->whereNull('deleted_at')
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('payment_type')
                    ->orWhereNotIn('payment_type', ['deposit', 'refund']);
            })
            ->sum('amount');

        $alreadyRefunded = (float) Payment::query()
            ->where('rental_reservation_id', $id)
            ->whereNull('deleted_at')
            ->where('payment_type', 'refund')
            ->sum('amount');

        $maxRefundable = max(0, $paidTotal - $alreadyRefunded);
        $requestedAmount = (float) ($request->input('amount') ?? 0);
        $refundAmount = $requestedAmount > 0 ? $requestedAmount : min((float) ($payment->amount ?? 0), $maxRefundable);
        if ($refundAmount <= 0) {
            return $this->sendError('No refundable amount available', [], 422);
        }
        if ($refundAmount > $maxRefundable) {
            return $this->sendError('Refund amount exceeds refundable balance', [
                'max_refundable' => round($maxRefundable, 2),
            ], 422);
        }

        $refundMethod = (string) ($request->input('refund_method') ?: (!empty($payment->payrexx_reference) ? 'payrexx' : 'cash'));
        $notes = trim((string) $request->input('notes', ''));
        $voucherId = null;

        $payrexxRefunded = false;
        $payrexxError    = null;

        // Attempt Payrexx gateway refund if we have a reference
        if ($refundMethod === 'payrexx' && !empty($payment->payrexx_reference)) {
            try {
                $school = $this->getSchoolModel($reservation->school_id);
                if ($school && !empty($school->getPayrexxInstance()) && !empty($school->getPayrexxKey())) {
                    $apiBaseDomain = config('services.payrexx.base_domain', 'pay.boukii.com');
                    $payrexx = new Payrexx(
                        $school->getPayrexxInstance(),
                        $school->getPayrexxKey(),
                        '',
                        $apiBaseDomain
                    );

                    // Payrexx refund: delete the gateway by reference
                    $gatewayReq = new GatewayRequest();
                    $gatewayReq->setId((int) $payment->payrexx_reference);
                    $payrexx->delete($gatewayReq);
                    $payrexxRefunded = true;
                }
            } catch (\Throwable $e) {
                $payrexxError = $e->getMessage();
                Log::warning('RENTAL_PAYREXX_REFUND_FAILED', [
                    'reservation_id'   => $id,
                    'payment_id'       => $payment->id,
                    'payrexx_reference' => $payment->payrexx_reference,
                    'error'            => $payrexxError,
                ]);
            }
        } elseif ($refundMethod === 'payrexx' && empty($payment->payrexx_reference)) {
            return $this->sendError('Original payment has no Payrexx reference', [], 422);
        }

        if ($refundMethod === 'voucher') {
            $voucher = Voucher::create([
                'code' => $this->generateVoucherCode(),
                'name' => $request->input('voucher_name') ?: ('Rental credit #' . $id),
                'description' => 'Voucher generated from rental refund',
                'quantity' => $refundAmount,
                'remaining_balance' => $refundAmount,
                'payed' => true,
                'is_gift' => false,
                'is_transferable' => true,
                'client_id' => $reservation->client_id ?: null,
                'buyer_name' => $reservation->client_name,
                'buyer_email' => $reservation->email,
                'buyer_phone' => $reservation->phone,
                'school_id' => $reservation->school_id,
                'origin_type' => 'refund_credit',
                'notes' => 'Created from rental reservation refund #' . $id,
            ]);
            $voucherId = (int) $voucher->id;
        }

        $refundPayment = Payment::create([
            'booking_id' => null,
            'rental_reservation_id' => $id,
            'school_id' => $reservation->school_id,
            'amount' => $refundAmount,
            'status' => 'refunded',
            'payment_method' => $refundMethod,
            'payment_type' => 'refund',
            'notes' => trim(($notes ? $notes . "\n" : '') . 'Refund generated from payment #' . $payment->id . ($voucherId ? (' · voucher #' . $voucherId) : '')),
            'payrexx_reference' => $refundMethod === 'payrexx' ? ($payment->payrexx_reference ?? null) : null,
        ]);

        RentalEvent::log($id, $reservation->school_id, 'refunded', [
            'payment_id'       => $payment->id,
            'refund_payment_id' => $refundPayment->id,
            'amount'           => $refundAmount,
            'refund_method'    => $refundMethod,
            'voucher_id'       => $voucherId,
            'payrexx_refunded' => $payrexxRefunded,
            'payrexx_error'    => $payrexxError,
        ]);

        return $this->sendResponse([
            'payment_id'       => $payment->id,
            'refund_payment_id' => $refundPayment->id,
            'status'           => 'refunded',
            'amount'           => round($refundAmount, 2),
            'refund_method'    => $refundMethod,
            'voucher_id'       => $voucherId,
            'payrexx_refunded' => $payrexxRefunded,
            'manual_action_needed' => $refundMethod === 'payrexx' && !$payrexxRefunded,
            'message' => $payrexxRefunded
                ? 'Payrexx refund processed successfully.'
                : ($refundMethod === 'payrexx'
                    ? 'Refund registered locally. Manual refund may be required in Payrexx.'
                    : ($refundMethod === 'voucher'
                        ? 'Refund converted to voucher successfully.'
                        : 'Refund registered successfully.')
                ),
        ], 'Refund processed');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /admin/rentals/reservations/{id}/payment
    // Return current payment info for a reservation.
    // ─────────────────────────────────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $reservation = RentalReservation::find($id);
        if (!$reservation) {
            return $this->sendError('Rental reservation not found', [], 404);
        }

        $cols = ['id', 'amount', 'status', 'payment_method', 'payment_type', 'notes', 'payrexx_reference', 'created_at'];

        $payment        = $reservation->payment_id        ? Payment::select($cols)->find($reservation->payment_id)        : null;
        $depositPayment = $reservation->deposit_payment_id ? Payment::select($cols)->find($reservation->deposit_payment_id) : null;

        $refunds = Payment::select($cols)
            ->where('rental_reservation_id', $id)
            ->where('payment_type', 'refund')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        return $this->sendResponse([
            'reservation_id'     => $id,
            'total'              => $reservation->total,
            'deposit_amount'     => $reservation->deposit_amount ?? 0,
            'deposit_status'     => $reservation->deposit_status ?? 'none',
            'deposit_payment_id' => $reservation->deposit_payment_id,
            'payment'            => $payment,
            'deposit_payment'    => $depositPayment,
            'refunds'            => $refunds,
        ], 'Payment info retrieved');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getSchoolModel(int $schoolId): ?\App\Models\School
    {
        return \App\Models\School::find($schoolId);
    }

    private function emailPaylink(\App\Models\School $school, int $reservationId, float $amount, string $currency, string $link, string $email): void
    {
        try {
            $subject = "Payment link — Rental #{$reservationId}";
            $body = "Amount: <strong>" . number_format($amount, 2) . " {$currency}</strong><br><br>"
                  . "<a href=\"" . e($link) . "\">" . e($link) . "</a>";

            $mailer = new BlankMailer($subject, $body, [$email], [], $school);
            dispatch(function () use ($mailer) {
                Mail::send($mailer);
            })->afterResponse();

            Log::channel('payments')->info('RENTAL_PAYLINK_EMAILED', [
                'reservation_id' => $reservationId,
                'email'          => $email,
            ]);
        } catch (\Throwable $e) {
            Log::warning('RENTAL_PAYLINK_EMAIL_FAILED', [
                'reservation_id' => $reservationId,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    private function generateVoucherCode(): string
    {
        do {
            $code = 'RVF-' . Str::upper(Str::random(8));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
