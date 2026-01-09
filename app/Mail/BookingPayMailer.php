<?php

/**
 * Class BookingPayMailer
 */

namespace App\Mail;

use App\Models\Mail;
use Illuminate\Mail\Mailable;
use App\Support\LocaleHelper;

/**
 * When a new Booking is created, and chose "Payment Online",
 * send buyer user an email with the payment details.
 * @see \App\Http\PayrexxHelpers::sendPayEmail()
 */
class BookingPayMailer extends Mailable
{
    private $schoolData;
    private $bookingData;
    private $userData;
    private $payLink;


    /**
     * Create a new message instance.
     *
     * @param \App\Models\School $schoolData Where it was bought
     * @param \App\Models\Booking $bookingData What
     * @param \App\Models\User $userData Who
     * @param string $payLink How
     * @return void
     */
    public function __construct($schoolData, $bookingData, $userData, $payLink)
    {
        $this->schoolData = $schoolData;
        $this->bookingData = $bookingData;
        $this->userData = $userData;
        $this->payLink = $payLink;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->bookingData->loadMissing(['clientMain']);
        $oldLocale = \App::getLocale();
        $userLocale = LocaleHelper::resolve(null, $this->userData, $this->bookingData);
        \App::setLocale($userLocale);

        $templateView = 'mailsv2.newBookingPay';
        $footerView = 'mailsv2.newFooter';

        $templateMail = Mail::where('type', 'payment_link')->where('school_id', $this->schoolData->id)
            ->where('lang', $userLocale)->first();

        $groupedActivities = $this->bookingData->buildGroupedActivitiesFromBookingUsers(
            $this->bookingData->bookingUsers
        );
        $voucherBalance = $this->bookingData->getCurrentBalance();
        $voucherUsed = (float) ($voucherBalance['total_vouchers_used'] ?? 0);
        if ($voucherUsed <= 0) {
            $voucherFallback = (float) $this->bookingData->vouchersLogs()
                ->where('amount', '>', 0)
                ->sum('amount');
            if ($voucherFallback > 0) {
                $voucherUsed = $voucherFallback;
            }
        }
        $voucherIncludedInPrice = $this->bookingData->priceIncludesVoucherDiscounts();
        $pendingAmount = $this->bookingData->getPendingAmount();
        $priceTotalStored = (float) ($this->bookingData->price_total ?? 0);
        $priceTva = (float) ($this->bookingData->price_tva ?? 0);
        $priceReduction = (float) ($this->bookingData->price_reduction ?? 0);
        $hasReduction = !empty($this->bookingData->has_reduction) && $priceReduction > 0;
        $displayTotal = (float) $pendingAmount;
        $displaySubtotal = max(0, $priceTotalStored - $priceTva);
        if ($hasReduction) {
            $displaySubtotal = max(0, $displaySubtotal + $priceReduction);
        }

        $templateData = [
            'titleTemplate' => $templateMail ? $templateMail->title : '',
            'bodyTemplate' => $templateMail ? $templateMail->body: '',
            'userName' => trim($this->userData->first_name . ' ' . $this->userData->last_name),
            'schoolName' => $this->schoolData->name,
            'schoolDescription' => $this->schoolData->description,
            'schoolLogo' => $this->schoolData->logo,
            'schoolEmail' => $this->schoolData->contact_email,
            'schoolPhone' =>  $this->schoolData->contact_phone,
            'schoolConditionsURL' => $this->schoolData->conditions_url,
            'reference' => $this->bookingData->payrexx_reference,
            'bookingNotes' => $this->bookingData->notes,
            'booking' => $this->bookingData,
            'courses' => $this->bookingData->parseBookedGroupedWithCourses(),
            'bookings' => $this->bookingData->bookingUsers,
            'client' => $this->bookingData->clientMain,
            'hasCancellationInsurance' => $this->bookingData->has_cancellation_insurance,
            'amount' => number_format($pendingAmount, 2),
            'currency' => $this->bookingData->currency,
            'actionURL' => $this->payLink,
            'footerView' => $footerView,
            'groupedActivities' => $groupedActivities,
            'voucherUsed' => $voucherUsed,
            'voucherIncludedInPrice' => $voucherIncludedInPrice,
            'displayTotal' => number_format($displayTotal, 2, '.', ''),
            'displaySubtotal' => number_format($displaySubtotal, 2, '.', '')
        ];

        $subject = __('emails.bookingPay.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->userData->email)
            ->subject($subject)
            ->view($templateView)->with($templateData);

    }
}
