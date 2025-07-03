<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\Client;
use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Mail;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\Models\Request\Invoice as InvoiceRequest;
use Payrexx\Models\Request\Transaction as TransactionRequest;
use Payrexx\Models\Response\Transaction as TransactionResponse;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;

use App\Mail\BookingPayMailer;
use App\Models\Language;
use App\Models\School;


/**
 * Convenient wrapper for Payrexx API
 * @see https://github.com/payrexx/payrexx-php/tree/master/examples
 */
class PayrexxHelpers
{
    /**
     * Prepare a Payrexx Gateway link to start a Transaction.
     * @see https://developers.payrexx.com/reference/create-a-gateway
     *
     * @param School $schoolData i.e. who wants the money
     * @param Booking $bookingData i.e. the Booking ID this payment is for
     * @param Client|null $buyerUser to get his payment & contact details
     * @param string $redirectTo tell Payrexx to redirect back to a certain URL,
     * or "panel" frontend page, or to an special empty screen for "app", or nowhere
     *
     * @return string empty if something failed
     */
    public static function createGatewayLinkNew($schoolData, $bookingData,
                                                $basketData, Client $buyerUser = null, $redirectTo = null)
    {
        $link = '';

        try {

            // Check that School has Payrexx credentials
            //dd($schoolData->getPayrexxInstance());
            if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
                throw new \Exception('No credentials for School ID=' . $schoolData->id);
            }

            // Prepare gateway: basic data
            $gr = new GatewayRequest();
            $gr->setReferenceId($bookingData->getOrGeneratePayrexxReference());
            $gr->setAmount($bookingData->price_total * 100);
            $gr->setCurrency($bookingData->currency);
            $gr->setVatRate($schoolData->bookings_comission_cash);                  // TODO TBD as of 2022-10 all Schools are at Switzerland and there's no VAT ???

            // Add School's legal terms, if set
            if ($schoolData->conditions_url) {
                $gr->addField('terms', $schoolData->conditions_url);
            }

            // Calcular el precio total del "basket"
            $totalAmount = array_reduce($basketData->all(), function($carry, $item) {
                return $carry + $item['amount'];
            }, 0);

            $gr->setBasket($basketData->all());
            $gr->setAmount($totalAmount);

            // Buyer data
            if ($buyerUser) {
                $gr->addField('forename', $buyerUser->first_name);
                $gr->addField('surname', $buyerUser->last_name);
                $gr->addField('phone', $buyerUser->phone);
                $gr->addField('email', $buyerUser->email);
                $gr->addField('street', $buyerUser->address);
                $gr->addField('postcode', $buyerUser->cp);

                $gr->addField('place', $buyerUser->province);
                $gr->addField('country',  $buyerUser->country);
            }

            // OK/error pages to redirect user after payment
            if ($redirectTo == 'panel') {
                $gr->setSuccessRedirectUrl(env('ADMIM_URL') . '/bookings?status=success');
                $gr->setFailedRedirectUrl(env('ADMIM_URL') . '/bookings?status=failed');
                $gr->setCancelRedirectUrl(env('ADMIM_URL') . '/bookings?status=cancel');
            } else if ($redirectTo == 'app') {
                $gr->setSuccessRedirectUrl(route('api.payrexx.finish', ['status' => 'success']));
                $gr->setFailedRedirectUrl(route('api.payrexx.finish', ['status' => 'failed']));
                $gr->setCancelRedirectUrl(route('api.payrexx.finish', ['status' => 'cancel']));
            } else if ($redirectTo != null) {
                $gr->setSuccessRedirectUrl($redirectTo . '?status=success');
                $gr->setFailedRedirectUrl($redirectTo . '?status=failed');
                $gr->setCancelRedirectUrl($redirectTo . '?status=cancel');
            }

            if($bookingData->source == 'web') {
                $gr->setValidity(15);
            }


            // Launch it
            $payrexx = new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );

            $gateway = $payrexx->create($gr);

            if ($gateway) {
                $link = $gateway->getLink();
            }

        } catch (\Exception $e) {
            // Altought not stated by API documentation (as of 2022-10),
            // missing or wrong params will throw an Exception, plus other connection etc issues
            Log::error('PayrexxHelpers createGatewayLink Booking ID=' . $bookingData->id);
            Log::error($e->getMessage());
            Log::error('Error line:'. $e->getLine());
            Log::error('Error file:'. $e->getFile());
            $link = '';
        }

        return $link;
    }

    public static function createGatewayLink($schoolData, $bookingData,
                                             $basketData, Client $buyerUser = null, $redirectTo = null)
    {
        $link = '';

        try {
            // Check that School has Payrexx credentials
            //dd($schoolData->getPayrexxInstance());
            if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
                throw new \Exception('No credentials for School ID=' . $schoolData->id);
            }

            // Prepare gateway: basic data
            $gr = new GatewayRequest();
            $gr->setReferenceId($bookingData->getOrGeneratePayrexxReference());
            $gr->setAmount($bookingData->price_total * 100);
            $gr->setCurrency($bookingData->currency);
            $gr->setVatRate($schoolData->bookings_comission_cash);                  // TODO TBD as of 2022-10 all Schools are at Switzerland and there's no VAT ???

            // Add School's legal terms, if set
            if ($schoolData->conditions_url) {
                $gr->addField('terms', $schoolData->conditions_url);
            }

            // Product basket i.e. courses booked plus maybe cancellation insurance
            $basket = [];

            $totalAmount = 0;

// Agregar el precio base
            $basket[] = [
                'name' => [1 => $basketData['price_base']['name']],
                'quantity' => $basketData['price_base']['quantity'],
                'amount' => $basketData['price_base']['price'] * 100, // Convertir a centavos
            ];
            $totalAmount += $basketData['price_base']['price'] * 100;

// Agregar reducción
            if (isset($basketData['reduction'])) {
                $basket[] = [
                    'name' => [1 => $basketData['reduction']['name']],
                    'quantity' => $basketData['reduction']['quantity'],
                    'amount' => $basketData['reduction']['price'] * 100,
                ];
                $totalAmount += $basketData['reduction']['price'] * 100;
            }

// Agregar el campo "tva"
            $basket[] = [
                'name' => [1 => $basketData['tva']['name']],
                'quantity' => $basketData['tva']['quantity'],
                'amount' => $basketData['tva']['price'] * 100,
            ];
            $totalAmount += $basketData['tva']['price'] * 100;

// Agregar "Boukii Care"
            $basket[] = [
                'name' => [1 => $basketData['boukii_care']['name']],
                'quantity' => $basketData['boukii_care']['quantity'],
                'amount' => $basketData['boukii_care']['price'] * 100,
            ];
            $totalAmount += $basketData['boukii_care']['price'] * 100;

// Agregar "Cancellation Insurance"
            $basket[] = [
                'name' => [1 => $basketData['cancellation_insurance']['name']],
                'quantity' => $basketData['cancellation_insurance']['quantity'],
                'amount' => $basketData['cancellation_insurance']['price'] * 100,
            ];
            $totalAmount += $basketData['cancellation_insurance']['price'] * 100;

// Agregar extras
            if (isset($basketData['extras']['extras']) && count($basketData['extras']['extras']) > 0) {
                foreach ($basketData['extras']['extras'] as $extra) {
                    $basket[] = [
                        'name' => [1 => $extra['name']],
                        'quantity' => $extra['quantity'],
                        'amount' => $extra['price'] * 100,
                    ];
                    $totalAmount += $extra['price'] * 100;
                }
            }

// Agregar bonos
            if (isset($basketData['bonus']['bonuses']) && count($basketData['bonus']['bonuses']) > 0) {
                foreach ($basketData['bonus']['bonuses'] as $bonus) {
                    $basket[] = [
                        'name' => [1 => $bonus['name']],
                        'quantity' => $bonus['quantity'],
                        'amount' => $bonus['price'] * 100,
                    ];
                    $totalAmount += $bonus['price'] * 100;
                }
            }

// Verificar si la suma del basket coincide con pending_amount
            if ($totalAmount / 100 !== $basketData['pending_amount']) {
                // Si no coincide, eliminar bonos y recalcular
                foreach ($basket as $key => $item) {
                    if (strpos($item['name'][1], 'BOU') !== false) {
                        $totalAmount -= $item['amount'];
                        unset($basket[$key]);

                        // Verificar si ahora coincide
                        if ($totalAmount / 100 === $basketData['pending_amount']) {
                            break;
                        }
                    }
                }

                // Si aún no coincide, ajustar con un campo adicional
                $adjustment = ($basketData['pending_amount'] * 100) - $totalAmount;

                if ($adjustment != 0) { // Solo añadir el ajuste si es diferente de 0
                    $basket[] = [
                        'name' => [1 => 'Adjustment'],
                        'quantity' => 1,
                        'amount' => $adjustment,
                    ];
                    $totalAmount += $adjustment;
                }
            }

            $gr->setBasket($basket);
            $gr->setAmount($totalAmount);

            // Buyer data
            if ($buyerUser) {
                $gr->addField('forename', $buyerUser->first_name);
                $gr->addField('surname', $buyerUser->last_name);
                $gr->addField('phone', $buyerUser->phone);
                $gr->addField('email', $buyerUser->email);
                $gr->addField('street', $buyerUser->address);
                $gr->addField('postcode', $buyerUser->cp);

                $gr->addField('place', $buyerUser->province);
                $gr->addField('country',  $buyerUser->country);
            }

            // OK/error pages to redirect user after payment
            if ($redirectTo == 'panel') {
                $gr->setSuccessRedirectUrl(env('ADMIM_URL') . '/bookings?status=success');
                $gr->setFailedRedirectUrl(env('ADMIM_URL') . '/bookings?status=failed');
                $gr->setCancelRedirectUrl(env('ADMIM_URL') . '/bookings?status=cancel');
            } else if ($redirectTo == 'app') {
                $gr->setSuccessRedirectUrl(route('api.payrexx.finish', ['status' => 'success']));
                $gr->setFailedRedirectUrl(route('api.payrexx.finish', ['status' => 'failed']));
                $gr->setCancelRedirectUrl(route('api.payrexx.finish', ['status' => 'cancel']));
            } else if ($redirectTo != null) {
                $gr->setSuccessRedirectUrl($redirectTo . '?status=success');
                $gr->setFailedRedirectUrl($redirectTo . '?status=failed');
                $gr->setCancelRedirectUrl($redirectTo . '?status=cancel');
            }

            if($bookingData->source == 'web') {
                $gr->setValidity(15);
            }

            Log::channel('payrexx')->info('Link prepared amount: '. $totalAmount);


            // Launch it
            $payrexx = new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );

            $gateway = $payrexx->create($gr);

            if ($gateway) {
                $link = $gateway->getLink();
            }

        } catch (\Exception $e) {
            // Altought not stated by API documentation (as of 2022-10),
            // missing or wrong params will throw an Exception, plus other connection etc issues
            Log::error('PayrexxHelpers createGatewayLink Booking ID=' . $bookingData->id);
            Log::error($e->getMessage());
            Log::error('Error:', $e->getTrace());
            $link = '';
        }

        return $link;
    }

    /**
     * Download from Payrexx the details of a Transaction.
     * @see https://developers.payrexx.com/reference/retrieve-a-transaction
     *
     * @param string $payrexxInstance
     * @param string $payrexxKey
     * @param int $transactionID
     * @return Payrexx\Models\Response\Transaction|null
     */
    public static function retrieveTransaction($payrexxInstance, $payrexxKey, $transactionID)
    {
        try {
            $tr = new TransactionRequest();
            $tr->setId($transactionID);

            $payrexx = new Payrexx(
                $payrexxInstance,
                $payrexxKey,
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );
            return $payrexx->getOne($tr);
        } catch (PayrexxException $pe) {
            // Altough not stated by API documentation (as of 2022-10),
            // if it was a wrong ID, getOne will throw an Exception "No Transaction found with id xxx".
            // Plus other connection etc issues
            Log::channel('payrexx')->error('PayrexxHelpers retrieveTransaction ID=' . $transactionID);
            Log::channel('payrexx')->error($pe->getMessage());
            return null;
        }
    }

    /**
     * Prepare a Payrexx direct pay link.
     * @see https://developers.payrexx.com/reference/create-a-paylink
     *
     * @param School $schoolData i.e. who wants the money
     * @param Booking $bookingData i.e. the Booking ID this payment is for
     * @param User $buyerUser to get his payment & contact details
     *
     * @return string empty if something failed
     */
    public static function createPayLink($schoolData, $bookingData, $basketData, Client $buyerUser = null)
    {
        $link = '';

        try {
            // Check that School has Payrexx credentials
            /*            if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
                            throw new \Exception('No credentials for School ID=' . $schoolData->id);
                        }*/


            // Prepare invoice: basic data
            $ir = new InvoiceRequest();
            $ir->setReferenceId($bookingData->getOrGeneratePayrexxReference());
            //$ir->setAmount($bookingData->price_total * 100);
            $ir->setCurrency($bookingData->currency);
            $ir->setVatRate($schoolData->bookings_comission_cash);                  // TODO TBD as of 2022-10 all Schools are at Switzerland and there's no VAT ???

            // Add School's legal terms, if set
            if ($schoolData->conditions_url) {
                $ir->addField('terms', $schoolData->conditions_url);
            }
            // Product data i.e. courses booked plus maybe cancellation insurance
            $ir->setTitle($schoolData->name);

            // Calcular el precio total del "basket"
            /*        $totalAmount = array_reduce($basketData->all(), function($carry, $item) {
                        return $carry + $item['amount'];
                    }, 0);*/

            $basket = [];

            $totalAmount = 0;

// Agregar el precio base
            $basket[] = [
                'name' => [1 => $basketData['price_base']['name']],
                'quantity' => $basketData['price_base']['quantity'],
                'amount' => $basketData['price_base']['price'] * 100, // Convertir a centavos
            ];
            $totalAmount += $basketData['price_base']['price'] * 100;

// Agregar reducción
            if (isset($basketData['reduction'])) {
                $basket[] = [
                    'name' => [1 => $basketData['reduction']['name']],
                    'quantity' => $basketData['reduction']['quantity'],
                    'amount' => $basketData['reduction']['price'] * 100,
                ];
                $totalAmount += $basketData['reduction']['price'] * 100;
            }

// Agregar el campo "tva"
            $basket[] = [
                'name' => [1 => $basketData['tva']['name']],
                'quantity' => $basketData['tva']['quantity'],
                'amount' => $basketData['tva']['price'] * 100,
            ];
            $totalAmount += $basketData['tva']['price'] * 100;

// Agregar "Boukii Care"
            $basket[] = [
                'name' => [1 => $basketData['boukii_care']['name']],
                'quantity' => $basketData['boukii_care']['quantity'],
                'amount' => $basketData['boukii_care']['price'] * 100,
            ];
            $totalAmount += $basketData['boukii_care']['price'] * 100;

// Agregar "Cancellation Insurance"
            $basket[] = [
                'name' => [1 => $basketData['cancellation_insurance']['name']],
                'quantity' => $basketData['cancellation_insurance']['quantity'],
                'amount' => $basketData['cancellation_insurance']['price'] * 100,
            ];
            $totalAmount += $basketData['cancellation_insurance']['price'] * 100;

// Agregar extras
            if (isset($basketData['extras']['extras']) && count($basketData['extras']['extras']) > 0) {
                foreach ($basketData['extras']['extras'] as $extra) {
                    $basket[] = [
                        'name' => [1 => $extra['name']],
                        'quantity' => $extra['quantity'],
                        'amount' => $extra['price'] * 100,
                    ];
                    $totalAmount += $extra['price'] * 100;
                }
            }

// Agregar bonos
            if (isset($basketData['bonus']['bonuses']) && count($basketData['bonus']['bonuses']) > 0) {
                foreach ($basketData['bonus']['bonuses'] as $bonus) {
                    $basket[] = [
                        'name' => [1 => $bonus['name']],
                        'quantity' => $bonus['quantity'],
                        'amount' => $bonus['price'] * 100,
                    ];
                    $totalAmount += $bonus['price'] * 100;
                }
            }

// Verificar si la suma del basket coincide con pending_amount
            if ($totalAmount / 100 !== $basketData['pending_amount']) {
                // Si no coincide, eliminar bonos y recalcular
                foreach ($basket as $key => $item) {
                    if (strpos($item['name'][1], 'BOU') !== false) {
                        $totalAmount -= $item['amount'];
                        unset($basket[$key]);

                        // Verificar si ahora coincide
                        if ($totalAmount / 100 === $basketData['pending_amount']) {
                            break;
                        }
                    }
                }

                // Si aún no coincide, ajustar con un campo adicional
                $adjustment = ($basketData['pending_amount'] * 100) - $totalAmount;

                if ($adjustment != 0) { // Solo añadir el ajuste si es diferente de 0
                    $basket[] = [
                        'name' => [1 => 'Adjustment'],
                        'quantity' => 1,
                        'amount' => $adjustment,
                    ];
                    $totalAmount += $adjustment;
                }
            }

            //$totalAmount = $basketData['pending_amount'] * 100;

            Log::channel('payrexx')->info('Basket, ', $basket);


            $paymentSummary = self::generatePaymentSummary($basket);


            $ir->setAmount($totalAmount);
/*
            Log::channel('payrexx')->info('Pending Amount:', ['pending_amount' => $basket['pending_amount']]);
            Log::channel('payrexx')->info('Total Amount in Cents:', ['total_amount' => $totalAmount]);
            Log::channel('payrexx')->info('InvoiceRequest Amount:', ['amount' => $ir->getAmount()]);*/
            // $ir->setDescription($basketData->all());
            $ir->setName($bookingData->getOrGeneratePayrexxReference());
            //  $ir->setPurpose($basketData->all());
            $ir->setTitle($paymentSummary['title']);
            $ir->setPurpose('Booking: #'.$bookingData->id);
            $ir->setDescription($paymentSummary['description']);
            // Add School's legal terms, if set
            // (InvoiceRequest DOES accept "terms" as a valid field)
            if ($schoolData->conditions_url) {
                $ir->addField('terms', true, $schoolData->conditions_url);
            }

            if ($buyerUser) {
                $ir->addField('forename', $buyerUser->first_name);
                $ir->addField('surname', $buyerUser->last_name);
                $ir->addField('phone', $buyerUser->phone);
                $ir->addField('email', $buyerUser->email);
                $ir->addField('street', $buyerUser->address);
                $ir->addField('postcode', $buyerUser->cp);

                $ir->addField('place', $buyerUser->province);
                $ir->addField('country',  $buyerUser->country);
            }

            Log::channel('payrexx')->info('Link prepared amount: '. $totalAmount);

            // Launch it
            $payrexx = new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );
            // dd($ir);
            Log::channel('payrexx')->info('InvoiceRequest Amount after changes:', ['amount' => $ir->getAmount()]);
            $invoice = $payrexx->create($ir);
            //Log::channel('payrexx')->info('Info', $invoice);
            Log::channel('payrexx')->info($invoice->getLink());
            if ($invoice) {
                $link = $invoice->getLink();
            }
        } catch (\Exception $e) {
            // Altought not stated by API documentation (as of 2022-10),
            // missing or wrong params will throw an Exception, plus other connection etc issues
            Log::channel('payrexx')->error('PayrexxHelpers createPayLink Booking ID=' . $bookingData->id);
            Log::channel('payrexx')->error($e->getMessage());
            Log::channel('payrexx')->error($e->getLine());

            $link = '';
        }

        return $link;
    }

    public static function generatePaymentSummary($basketData)
    {
        $title = "Payment";
        $descriptionLines = [];

        // Recorrer cada item en el array del basketData
        foreach ($basketData as $key => $item) {
            // Verificar si el elemento es un array y tiene las claves esperadas
            if (is_array($item) && isset($item['name'], $item['quantity'], $item['price'])) {
                $name = $item['name'];
                $quantity = $item['quantity'];
                $price = $item['price'];

                // Formatear la línea de descripción
                $descriptionLines[] = "$name - Quantity: $quantity - Price: " . number_format($price, 2) . " CHF";
            }
        }

        // Generar el texto de la descripción
        $description = implode("\n", $descriptionLines);

        return [
            'title' => $title,
            'description' => $description,
        ];
    }


    /**
     * Tell Payrexx to refund some money from a Transaction.
     * @see https://developers.payrexx.com/reference/refund-a-transaction
     *
     * @param \App\Models\Booking $bookingData i.e. the product to be refunded
     * @param float $amountToRefund
     * @return boolean
     */
    public static function refundTransaction($bookingData, $amountToRefund)
    {
        try {
            // Check if the total booking price is greater than or equal to the amount to refund
            $totalBookingPrice = $bookingData->price_total;
            if ($totalBookingPrice < $amountToRefund) {
                throw new \Exception('Amount to refund exceeds total booking price');
            }

            // Check if any payment is greater than or equal to the amount to refund
            $paymentToUse = null;
            foreach ($bookingData->payments as $payment) {
                if ($payment->amount >= $amountToRefund
                    && $payment->payrexx_transaction != null
                    && $payment->status == 'paid') {

                    // Find other payments with the same payrexx_transaction
                    $relatedPayments = $bookingData->payments->filter(function ($relatedPayment) use ($payment) {
                        return $relatedPayment->payrexx_transaction == $payment->payrexx_transaction;
                    });

                    $refundAmount = $payment->amount;

                    // Check the status of related payments
                    foreach ($relatedPayments as $relatedPayment) {
                        if ($relatedPayment->status == 'refund') {
                            continue; // Skip payments that are fully refunded
                        } elseif ($relatedPayment->status == 'partial_refund') {
                            // Subtract the amount of partial refunds
                            $refundAmount -= $relatedPayment->amount;
                        }
                    }

                    if ($refundAmount >= $amountToRefund) {
                        $paymentToUse = $payment;
                        break;
                    }
                }
            }

            if (!$paymentToUse) {
                // If no single payment covers the refund amount, perform partial refunds
                $remainingAmountToRefund = $amountToRefund;
                foreach ($bookingData->payments as $payment) {
                    if ($payment->amount > 0 && $payment->payrexx_transaction != null) {
                        // Find other payments with the same payrexx_transaction
                        $relatedPayments = $bookingData->payments->filter(function ($relatedPayment) use ($payment) {
                            return $relatedPayment->payrexx_transaction == $payment->payrexx_transaction;
                        });

                        // Calculate the total refund amount for related payments
                        $totalRefundAmount = 0;
                        foreach ($relatedPayments as $relatedPayment) {
                            if ($relatedPayment->status == 'refund') {
                                continue; // Skip payments that are fully refunded
                            } elseif ($relatedPayment->status == 'partial_refund') {
                                // Add the amount of partial refunds
                                $totalRefundAmount += $relatedPayment->amount;
                            }
                        }

                        // Calculate the remaining refund amount for this payment
                        $refundAmount = min($payment->amount - $totalRefundAmount, $remainingAmountToRefund);

                        if ($refundAmount > 0) {
                            $refundSuccess = self::performRefund($payment, $refundAmount);
                            if ($refundSuccess) {
                                $remainingAmountToRefund -= $refundAmount;

                                if ($remainingAmountToRefund <= 0) {
                                    break;
                                }
                            }
                        }
                    }
                }
            } else {
                // Use the payment that covers the full refund amount
                $refundSuccess = self::performRefund($paymentToUse, $amountToRefund);
            }

            if ($refundSuccess) {
                return true;
            } else {
                throw new \Exception('Refund failed');
            }
        } catch (\Exception $e) {
            Log::channel('payrexx')->error('PayrexxHelpers refundTransaction Booking ID=' . $bookingData->id);
            Log::channel('payrexx')->error($e->getMessage());
            return false;
        }
    }

    private static function performRefund($payment, $refundAmount)
    {
        // Perform the actual refund using Payrexx
        $transactionData = $payment->getPayrexxTransaction();
        $transactionID = $transactionData['id'] ?? '';

        $tr = new TransactionRequest();
        $tr->setId($transactionID);
        $tr->setAmount($refundAmount * 100);

        $payrexx = new Payrexx(
            $payment->school->getPayrexxInstance(),
            $payment->school->getPayrexxKey(),
            '',
            env('PAYREXX_API_BASE_DOMAIN')
        );
        Log::channel('payrexx')->debug('PayrexxHelpers refundTransaction: ' . $transactionID);
        Log::channel('payrexx')->debug('PayrexxHelpers refund amount: ' . $refundAmount);
        $response = $payrexx->refund($tr);
        $newPayment = new Payment($payment->toArray());
        // Update payment notes based on whether it's a full or partial refund
        if ($response->getStatus() == TransactionResponse::REFUNDED) {
            $newPayment->status = 'refund';
            $newPayment->amount = $refundAmount;
            $newPayment->save();
        } elseif ($response->getStatus() == TransactionResponse::PARTIALLY_REFUNDED) {
            $newPayment->status = 'partial_refund';
            $newPayment->amount = $refundAmount;
            $newPayment->save();
        }



        return ($response->getStatus() == TransactionResponse::REFUNDED || $response->getStatus() == TransactionResponse::PARTIALLY_REFUNDED);
    }


    /**
     * Send an email with payment data: a Payrexx direct pay link both as text and as QR
     *
     * @param School $schoolData i.e. who wants the money
     * @param Booking2 $bookingData i.e. the Booking ID this payment is for
     * @param User $buyerUser to get his payment & contact details
     *
     * @return boolean telling if it was OK
     */
    public static function sendPayEmail($schoolData, $bookingData, $request, $buyerUser)
    {
        $sentOK = false;

        if ($buyerUser && $buyerUser->email) {
            try {
                // Check that School has Payrexx credentials
                if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
                    throw new \Exception('No credentials for School ID=' . $schoolData->id);
                }


                // Create pay link
                $link = self::createPayLink($schoolData, $bookingData, $request, $buyerUser);
                if (strlen($link) < 1) {
                    throw new \Exception('Cant create Payrexx Direct Link for School ID=' . $schoolData->id);
                }

                // Send by email
                $bookingData = $bookingData->fresh();   // To retrieve its generated PayrexxReference
                \Mail::to($buyerUser->email)
                    ->send(new BookingPayMailer(
                        $schoolData,
                        $bookingData,
                        $buyerUser,
                        $link
                    ));
                $logData = [
                    'booking_id' => $bookingData->id,
                    'action' => 'send_pay_link',
                    'user_id' => $bookingData->user_id,
                    'description' => 'Booking pay link sent',
                ];

                BookingLog::create($logData);
                $sentOK = true;
            } catch (\Exception $e) {
                // Altought not stated by API documentation (as of 2022-10),
                // missing or wrong params will throw an Exception, plus other connection etc issues
                Log::channel('payrexx')->error('PayrexxHelpers sendPayEmail Booking ID=' . $bookingData->id);
                Log::channel('payrexx')->error($e->getTraceAsString());
            }
        }

        return $sentOK;
    }


    public static function createVoucherPayLink($schoolData, $voucherData, $buyerUser = null)
    {
        $link = '';

        try {
            // Check that School has Payrexx credentials
            if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
                throw new \Exception('No credentials for School ID=' . $schoolData->id);
            }

            // Prepare gateway: basic data
            $gr = new GatewayRequest();
            $payrexx_reference = $voucherData->generatePayrexxReference();
            $gr->setReferenceId($payrexx_reference);
            $gr->setAmount($voucherData->quantity * 100);
            $gr->setCurrency('CHF');
            $gr->setVatRate(null);                  // TODO TBD as of 2022-10 all Schools are at Switzerland and there's no VAT ???

            // Add School's legal terms, if set
            if ($schoolData->conditions_url) {
                $gr->addField('terms', $schoolData->conditions_url);
            }

            // Product basket i.e. courses booked plus maybe cancellation insurance
            $basket = [];
            $basket[] = [
                'name' => [1 => $payrexx_reference],
                'quantity' => 1,
                'amount' => $voucherData->quantity * 100
            ];
            $gr->setBasket($basket);

            // Buyer data
            if ($buyerUser) {
                $gr->addField('forename', $buyerUser->first_name);
                $gr->addField('surname', $buyerUser->last_name);
                $gr->addField('phone', $buyerUser->phone);
                $gr->addField('email', $buyerUser->email);
                $gr->addField('street', $buyerUser->address);
                $gr->addField('postcode', $buyerUser->cp);

                $province = $buyerUser->province_id ?: null;
                $gr->addField('place', $province ? $province->name : '');
                $gr->addField('country', $province ? $province->country_iso : '');
            }

            // OK/error pages to redirect user after payment
            $gr->setSuccessRedirectUrl(route('api.payrexx.finish', ['status' => 'success']));
            $gr->setFailedRedirectUrl(route('api.payrexx.finish', ['status' => 'failed']));
            $gr->setCancelRedirectUrl(route('api.payrexx.finish', ['status' => 'cancel']));

            // Launch it
            $payrexx = new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );
            $gateway = $payrexx->create($gr);
            if ($gateway) {
                $link = $gateway->getLink();
            }
        } catch (\Exception $e) {
            // Altought not stated by API documentation (as of 2022-10),
            // missing or wrong params will throw an Exception, plus other connection etc issues
            Log::channel('payrexx')->error('PayrexxHelpers createPayLink Voucher ID=' . $voucherData->id);
            Log::channel('payrexx')->error($e->getMessage());
            $link = '';
        }

        return $link;
    }

    // Añadir estos métodos al final de PayrexxHelpers.php

    /**
     * Obtener todas las transacciones de Payrexx en un rango de fechas
     *
     * @param School $schoolData
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    /**
     * Obtener todas las transacciones de Payrexx en un rango de fechas
     *
     * @param School $schoolData
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    /**
     * Versión corregida con paginación del método getTransactionsByDateRange
     * Reemplaza el método existente en PayrexxHelpers.php
     */
    /**
     * Versión corregida que maneja DateTime correctamente
     * Reemplaza el método getTransactionsByDateRange en PayrexxHelpers.php
     */
    /**
     * Método getTransactionsByDateRange corregido que respeta límites de API Payrexx
     * Reemplazar el método completo en PayrexxHelpers.php
     */
    public static function getTransactionsByDateRange($schoolData, $startDate, $endDate)
    {
        try {
            Log::info('=== INICIANDO getTransactionsByDateRange CON LÍMITES CORREGIDOS ===', [
                'school_id' => $schoolData->id,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            if (!$schoolData->getPayrexxInstance() || !$schoolData->getPayrexxKey()) {
                Log::warning("School {$schoolData->id} no tiene credenciales de Payrexx configuradas");
                return [];
            }

            $payrexx = new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );

            // PAGINACIÓN CORREGIDA CON LÍMITES DE API
            $allTransactions = [];
            $limit = 100; // MÁXIMO PERMITIDO POR LA API
            $maxPages = 50; // Obtener hasta 2000 transacciones
            $totalFetched = 0;

            Log::info('Iniciando paginación con límites correctos', [
                'limit_per_page' => $limit,
                'max_pages' => $maxPages
            ]);

            for ($page = 0; $page < $maxPages; $page++) {
                $offset = $page * $limit;

                Log::info("Obteniendo página " . ($page + 1), [
                    'offset' => $offset,
                    'limit' => $limit
                ]);

                $transactionRequest = new TransactionRequest();
                $transactionRequest->setLimit($limit); // Usar límite correcto
                $transactionRequest->setOffset($offset);

                $pageTransactions = $payrexx->getAll($transactionRequest);
                $pageCount = count($pageTransactions);
                $totalFetched += $pageCount;

                Log::info("Página " . ($page + 1) . " obtenida", [
                    'transactions_in_page' => $pageCount,
                    'total_fetched_so_far' => $totalFetched
                ]);

                if ($pageCount === 0) {
                    Log::info('No hay más transacciones, terminando paginación');
                    break;
                }

                // Buscar específicamente booking 3075 y otras para debug
                foreach ($pageTransactions as $tx) {
                    $ref = $tx->getReferenceId();
                    if (str_contains($ref, '3075') || str_contains($ref, '3056')) {
                        Log::info('🎯 BOOKING IMPORTANTE ENCONTRADO EN PÁGINA ' . ($page + 1), [
                            'reference' => $ref,
                            'amount' => $tx->getAmount() / 100,
                            'status' => $tx->getStatus(),
                            'id' => $tx->getId()
                        ]);
                    }
                }

                $allTransactions = array_merge($allTransactions, $pageTransactions);

                // Si esta página tiene menos transacciones que el límite, es la última
                if ($pageCount < $limit) {
                    Log::info('Última página alcanzada (página incompleta)');
                    break;
                }
            }

            Log::info('Paginación completada', [
                'total_pages_fetched' => $page + 1,
                'total_transactions' => count($allTransactions)
            ]);

            // PROCESAR TODAS LAS TRANSACCIONES
            $formattedTransactions = [];
// MANEJO SEGURO DE FECHAS NULL
            if (!$startDate || !$endDate) {
                Log::warning('Fechas null detectadas, usando rango amplio');
                $startDateTime = \Carbon\Carbon::now()->subYears(2)->startOfDay();
                $endDateTime = \Carbon\Carbon::now()->addDays(30)->endOfDay();
            } else {
                $startDateTime = \Carbon\Carbon::parse($startDate)->startOfDay();
                $endDateTime = \Carbon\Carbon::parse($endDate)->endOfDay();
            }

            $debugStats = [
                'total_processed' => 0,
                'filtered_by_date' => 0,
                'filtered_by_status' => 0,
                'filtered_by_reference' => 0,
                'final_count' => 0,
                'found_important_bookings' => [],
                'status_distribution' => [],
                'date_range_samples' => []
            ];

            $processedReferences = []; // Array para evitar procesar el mismo reference múltiples veces

            foreach ($allTransactions as $transaction) {
                $debugStats['total_processed']++;

                $reference = $transaction->getReferenceId();
                $status = $transaction->getStatus();

                // Contar distribución de status
                $debugStats['status_distribution'][$status] = ($debugStats['status_distribution'][$status] ?? 0) + 1;

                // Tracking de bookings importantes
                if (str_contains($reference, '3075') || str_contains($reference, '3056') ||
                    str_contains($reference, '3074') || str_contains($reference, '3073')) {
                    $debugStats['found_important_bookings'][] = [
                        'reference' => $reference,
                        'status' => $status,
                        'amount' => $transaction->getAmount() / 100,
                        'id' => $transaction->getId()
                    ];
                }

                // SKIP SI YA PROCESAMOS ESTE REFERENCE
                if (in_array($reference, $processedReferences)) {
                    Log::info("Reference {$reference} ya procesado, saltando duplicado");
                    continue;
                }

                // Obtener timestamp de múltiples formas
                $timestamp = null;
                $timestampMethod = null;
                $txDate = null;

                if (method_exists($transaction, 'getCreatedAt') && $transaction->getCreatedAt()) {
                    $timestamp = $transaction->getCreatedAt();
                    $timestampMethod = 'getCreatedAt';
                } elseif (method_exists($transaction, 'getTime') && $transaction->getTime()) {
                    $timestamp = $transaction->getTime();
                    $timestampMethod = 'getTime';
                } else {
                    // Usar reflection para acceder a la propiedad -time
                    try {
                        $reflection = new \ReflectionObject($transaction);
                        $timeProperty = $reflection->getProperty('time');
                        $timeProperty->setAccessible(true);
                        $timeString = $timeProperty->getValue($transaction);

                        if ($timeString) {
                            $txDate = \Carbon\Carbon::parse($timeString);
                            $timestampMethod = 'reflection_time';
                        }
                    } catch (\Exception $e) {
                        // Ignorar errores de reflection
                    }
                }

                // Convertir timestamp a fecha si no se obtuvo por reflection
                if (!$txDate && $timestamp) {
                    try {
                        $txDate = \Carbon\Carbon::createFromTimestamp($timestamp);
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (!$txDate) {
                    continue;
                }

                // Guardar muestra de fechas para debug
                $dateKey = $txDate->format('Y-m-d');
                if (count($debugStats['date_range_samples']) < 10) {
                    $debugStats['date_range_samples'][$dateKey] =
                        ($debugStats['date_range_samples'][$dateKey] ?? 0) + 1;
                }

                // APLICAR FILTROS MANUALMENTE

                // 1. Filtro de fecha
                if ($startDate && $endDate) {
                    if ($txDate->lt($startDateTime) || $txDate->gt($endDateTime)) {
                        if (str_contains($reference, '3075') || str_contains($reference, '3056')) {
                            Log::info("Transacción {$reference} FILTRADA POR FECHA", [
                                'tx_date' => $txDate->format('Y-m-d H:i:s'),
                                'start_range' => $startDateTime->format('Y-m-d H:i:s'),
                                'end_range' => $endDateTime->format('Y-m-d H:i:s')
                            ]);
                        }
                        $debugStats['filtered_by_date']++;
                        continue;
                    }
                }

                // 2. Filtro de status (más permisivo)
                $validStatuses = ['confirmed', 'authorized', 'captured', 'paid', 'settled', 'processing'];
                if (!in_array($status, $validStatuses)) {
                    $debugStats['filtered_by_status']++;
                    continue;
                }

                // 3. Filtro de referencia
                if (!str_contains($reference, 'Boukii #') && !str_contains($reference, 'BOU')) {
                    $debugStats['filtered_by_reference']++;
                    continue;
                }

                // BUSCAR TODAS LAS TRANSACCIONES CON EL MISMO REFERENCE Y SUMARLAS
                $totalAmount = 0;
                $allTransactionIds = [];
                $latestDate = null;
                $latestTimestampMethod = null;
                $mainTransactionId = null;
                $transactionCount = 0;

                foreach ($allTransactions as $innerTransaction) {
                    if ($innerTransaction->getReferenceId() === $reference) {
                        $innerStatus = $innerTransaction->getStatus();

                        // Aplicar los mismos filtros a las transacciones internas
                        if (!in_array($innerStatus, $validStatuses)) {
                            continue;
                        }

                        // Obtener fecha de la transacción interna
                        $innerTxDate = null;
                        $innerTimestampMethod = null;

                        if (method_exists($innerTransaction, 'getCreatedAt') && $innerTransaction->getCreatedAt()) {
                            $innerTxDate = \Carbon\Carbon::createFromTimestamp($innerTransaction->getCreatedAt());
                            $innerTimestampMethod = 'getCreatedAt';
                        } elseif (method_exists($innerTransaction, 'getTime') && $innerTransaction->getTime()) {
                            $innerTxDate = \Carbon\Carbon::createFromTimestamp($innerTransaction->getTime());
                            $innerTimestampMethod = 'getTime';
                        }

                        if ($innerTxDate) {
                            // Aplicar filtro de fecha a la transacción interna
                            if ($startDate && $endDate) {
                                if ($innerTxDate->lt($startDateTime) || $innerTxDate->gt($endDateTime)) {
                                    continue;
                                }
                            }

                            $totalAmount += $innerTransaction->getAmount() / 100;
                            $allTransactionIds[] = $innerTransaction->getId();
                            $transactionCount++;

                            // Mantener la fecha más reciente
                            if (!$latestDate || $innerTxDate->gt($latestDate)) {
                                $latestDate = $innerTxDate;
                                $latestTimestampMethod = $innerTimestampMethod;
                                $mainTransactionId = $innerTransaction->getId();
                            }
                        }
                    }
                }

                // Solo agregar si encontramos transacciones válidas
                if ($transactionCount > 0) {
                    Log::info("PROCESANDO REFERENCE {$reference}", [
                        'transactions_found' => $transactionCount,
                        'total_amount' => $totalAmount,
                        'transaction_ids' => $allTransactionIds
                    ]);

                    $formattedTransactions[$reference] = [
                        'id' => $mainTransactionId,
                        'reference' => $reference,
                        'amount' => $totalAmount,
                        'currency' => 'CHF',
                        'status' => $status,
                        'date' => $latestDate->format('Y-m-d H:i:s'),
                        'timestamp_method' => $latestTimestampMethod,
                        'payment_method' => self::getPaymentMethodFromTransaction($transaction),
                        'transaction_data' => $transaction,
                        'multiple_transactions' => $transactionCount > 1,
                        'transaction_ids' => $allTransactionIds
                    ];

                    // MARCAR COMO PROCESADO PARA EVITAR DUPLICADOS
                    $processedReferences[] = $reference;
                    $debugStats['final_count']++;
                }
            }

            Log::info('=== ESTADÍSTICAS FINALES CORREGIDAS ===', [
                'total_pages_fetched' => $page + 1,
                'total_raw_transactions' => count($allTransactions),
                'debug_stats' => $debugStats,
                'final_formatted_count' => count($formattedTransactions),
                'important_bookings_found' => count($debugStats['found_important_bookings']),
                'contains_3075' => isset($formattedTransactions['Boukii #3075']),
                'contains_3056' => isset($formattedTransactions['Boukii #3056'])
            ]);

            return $formattedTransactions;

        } catch (\Exception $e) {
            Log::error('Error en getTransactionsByDateRange corregido: ' . $e->getMessage(), [
                'school_id' => $schoolData->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Verificar una transacción específica contra Payrexx
     *
     * @param Payment $payment
     * @param Booking $booking
     * @return array
     */
    public static function verifyTransactionDetails($payment, $booking)
    {
        try {
            $school = $booking->school;

            $verification = [
                'payment_id' => $payment->id,
                'reference' => $payment->payrexx_reference,
                'system_amount' => $payment->amount,
                'payrexx_amount' => null,
                'status_match' => false,
                'amount_match' => false,
                'found_in_payrexx' => false,
                'issues' => []
            ];

            // Si tenemos datos de la transacción guardados
            if ($payment->payrexx_transaction) {
                $transactionData = $payment->getPayrexxTransaction();

                if (isset($transactionData['id'])) {
                    // Verificar directamente con Payrexx
                    $payrexxTransaction = self::retrieveTransaction(
                        $school->getPayrexxInstance(),
                        $school->getPayrexxKey(),
                        $transactionData['id']
                    );

                    if ($payrexxTransaction) {
                        $verification['found_in_payrexx'] = true;
                        $payrexxAmount = $payrexxTransaction->getAmount() / 100;
                        $verification['payrexx_amount'] = $payrexxAmount;
                        $verification['amount_match'] = abs($payment->amount - $payrexxAmount) < 0.01;
                        $verification['status_match'] = self::statusMatches($payment->status, $payrexxTransaction->getStatus());

                        if (!$verification['amount_match']) {
                            $verification['issues'][] = "Monto no coincide: Sistema {$payment->amount}€ vs Payrexx {$payrexxAmount}€";
                        }

                        if (!$verification['status_match']) {
                            $verification['issues'][] = "Estado no coincide: Sistema '{$payment->status}' vs Payrexx '{$payrexxTransaction->getStatus()}'";
                        }
                    } else {
                        $verification['issues'][] = 'Transacción no encontrada en Payrexx con ID: ' . $transactionData['id'];
                    }
                } else {
                    $verification['issues'][] = 'No hay ID de transacción Payrexx en los datos guardados';
                }
            } else {
                $verification['issues'][] = 'No hay datos de transacción Payrexx guardados en el sistema';
            }

            return $verification;

        } catch (\Exception $e) {
            Log::error('PayrexxHelpers::verifyTransactionDetails error: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id
            ]);

            return [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'found_in_payrexx' => false,
                'issues' => ['Error al verificar: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Comparar pagos de una reserva con Payrexx - VERSIÓN CORREGIDA
     *
     * @param Booking $booking
     * @param array $payrexxTransactions (opcional, si ya se obtuvieron)
     * @return array
     */
    public static function compareBookingWithPayrexx($booking, $payrexxTransactions = null)
    {
        $comparison = [
            'booking_id' => $booking->id,
            'transactions' => [],
            'verified_payments' => [],
            'total_system_amount' => 0,
            'total_payrexx_amount' => 0,
            'has_discrepancy' => false,
            'difference' => 0,
            'missing_transactions' => [],
            'summary' => []
        ];

        // Obtener solo pagos exitosos con referencia de Payrexx
        $bookingPayments = $booking->payments
            ->whereNotNull('payrexx_reference')
            ->whereIn('status', ['paid']);

        Log::info("Comparando booking {$booking->id} con Payrexx", [
            'payments_count' => $bookingPayments->count(),
            'payments_references' => $bookingPayments->pluck('payrexx_reference')->toArray(),
            'payrexx_transactions_available' => $payrexxTransactions ? count($payrexxTransactions) : 0,
            'payrexx_transaction_keys' => $payrexxTransactions ? array_keys($payrexxTransactions) : []
        ]);

        // NUEVA LÓGICA: Procesar por referencia única, no por pago individual
        $processedReferences = [];
        $referenceGroups = $bookingPayments->groupBy('payrexx_reference');

        foreach ($referenceGroups as $reference => $paymentsWithSameRef) {
            Log::info("Procesando referencia {$reference}", [
                'payments_count' => $paymentsWithSameRef->count(),
                'payment_ids' => $paymentsWithSameRef->pluck('id')->toArray()
            ]);

            // Sumar todos los pagos del sistema para esta referencia
            $systemAmountForRef = $paymentsWithSameRef->sum('amount');
            $comparison['total_system_amount'] += $systemAmountForRef;

            // Buscar la transacción de Payrexx para esta referencia (SOLO UNA VEZ)
            $payrexxTx = null;
            $foundInPayrexx = false;

            if ($payrexxTransactions && isset($payrexxTransactions[$reference])) {
                $payrexxTx = $payrexxTransactions[$reference];
                $foundInPayrexx = true;

                // ✅ AÑADIR TRANSACCIÓN SOLO UNA VEZ POR REFERENCIA
                if (!in_array($reference, $processedReferences)) {
                    $comparison['transactions'][] = $payrexxTx;
                    $comparison['total_payrexx_amount'] += $payrexxTx['amount'];
                    $processedReferences[] = $reference;

                    Log::info("✅ Transacción Payrexx añadida ÚNICA VEZ", [
                        'reference' => $reference,
                        'payrexx_amount' => $payrexxTx['amount'],
                        'system_amount_for_ref' => $systemAmountForRef
                    ]);
                }
            } else {
                // Buscar con variaciones de la referencia
                foreach ($payrexxTransactions as $ref => $tx) {
                    $normalizedPaymentRef = trim(strtolower($reference));
                    $normalizedTxRef = trim(strtolower($ref));

                    if ($normalizedPaymentRef === $normalizedTxRef) {
                        $payrexxTx = $tx;
                        $foundInPayrexx = true;

                        if (!in_array($ref, $processedReferences)) {
                            $comparison['transactions'][] = $tx;
                            $comparison['total_payrexx_amount'] += $tx['amount'];
                            $processedReferences[] = $ref;

                            Log::info("✅ Transacción encontrada por referencia normalizada", [
                                'payment_ref' => $reference,
                                'payrexx_ref' => $ref,
                                'amount' => $tx['amount']
                            ]);
                        }
                        break;
                    }
                }
            }

            // Crear verificaciones para cada pago individual de esta referencia
            foreach ($paymentsWithSameRef as $payment) {
                if ($foundInPayrexx && $payrexxTx) {
                    $comparison['verified_payments'][] = [
                        'payment_id' => $payment->id,
                        'reference' => $reference,
                        'system_amount' => $payment->amount,
                        'payrexx_amount' => $payrexxTx['amount'],
                        'status_match' => true,
                        'amount_match' => abs($systemAmountForRef - $payrexxTx['amount']) < 0.01,
                        'found_in_payrexx' => true,
                        'issues' => []
                    ];
                } else {
                    // FALLBACK: Verificación individual solo si no se encontró por referencia
                    $verification = self::verifyTransactionDetails($payment, $booking);
                    $comparison['verified_payments'][] = $verification;

                    if ($verification['found_in_payrexx'] && $verification['payrexx_amount']) {
                        // Solo añadir si no se procesó ya por referencia
                        if (!$foundInPayrexx) {
                            $comparison['total_payrexx_amount'] += $verification['payrexx_amount'];
                        }

                        Log::info("✅ Transacción encontrada por verificación individual", [
                            'payment_id' => $payment->id,
                            'payrexx_amount' => $verification['payrexx_amount']
                        ]);
                    } else {
                        $comparison['missing_transactions'][] = [
                            'reference' => $reference,
                            'payment_id' => $payment->id,
                            'amount' => $payment->amount,
                            'status' => $payment->status,
                            'reason' => 'No encontrada por referencia ni por verificación individual'
                        ];

                        Log::warning("❌ Transacción no encontrada", [
                            'payment_id' => $payment->id,
                            'reference' => $reference,
                            'verification_issues' => $verification['issues'] ?? []
                        ]);
                    }
                }
            }
        }

        // Calcular discrepancias
        $difference = round($comparison['total_system_amount'] - $comparison['total_payrexx_amount'], 2);

        if (abs($difference) > 0.01) {
            $comparison['has_discrepancy'] = true;
            $comparison['difference'] = $difference;
        }

        // Resumen de problemas
        $comparison['summary'] = [
            'total_payments' => $bookingPayments->count(),
            'verified_payments' => count($comparison['verified_payments']),
            'successful_verifications' => count(array_filter($comparison['verified_payments'], fn($v) => $v['found_in_payrexx'])),
            'amount_mismatches' => count(array_filter($comparison['verified_payments'], fn($v) => isset($v['amount_match']) && !$v['amount_match'])),
            'status_mismatches' => count(array_filter($comparison['verified_payments'], fn($v) => isset($v['status_match']) && !$v['status_match'])),
            'missing_in_payrexx' => count($comparison['missing_transactions']),
            'unique_references_processed' => count($processedReferences)
        ];

        Log::info("Comparación CORREGIDA completada para booking {$booking->id}", [
            'system_amount' => $comparison['total_system_amount'],
            'payrexx_amount' => $comparison['total_payrexx_amount'],
            'has_discrepancy' => $comparison['has_discrepancy'],
            'difference' => $comparison['difference'],
            'unique_payrexx_transactions' => count($comparison['transactions']),
            'processed_references' => $processedReferences,
            'summary' => $comparison['summary']
        ]);

        return $comparison;
    }
    /**
     * Análisis completo de Payrexx para múltiples reservas - VERSIÓN CORREGIDA
     *
     * @param \Illuminate\Support\Collection $bookings
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    /**
     * Análisis completo de Payrexx para múltiples reservas - VERSIÓN ACTUALIZADA CON CANCELADAS
     * REEMPLAZAR el método analyzeBookingsWithPayrexx en PayrexxHelpers.php
     */
    public static function analyzeBookingsWithPayrexx($bookings, $startDate, $endDate)
    {
        $analysis = [
            'total_bookings' => $bookings->count(),
            'total_system_amount' => 0,
            'total_payrexx_amount' => 0,
            'total_discrepancies' => 0,
            'discrepancies_amount' => 0,
            'successful_verifications' => 0,
            'failed_verifications' => 0,
            'missing_transactions' => 0,
            'booking_comparisons' => [],
            'payrexx_transactions' => [],
            'unmatched_payrexx_transactions' => [],
            // ✅ NUEVOS CAMPOS PARA ANÁLISIS DE CANCELADAS
            'bookings_by_status' => [
                'active' => 0,
                'cancelled' => 0,
                'partial_cancelled' => 0
            ],
            'amounts_by_status' => [
                'active_system' => 0,
                'active_payrexx' => 0,
                'cancelled_system' => 0,
                'cancelled_payrexx' => 0,
                'partial_cancelled_system' => 0,
                'partial_cancelled_payrexx' => 0
            ]
        ];

        // Obtener todas las transacciones de Payrexx del período por escuela
        $schoolsProcessed = [];
        $allPayrexxTransactions = [];

        foreach ($bookings as $booking) {
            $schoolId = $booking->school_id;

            // Evitar obtener transacciones múltiples veces para la misma escuela
            if (!in_array($schoolId, $schoolsProcessed)) {
                $schoolTransactions = self::getTransactionsByDateRange($booking->school, $startDate, $endDate);
                $allPayrexxTransactions = array_merge($allPayrexxTransactions, $schoolTransactions);
                $analysis['payrexx_transactions'] = array_merge($analysis['payrexx_transactions'], $schoolTransactions);
                $schoolsProcessed[] = $schoolId;

                Log::info("Transacciones obtenidas para escuela", [
                    'school_id' => $schoolId,
                    'transactions_count' => count($schoolTransactions)
                ]);
            }
        }

        Log::info("Total transacciones Payrexx encontradas", [
            'total_count' => count($analysis['payrexx_transactions']),
            'schools_processed' => $schoolsProcessed
        ]);

        // Analizar cada reserva (incluyendo canceladas)
        foreach ($bookings as $booking) {
            $bookingComparison = self::compareBookingWithPayrexx($booking, $allPayrexxTransactions);

            $analysis['booking_comparisons'][] = $bookingComparison;
            $analysis['total_system_amount'] += $bookingComparison['total_system_amount'];
            $analysis['total_payrexx_amount'] += $bookingComparison['total_payrexx_amount'];

            // ✅ CLASIFICAR POR ESTADO
            switch ($booking->status) {
                case 1:
                    $analysis['bookings_by_status']['active']++;
                    $analysis['amounts_by_status']['active_system'] += $bookingComparison['total_system_amount'];
                    $analysis['amounts_by_status']['active_payrexx'] += $bookingComparison['total_payrexx_amount'];
                    break;
                case 2:
                    $analysis['bookings_by_status']['cancelled']++;
                    $analysis['amounts_by_status']['cancelled_system'] += $bookingComparison['total_system_amount'];
                    $analysis['amounts_by_status']['cancelled_payrexx'] += $bookingComparison['total_payrexx_amount'];
                    break;
                case 3:
                    $analysis['bookings_by_status']['partial_cancelled']++;
                    $analysis['amounts_by_status']['partial_cancelled_system'] += $bookingComparison['total_system_amount'];
                    $analysis['amounts_by_status']['partial_cancelled_payrexx'] += $bookingComparison['total_payrexx_amount'];
                    break;
            }

            if ($bookingComparison['has_discrepancy']) {
                $analysis['total_discrepancies']++;
                $analysis['discrepancies_amount'] += abs($bookingComparison['difference']);
            }

            $analysis['successful_verifications'] += $bookingComparison['summary']['successful_verifications'];
            $analysis['failed_verifications'] += ($bookingComparison['summary']['verified_payments'] - $bookingComparison['summary']['successful_verifications']);
            $analysis['missing_transactions'] += $bookingComparison['summary']['missing_in_payrexx'];
        }

        // Buscar transacciones en Payrexx que no están en el sistema
        $usedReferences = [];
        foreach ($analysis['booking_comparisons'] as $comparison) {
            foreach ($comparison['transactions'] as $tx) {
                $usedReferences[] = $tx['reference'];
            }
        }

        foreach ($analysis['payrexx_transactions'] as $reference => $transaction) {
            if (!in_array($reference, $usedReferences)) {
                $analysis['unmatched_payrexx_transactions'][] = $transaction;
            }
        }

        // Redondear totales
        $analysis['total_system_amount'] = round($analysis['total_system_amount'], 2);
        $analysis['total_payrexx_amount'] = round($analysis['total_payrexx_amount'], 2);
        $analysis['discrepancies_amount'] = round($analysis['discrepancies_amount'], 2);
        $analysis['total_difference'] = round($analysis['total_system_amount'] - $analysis['total_payrexx_amount'], 2);

        // Redondear amounts_by_status
        foreach ($analysis['amounts_by_status'] as $key => $amount) {
            $analysis['amounts_by_status'][$key] = round($amount, 2);
        }

        Log::info("Análisis de Payrexx completado (incluyendo canceladas)", [
            'total_bookings' => $analysis['total_bookings'],
            'bookings_by_status' => $analysis['bookings_by_status'],
            'total_system_amount' => $analysis['total_system_amount'],
            'total_payrexx_amount' => $analysis['total_payrexx_amount'],
            'total_difference' => $analysis['total_difference'],
            'discrepancies' => $analysis['total_discrepancies']
        ]);

        return $analysis;
    }

    /**
     * Verificar si los estados coinciden entre sistema y Payrexx
     *
     * @param string $systemStatus
     * @param string $payrexxStatus
     * @return bool
     */
    /**
     * MÉTODO FALTANTE: Verificar si los estados coinciden entre sistema y Payrexx
     */
    private static function statusMatches($systemStatus, $payrexxStatus): bool
    {
        $statusMap = [
            'paid' => ['confirmed', 'authorized', 'captured', 'paid', 'settled'],
            'refund' => ['refunded'],
            'partial_refund' => ['partially_refunded'],
            'pending' => ['waiting', 'processing', 'pending'],
            'failed' => ['failed', 'declined', 'error'],
            'cancelled' => ['cancelled', 'canceled']
        ];

        $validPayrexxStatuses = $statusMap[$systemStatus] ?? [];
        return in_array(strtolower($payrexxStatus), array_map('strtolower', $validPayrexxStatuses));
    }

    /**
     * Obtener método de pago de una transacción Payrexx
     *
     * @param $transaction
     * @return string
     */
    private static function getPaymentMethodFromTransaction($transaction)
    {
        try {
            if (method_exists($transaction, 'getPsp') && $transaction->getPsp()) {
                $psp = $transaction->getPsp();
                if (is_array($psp) && isset($psp[0]['name'])) {
                    return $psp[0]['name'];
                }
            }

            if (method_exists($transaction, 'getPaymentMethod')) {
                return $transaction->getPaymentMethod();
            }

            return 'unknown';

        } catch (\Exception $e) {
            Log::warning('Error getting payment method from Payrexx transaction: ' . $e->getMessage());
            return 'unknown';
        }
    }


    /**
     * Método rápido para verificar específicamente la transacción 3056 faltante
     * Añadir temporalmente a PayrexxHelpers.php
     */
    /**
     * Método corregido que respeta los límites de la API de Payrexx (máximo 100)
     * Reemplazar el método quickCheck3056 en PayrexxHelpers.php
     */
    public static function quickCheck3056($schoolData)
    {
        try {
            Log::info('🔍 VERIFICACIÓN RÁPIDA BOOKING 3056 - LÍMITES CORREGIDOS');

            $payrexx = new Payrexx(
                $schoolData->getPayrexxInstance(),
                $schoolData->getPayrexxKey(),
                '',
                env('PAYREXX_API_BASE_DOMAIN')
            );

            $results = [
                'booking_3056' => [
                    'found_in_transactions' => false,
                    'found_in_invoices' => false,
                    'found_in_gateways' => false,
                    'details' => null
                ],
                'search_by_id' => null,
                'total_counts' => [],
                'pagination_info' => []
            ];

            // 1. BUSCAR EN TRANSACTIONS CON PAGINACIÓN CORRECTA
            Log::info('Buscando en endpoint Transaction con paginación...');
            $foundInTransactions = false;
            $totalTransactions = 0;
            $maxPages = 10; // Buscar en las primeras 10 páginas (1000 transacciones)

            for ($page = 0; $page < $maxPages; $page++) {
                $offset = $page * 100;

                $transactionRequest = new \Payrexx\Models\Request\Transaction();
                $transactionRequest->setLimit(100); // MÁXIMO PERMITIDO
                $transactionRequest->setOffset($offset);

                $transactions = $payrexx->getAll($transactionRequest);
                $pageCount = count($transactions);
                $totalTransactions += $pageCount;

                Log::info("Página " . ($page + 1) . " de Transaction: $pageCount transacciones");

                if ($pageCount === 0) {
                    break; // No hay más transacciones
                }

                // Buscar 3056 en esta página
                foreach ($transactions as $tx) {
                    if (str_contains($tx->getReferenceId(), '3056')) {
                        $results['booking_3056']['found_in_transactions'] = true;
                        $results['booking_3056']['details'] = [
                            'endpoint' => 'Transaction',
                            'page' => $page + 1,
                            'id' => $tx->getId(),
                            'reference' => $tx->getReferenceId(),
                            'amount' => $tx->getAmount() / 100,
                            'status' => $tx->getStatus()
                        ];
                        Log::info('✅ 3056 encontrada en Transaction endpoint, página ' . ($page + 1));
                        $foundInTransactions = true;
                        break 2; // Salir de ambos loops
                    }
                }

                // Si esta página tiene menos de 100, es la última
                if ($pageCount < 100) {
                    break;
                }
            }

            $results['total_counts']['transactions'] = $totalTransactions;
            $results['pagination_info']['transaction_pages_searched'] = $page + 1;

            // 2. BUSCAR EN INVOICES (si no se encontró en Transaction)
            if (!$foundInTransactions) {
                Log::info('Buscando en endpoint Invoice...');
                try {
                    $totalInvoices = 0;
                    $foundInInvoices = false;

                    for ($page = 0; $page < $maxPages; $page++) {
                        $offset = $page * 100;

                        $invoiceRequest = new \Payrexx\Models\Request\Invoice();
                        $invoiceRequest->setLimit(100);
                        $invoiceRequest->setOffset($offset);

                        $invoices = $payrexx->getAll($invoiceRequest);
                        $pageCount = count($invoices);
                        $totalInvoices += $pageCount;

                        Log::info("Página " . ($page + 1) . " de Invoice: $pageCount invoices");

                        if ($pageCount === 0) {
                            break;
                        }

                        foreach ($invoices as $inv) {
                            if (method_exists($inv, 'getReferenceId') && str_contains($inv->getReferenceId(), '3056')) {
                                $results['booking_3056']['found_in_invoices'] = true;
                                $results['booking_3056']['details'] = [
                                    'endpoint' => 'Invoice',
                                    'page' => $page + 1,
                                    'id' => method_exists($inv, 'getId') ? $inv->getId() : 'N/A',
                                    'reference' => $inv->getReferenceId(),
                                    'amount' => method_exists($inv, 'getAmount') ? $inv->getAmount() / 100 : 'N/A',
                                    'status' => method_exists($inv, 'getStatus') ? $inv->getStatus() : 'N/A'
                                ];
                                Log::info('✅ 3056 encontrada en Invoice endpoint, página ' . ($page + 1));
                                $foundInInvoices = true;
                                break 2;
                            }
                        }

                        if ($pageCount < 100) {
                            break;
                        }
                    }

                    $results['total_counts']['invoices'] = $totalInvoices;
                    $results['pagination_info']['invoice_pages_searched'] = $page + 1;

                } catch (\Exception $e) {
                    Log::info('❌ Error accediendo a Invoice endpoint: ' . $e->getMessage());
                    $results['invoice_error'] = $e->getMessage();
                }
            }

            // 3. BUSCAR EN GATEWAYS (si no se encontró en anteriores)
            if (!$foundInTransactions && !($results['booking_3056']['found_in_invoices'] ?? false)) {
                Log::info('Buscando en endpoint Gateway...');
                try {
                    $totalGateways = 0;

                    for ($page = 0; $page < 5; $page++) { // Menos páginas para Gateway
                        $offset = $page * 100;

                        $gatewayRequest = new \Payrexx\Models\Request\Gateway();
                        $gatewayRequest->setLimit(100);
                        $gatewayRequest->setOffset($offset);

                        $gateways = $payrexx->getAll($gatewayRequest);
                        $pageCount = count($gateways);
                        $totalGateways += $pageCount;

                        Log::info("Página " . ($page + 1) . " de Gateway: $pageCount gateways");

                        if ($pageCount === 0) {
                            break;
                        }

                        foreach ($gateways as $gw) {
                            if (method_exists($gw, 'getReferenceId') && str_contains($gw->getReferenceId(), '3056')) {
                                $results['booking_3056']['found_in_gateways'] = true;
                                $results['booking_3056']['details'] = [
                                    'endpoint' => 'Gateway',
                                    'page' => $page + 1,
                                    'id' => method_exists($gw, 'getId') ? $gw->getId() : 'N/A',
                                    'reference' => $gw->getReferenceId(),
                                    'amount' => method_exists($gw, 'getAmount') ? $gw->getAmount() / 100 : 'N/A',
                                    'status' => method_exists($gw, 'getStatus') ? $gw->getStatus() : 'N/A'
                                ];
                                Log::info('✅ 3056 encontrada en Gateway endpoint, página ' . ($page + 1));
                                break 2;
                            }
                        }

                        if ($pageCount < 100) {
                            break;
                        }
                    }

                    $results['total_counts']['gateways'] = $totalGateways;
                    $results['pagination_info']['gateway_pages_searched'] = $page + 1;

                } catch (\Exception $e) {
                    Log::info('❌ Error accediendo a Gateway endpoint: ' . $e->getMessage());
                    $results['gateway_error'] = $e->getMessage();
                }
            }

            // 4. BUSCAR POR ID ESPECÍFICO
            Log::info('Buscando por ID específico 68a7cad5...');
            try {
                $transactionRequest = new \Payrexx\Models\Request\Transaction();
                $transactionRequest->setId('68a7cad5');
                $specificTransaction = $payrexx->getOne($transactionRequest);

                if ($specificTransaction) {
                    $results['search_by_id'] = [
                        'found' => true,
                        'id' => $specificTransaction->getId(),
                        'reference' => $specificTransaction->getReferenceId(),
                        'amount' => $specificTransaction->getAmount() / 100,
                        'status' => $specificTransaction->getStatus()
                    ];
                    Log::info('✅ 3056 encontrada por ID específico');
                } else {
                    $results['search_by_id'] = ['found' => false];
                    Log::info('❌ 3056 NO encontrada por ID específico');
                }
            } catch (\Exception $e) {
                $results['search_by_id'] = [
                    'found' => false,
                    'error' => $e->getMessage()
                ];
                Log::info('❌ Error buscando por ID: ' . $e->getMessage());
            }

            // RESUMEN FINAL
            $foundAnywhere = $results['booking_3056']['found_in_transactions'] ||
                $results['booking_3056']['found_in_invoices'] ||
                $results['booking_3056']['found_in_gateways'] ||
                ($results['search_by_id']['found'] ?? false);

            Log::info('📊 RESUMEN BÚSQUEDA 3056', [
                'found_anywhere' => $foundAnywhere,
                'found_in' => $results['booking_3056']['details']['endpoint'] ?? 'NOWHERE',
                'total_counts' => $results['total_counts'],
                'pagination_info' => $results['pagination_info']
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Error en verificación rápida 3056: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * MÉTODO MEJORADO: Verificar transacciones específicas de una reserva con detección de test
     */
    public static function verifyBookingPayrexxTransactions($booking)
    {
        $verification = [
            'booking_id' => $booking->id,
            'has_payrexx_payments' => false,
            'overall_status' => 'no_payrexx_payments',
            'verification_summary' => [
                'total_payments' => 0,
                'total_checked' => 0,
                'found_in_payrexx' => 0,
                'missing_in_payrexx' => 0,
                'amount_discrepancies' => 0,
                'test_transactions' => 0  // NUEVO: contador de test
            ],
            'payment_details' => [],
            'issues_detected' => [],
            'test_analysis' => [  // NUEVA SECCIÓN
                'total_test_transactions' => 0,
                'test_confidence_distribution' => ['high' => 0, 'medium' => 0, 'low' => 0],
                'test_indicators_summary' => []
            ]
        ];

        try {
            $school = $booking->school;
            if (!$school->getPayrexxInstance() || !$school->getPayrexxKey()) {
                $verification['overall_status'] = 'no_payrexx_credentials';
                return $verification;
            }

            $payrexxPayments = $booking->payments()->whereNotNull('payrexx_reference')->get();

            if ($payrexxPayments->isEmpty()) {
                return $verification;
            }

            $verification['has_payrexx_payments'] = true;
            $verification['verification_summary']['total_payments'] = $payrexxPayments->count();

            foreach ($payrexxPayments as $payment) {
                $verification['verification_summary']['total_checked']++;

                $paymentVerification = [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'reference' => $payment->payrexx_reference,
                    'status' => $payment->status,
                    'found_in_payrexx' => false,
                    'amount_matches' => false,
                    'status_matches' => false,
                    'payrexx_amount' => null,
                    'payrexx_status' => null,
                    'issues' => [],
                    'test_detection' => null  // NUEVO: detección de test
                ];

                // PASO 1: DETECTAR SI ES TRANSACCIÓN DE TEST
                $testDetection = self::detectTestTransaction($payment, $school);
                $paymentVerification['test_detection'] = $testDetection;

                if ($testDetection['is_test_transaction']) {
                    $verification['verification_summary']['test_transactions']++;
                    $verification['test_analysis']['total_test_transactions']++;
                    $verification['test_analysis']['test_confidence_distribution'][$testDetection['confidence_level']]++;

                    // Recopilar indicadores de test
                    foreach ($testDetection['test_indicators'] as $indicator) {
                        $verification['test_analysis']['test_indicators_summary'][$indicator] =
                            ($verification['test_analysis']['test_indicators_summary'][$indicator] ?? 0) + 1;
                    }
                }

                // PASO 2: VERIFICAR EN PAYREXX (si no es test de alta confianza)
                if (!$testDetection['is_test_transaction'] || $testDetection['confidence_level'] === 'low') {
                    $payrexxVerification = self::verifyPaymentInPayrexx($payment, $school);

                    if ($payrexxVerification['found']) {
                        $paymentVerification['found_in_payrexx'] = true;
                        $paymentVerification['payrexx_amount'] = $payrexxVerification['amount'];
                        $paymentVerification['payrexx_status'] = $payrexxVerification['status'];
                        $paymentVerification['amount_matches'] = abs($payment->amount - $payrexxVerification['amount']) < 0.01;
                        $paymentVerification['status_matches'] = self::statusMatches($payment->status, $payrexxVerification['status']);

                        $verification['verification_summary']['found_in_payrexx']++;

                        if (!$paymentVerification['amount_matches']) {
                            $verification['verification_summary']['amount_discrepancies']++;
                            $paymentVerification['issues'][] = 'amount_mismatch';
                        }
                    } else {
                        $verification['verification_summary']['missing_in_payrexx']++;
                        $paymentVerification['issues'][] = 'not_found_in_payrexx';
                    }
                } else {
                    // Para transacciones de test de alta confianza, no verificar en Payrexx
                    $paymentVerification['issues'][] = 'test_transaction_not_verified';
                }

                $verification['payment_details'][] = $paymentVerification;
            }

            // DETERMINAR ESTADO GENERAL
            $verification['overall_status'] = self::determineOverallStatus($verification['verification_summary']);

        } catch (\Exception $e) {
            Log::error('Error en verificación de Payrexx: ' . $e->getMessage(), [
                'booking_id' => $booking->id
            ]);
            $verification['overall_status'] = 'error';
            $verification['error'] = $e->getMessage();
        }

        return $verification;
    }
    /**
     * MÉTODO FALTANTE: Determinar estado general de verificación
     */
    private static function determineOverallStatus(array $verificationSummary): string
    {
        $totalChecked = $verificationSummary['total_checked'] ?? 0;
        $foundInPayrexx = $verificationSummary['found_in_payrexx'] ?? 0;
        $missingInPayrexx = $verificationSummary['missing_in_payrexx'] ?? 0;
        $amountDiscrepancies = $verificationSummary['amount_discrepancies'] ?? 0;
        $testTransactions = $verificationSummary['test_transactions'] ?? 0;

        // Si no hay transacciones para verificar
        if ($totalChecked === 0) {
            return 'no_transactions';
        }

        // Si todas son transacciones de test
        if ($testTransactions === $totalChecked) {
            return 'all_test_transactions';
        }

        // Si hay errores graves (muchas transacciones faltantes)
        if ($missingInPayrexx > $foundInPayrexx) {
            return 'critical_missing_transactions';
        }

        // Si hay algunas transacciones faltantes pero no crítico
        if ($missingInPayrexx > 0) {
            return 'missing_transactions';
        }

        // Si hay discrepancias de importes
        if ($amountDiscrepancies > 0) {
            return 'amount_mismatches';
        }

        // Si todo está verificado correctamente
        if ($foundInPayrexx === $totalChecked && $amountDiscrepancies === 0) {
            return 'all_verified';
        }

        // Si hay problemas mixtos
        if ($amountDiscrepancies > 0 || $missingInPayrexx > 0) {
            return 'partial_issues';
        }

        // Estado por defecto
        return 'unknown';
    }



    /**
     * NUEVO MÉTODO: Detectar si una transacción es de test
     */
    private static function detectTestTransaction($payment, $school)
    {
        $testDetection = [
            'is_test_transaction' => false,
            'confidence_level' => 'low',
            'test_indicators' => [],
            'test_card_type' => null,
            'reasoning' => []
        ];

        try {
            // VERIFICAR SI HAY DATOS DE TRANSACCIÓN GUARDADOS
            $transactionData = $payment->getPayrexxTransaction();

            if (!empty($transactionData)) {
                // DETECTAR POR TARJETAS DE TEST CONOCIDAS
                $cardInfo = self::extractCardInfo($transactionData);

                if ($cardInfo['is_test_card']) {
                    $testDetection['is_test_transaction'] = true;
                    $testDetection['confidence_level'] = 'high';
                    $testDetection['test_card_type'] = $cardInfo['card_type'];
                    $testDetection['test_indicators'][] = 'known_test_card';
                    $testDetection['reasoning'][] = "Tarjeta de test detectada: {$cardInfo['card_type']}";
                }

                // DETECTAR POR PSP DE TEST
                if (isset($transactionData['psp'])) {
                    $psp = is_array($transactionData['psp']) ? $transactionData['psp'][0] ?? [] : [];
                    $pspName = $psp['name'] ?? '';

                    if (stripos($pspName, 'test') !== false || stripos($pspName, 'dummy') !== false) {
                        $testDetection['is_test_transaction'] = true;
                        $testDetection['confidence_level'] = 'high';
                        $testDetection['test_indicators'][] = 'test_psp';
                        $testDetection['reasoning'][] = "PSP de test: {$pspName}";
                    }
                }

                // DETECTAR POR INVOICE ID DE TEST
                if (isset($transactionData['invoice']['referenceId'])) {
                    $ref = $transactionData['invoice']['referenceId'];
                    if (stripos($ref, 'test') !== false) {
                        $testDetection['test_indicators'][] = 'test_invoice_reference';
                        $testDetection['reasoning'][] = "Referencia de invoice contiene 'test'";
                        if ($testDetection['confidence_level'] === 'low') {
                            $testDetection['confidence_level'] = 'medium';
                        }
                    }
                }
            }

            // VERIFICAR POR REFERENCIA DE PAGO
            if (stripos($payment->payrexx_reference ?? '', 'test') !== false) {
                $testDetection['test_indicators'][] = 'test_payment_reference';
                $testDetection['reasoning'][] = 'Referencia de pago contiene "test"';
                if ($testDetection['confidence_level'] === 'low') {
                    $testDetection['confidence_level'] = 'medium';
                    $testDetection['is_test_transaction'] = true;
                }
            }

            // VERIFICAR POR AMBIENTE
            if (env('APP_ENV') !== 'production') {
                $testDetection['test_indicators'][] = 'non_production_environment';
                $testDetection['reasoning'][] = 'Ambiente no es producción';
                if ($testDetection['confidence_level'] === 'low') {
                    $testDetection['confidence_level'] = 'medium';
                    $testDetection['is_test_transaction'] = true;
                }
            }

            // VERIFICAR POR IMPORTES TÍPICOS DE TEST
            $testAmounts = [1, 5, 10, 100, 1.00, 5.00, 10.00, 100.00];
            if (in_array($payment->amount, $testAmounts)) {
                $testDetection['test_indicators'][] = 'common_test_amount';
                $testDetection['reasoning'][] = "Importe típico de test: {$payment->amount}€";
            }

            // VERIFICAR POR CLIENTE DE TEST (si está disponible)
            $booking = $payment->booking ?? null;
            if ($booking && $booking->clientMain) {
                $client = $booking->clientMain;
                $confirmedTestClientIds = [18956, 14479, 13583, 13524, 10358, 10735];

                if (in_array($client->id, $confirmedTestClientIds)) {
                    $testDetection['is_test_transaction'] = true;
                    $testDetection['confidence_level'] = 'high';
                    $testDetection['test_indicators'][] = 'confirmed_test_client';
                    $testDetection['reasoning'][] = "Cliente confirmado como test (ID: {$client->id})";
                }
            }

            // AJUSTAR CONFIANZA FINAL
            if (!$testDetection['is_test_transaction'] && count($testDetection['test_indicators']) >= 2) {
                $testDetection['is_test_transaction'] = true;
                $testDetection['confidence_level'] = 'medium';
            }

            Log::info("Detección de test completada", [
                'payment_id' => $payment->id,
                'is_test' => $testDetection['is_test_transaction'],
                'confidence' => $testDetection['confidence_level'],
                'indicators' => $testDetection['test_indicators']
            ]);

        } catch (\Exception $e) {
            Log::warning("Error en detección de test: " . $e->getMessage());
            $testDetection['test_indicators'][] = 'detection_error';
            $testDetection['reasoning'][] = 'Error en análisis: ' . $e->getMessage();
        }

        return $testDetection;
    }

    /**
     * NUEVO MÉTODO: Extraer información de tarjeta para detectar test
     */
    private static function extractCardInfo($transactionData)
    {
        $cardInfo = [
            'is_test_card' => false,
            'card_type' => null,
            'card_number_hint' => null
        ];

        try {
            // TARJETAS DE TEST CONOCIDAS DE PAYREXX/STRIPE
            $knownTestCards = [
                '4242424242424242' => 'Visa Test',
                '4000000000000002' => 'Visa Declined Test',
                '5555555555554444' => 'Mastercard Test',
                '2223003122003222' => 'Mastercard 2-series Test',
                '5200828282828210' => 'Mastercard Debit Test',
                '4000000000000069' => 'Visa Expired Test',
                '4000000000000127' => 'Visa CVC Fail Test',
                '4000000000000119' => 'Visa Processing Error Test'
            ];

            // BUSCAR EN DIFERENTES UBICACIONES DE LOS DATOS
            $possibleCardLocations = [
                $transactionData['creditcard']['number'] ?? null,
                $transactionData['card']['number'] ?? null,
                $transactionData['payment']['card']['number'] ?? null,
                $transactionData['psp'][0]['card']['number'] ?? null
            ];

            foreach ($possibleCardLocations as $cardNumber) {
                if ($cardNumber && isset($knownTestCards[$cardNumber])) {
                    $cardInfo['is_test_card'] = true;
                    $cardInfo['card_type'] = $knownTestCards[$cardNumber];
                    $cardInfo['card_number_hint'] = '****' . substr($cardNumber, -4);
                    break;
                }

                // VERIFICAR PATRONES DE TARJETAS DE TEST
                if ($cardNumber && self::isTestCardPattern($cardNumber)) {
                    $cardInfo['is_test_card'] = true;
                    $cardInfo['card_type'] = 'Test Card Pattern';
                    $cardInfo['card_number_hint'] = '****' . substr($cardNumber, -4);
                    break;
                }
            }

            // VERIFICAR POR NOMBRE DE PORTADOR
            $cardHolderNames = [
                $transactionData['creditcard']['cardholder'] ?? null,
                $transactionData['card']['holder'] ?? null,
                $transactionData['payment']['card']['holder'] ?? null
            ];

            foreach ($cardHolderNames as $holderName) {
                if ($holderName && (stripos($holderName, 'test') !== false || stripos($holderName, 'dummy') !== false)) {
                    $cardInfo['is_test_card'] = true;
                    $cardInfo['card_type'] = $cardInfo['card_type'] ?? 'Test Cardholder Name';
                    break;
                }
            }

        } catch (\Exception $e) {
            Log::warning('Error extrayendo info de tarjeta: ' . $e->getMessage());
        }

        return $cardInfo;
    }

    /**
     * NUEVO MÉTODO: Verificar patrones de tarjetas de test
     */
    private static function isTestCardPattern($cardNumber)
    {
        // PATRONES COMUNES DE TARJETAS DE TEST
        $testPatterns = [
            '/^4242424242424242$/',  // Visa clásica de test
            '/^4000000000000002$/',  // Visa declined
            '/^4000[0-9]{12}$/',     // Patrón Visa test general
            '/^5555555555554444$/',  // Mastercard test
            '/^5200828282828210$/',  // Mastercard debit test
        ];

        foreach ($testPatterns as $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return true;
            }
        }

        return false;
    }

    /**
     * MÉTODO MEJORADO: Verificar pago específico en Payrexx
     */
    private static function verifyPaymentInPayrexx($payment, $school)
    {
        $verification = [
            'found' => false,
            'amount' => null,
            'status' => null,
            'transaction_id' => null
        ];

        try {
            $transactionData = $payment->getPayrexxTransaction();

            if (isset($transactionData['id'])) {
                $payrexxTransaction = self::retrieveTransaction(
                    $school->getPayrexxInstance(),
                    $school->getPayrexxKey(),
                    $transactionData['id']
                );

                if ($payrexxTransaction) {
                    $verification['found'] = true;
                    $verification['amount'] = $payrexxTransaction->getAmount() / 100;
                    $verification['status'] = $payrexxTransaction->getStatus();
                    $verification['transaction_id'] = $payrexxTransaction->getId();
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error verificando pago en Payrexx: " . $e->getMessage());
        }

        return $verification;
    }

    /**
     * MÉTODO MEJORADO: Análisis de Payrexx excluyendo test
     */
    public static function analyzeBookingsWithPayrexxExcludingTest($bookings, $startDate, $endDate)
    {
        $analysis = [
            'total_bookings' => $bookings->count(),
            'production_bookings' => 0,
            'test_bookings' => 0,
            'cancelled_bookings' => 0,
            'total_system_amount' => 0,
            'total_payrexx_amount' => 0,
            'production_system_amount' => 0,
            'production_payrexx_amount' => 0,
            'test_system_amount' => 0,
            'test_payrexx_amount' => 0,
            'total_discrepancies' => 0,
            'booking_comparisons' => [],
            'payrexx_transactions' => [],
            'test_transactions_excluded' => [],
            'unmatched_payrexx_transactions' => []
        ];

        // Obtener transacciones de Payrexx
        $schoolsProcessed = [];
        $allPayrexxTransactions = [];

        foreach ($bookings as $booking) {
            $schoolId = $booking->school_id;

            if (!in_array($schoolId, $schoolsProcessed)) {
                $schoolTransactions = self::getTransactionsByDateRange($booking->school, $startDate, $endDate);
                $allPayrexxTransactions = array_merge($allPayrexxTransactions, $schoolTransactions);
                $analysis['payrexx_transactions'] = array_merge($analysis['payrexx_transactions'], $schoolTransactions);
                $schoolsProcessed[] = $schoolId;
            }
        }

        // Analizar cada reserva con clasificación
        foreach ($bookings as $booking) {
            $bookingComparison = self::compareBookingWithPayrexxClassified($booking, $allPayrexxTransactions);

            $analysis['booking_comparisons'][] = $bookingComparison;

            // CLASIFICAR POR TIPO DE RESERVA
            $bookingType = $bookingComparison['booking_classification'];

            switch ($bookingType) {
                case 'production':
                    $analysis['production_bookings']++;
                    $analysis['production_system_amount'] += $bookingComparison['total_system_amount'];
                    $analysis['production_payrexx_amount'] += $bookingComparison['total_payrexx_amount'];
                    $analysis['total_system_amount'] += $bookingComparison['total_system_amount'];
                    $analysis['total_payrexx_amount'] += $bookingComparison['total_payrexx_amount'];
                    break;

                case 'test':
                    $analysis['test_bookings']++;
                    $analysis['test_system_amount'] += $bookingComparison['total_system_amount'];
                    $analysis['test_payrexx_amount'] += $bookingComparison['total_payrexx_amount'];
                    // NO añadir a totales principales
                    $analysis['test_transactions_excluded'][] = $bookingComparison;
                    break;

                case 'cancelled':
                    $analysis['cancelled_bookings']++;
                    // Las canceladas sí se incluyen en totales para verificar procesamiento
                    $analysis['total_system_amount'] += $bookingComparison['total_system_amount'];
                    $analysis['total_payrexx_amount'] += $bookingComparison['total_payrexx_amount'];
                    break;
            }

            if ($bookingComparison['has_discrepancy']) {
                $analysis['total_discrepancies']++;
            }
        }

        // Redondear totales
        foreach (['total_system_amount', 'total_payrexx_amount', 'production_system_amount',
                     'production_payrexx_amount', 'test_system_amount', 'test_payrexx_amount'] as $key) {
            $analysis[$key] = round($analysis[$key], 2);
        }

        $analysis['total_difference'] = round($analysis['total_system_amount'] - $analysis['total_payrexx_amount'], 2);
        $analysis['production_difference'] = round($analysis['production_system_amount'] - $analysis['production_payrexx_amount'], 2);

        Log::info("Análisis de Payrexx con clasificación completado", [
            'total_bookings' => $analysis['total_bookings'],
            'production_bookings' => $analysis['production_bookings'],
            'test_bookings' => $analysis['test_bookings'],
            'production_amount' => $analysis['production_system_amount'],
            'test_amount_excluded' => $analysis['test_system_amount']
        ]);

        return $analysis;
    }

    /**
     * NUEVO MÉTODO: Comparar reserva con Payrexx incluyendo clasificación
     */
    private static function compareBookingWithPayrexxClassified($booking, $payrexxTransactions = null)
    {
        $comparison = self::compareBookingWithPayrexx($booking, $payrexxTransactions);

        // AÑADIR CLASIFICACIÓN DE LA RESERVA
        $comparison['booking_classification'] = self::classifyBookingForPayrexx($booking);
        $comparison['test_analysis'] = self::analyzeBookingTestStatus($booking);

        return $comparison;
    }

    /**
     * NUEVO MÉTODO: Clasificar reserva para análisis de Payrexx
     */
    private static function classifyBookingForPayrexx($booking)
    {
        // 1. VERIFICAR SI ES TEST
        $confirmedTestClientIds = [18956, 14479, 13583, 13524, 10358, 10735];
        $clientId = $booking->client_main_id;

        if (in_array($clientId, $confirmedTestClientIds)) {
            return 'test';
        }

        // 2. VERIFICAR TRANSACCIONES DE TEST
        $payrexxPayments = $booking->payments()->whereNotNull('payrexx_reference')->get();
        $testTransactions = 0;

        foreach ($payrexxPayments as $payment) {
            $testDetection = self::detectTestTransaction($payment, $booking->school);
            if ($testDetection['is_test_transaction'] && $testDetection['confidence_level'] !== 'low') {
                $testTransactions++;
            }
        }

        if ($testTransactions > 0 && $testTransactions === $payrexxPayments->count()) {
            return 'test';
        }

        // 3. VERIFICAR SI ESTÁ CANCELADA
        if ($booking->status == 2) {
            return 'cancelled';
        }

        // 4. TODO LO DEMÁS ES PRODUCCIÓN
        return 'production';
    }

    /**
     * NUEVO MÉTODO: Analizar estado de test de una reserva
     */
    private static function analyzeBookingTestStatus($booking)
    {
        $analysis = [
            'has_test_indicators' => false,
            'test_confidence' => 'none',
            'test_reasons' => []
        ];

        try {
            // Verificar cliente
            $confirmedTestClientIds = [18956, 14479, 13583, 13524, 10358, 10735];
            if (in_array($booking->client_main_id, $confirmedTestClientIds)) {
                $analysis['has_test_indicators'] = true;
                $analysis['test_confidence'] = 'high';
                $analysis['test_reasons'][] = "Cliente confirmado test (ID: {$booking->client_main_id})";
            }

            // Verificar transacciones
            $payrexxPayments = $booking->payments()->whereNotNull('payrexx_reference')->get();
            foreach ($payrexxPayments as $payment) {
                $testDetection = self::detectTestTransaction($payment, $booking->school);
                if ($testDetection['is_test_transaction']) {
                    $analysis['has_test_indicators'] = true;
                    if ($analysis['test_confidence'] === 'none') {
                        $analysis['test_confidence'] = $testDetection['confidence_level'];
                    }
                    $analysis['test_reasons'] = array_merge($analysis['test_reasons'], $testDetection['reasoning']);
                }
            }

        } catch (\Exception $e) {
            Log::warning("Error analizando test status: " . $e->getMessage());
        }

        return $analysis;
    }
}
