<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\School;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight helper around the Payyo API.
 *
 * The implementation purposely mirrors the methods that exist in the
 * PayrexxHelpers class so that controllers can swap between both payment
 * providers with minimal changes.
 */
class PayyoHelpers
{
    /**
     * Create a Payyo payment link for a booking.
     *
     * @param School       $schoolData Who will receive the payment
     * @param Booking      $bookingData Booking associated with the payment
     * @param array        $basketData Line items to be paid
     * @param Client|null  $buyerUser  Optional buyer information
     * @param string|null  $redirectTo Optional redirect URL after payment
     *
     * @return string URL for the Payyo hosted payment page
     */
    public static function createPayLink(
        School $schoolData,
        Booking $bookingData,
        $basketData = [],
        Client $buyerUser = null,
        ?string $redirectTo = null
    ) {
        $link = '';

        try {
            if (!$schoolData->getPayyoInstance() || !$schoolData->getPayyoKey()) {
                throw new \Exception('No credentials for School ID=' . $schoolData->id);
            }

            $client = new HttpClient();

            $payload = [
                'referenceId' => $bookingData->getOrGeneratePayyoReference(),
                'amount' => $bookingData->price_total * 100,
                'currency' => $bookingData->currency,
            ];

            if ($redirectTo) {
                $payload['successRedirectUrl'] = $redirectTo . '?status=success';
                $payload['failedRedirectUrl'] = $redirectTo . '?status=failed';
                $payload['cancelRedirectUrl'] = $redirectTo . '?status=cancel';
            }

            if ($buyerUser) {
                $payload['contact'] = [
                    'forename' => $buyerUser->first_name,
                    'surname' => $buyerUser->last_name,
                    'email' => $buyerUser->email,
                ];
            }

            if (!empty($basketData)) {
                $payload['basket'] = $basketData;
            }

            $response = $client->post(
                'https://' . $schoolData->getPayyoInstance() . '/api/v1/payments',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $schoolData->getPayyoKey(),
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                ]
            );

            $data = json_decode((string) $response->getBody(), true);
            if (isset($data['link'])) {
                $link = $data['link'];
            }
        } catch (\Throwable $e) {
            Log::channel('payyo')->error('PayyoHelpers createPayLink Booking ID=' . $bookingData->id);
            Log::channel('payyo')->error($e->getMessage());
        }

        return $link;
    }

    /**
     * Retrieve a Payyo transaction.
     *
     * @param string $payyoInstance Merchant instance
     * @param string $payyoKey      API key
     * @param int    $transactionID Transaction identifier
     *
     * @return array|null
     */
    public static function retrieveTransaction($payyoInstance, $payyoKey, $transactionID)
    {
        try {
            $client = new HttpClient();
            $response = $client->get(
                'https://' . $payyoInstance . '/api/v1/transactions/' . $transactionID,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $payyoKey,
                        'Accept' => 'application/json',
                    ],
                ]
            );

            return json_decode((string) $response->getBody(), true);
        } catch (\Throwable $e) {
            Log::channel('payyo')->error('PayyoHelpers retrieveTransaction ID=' . $transactionID);
            Log::channel('payyo')->error($e->getMessage());
            return null;
        }
    }
}

