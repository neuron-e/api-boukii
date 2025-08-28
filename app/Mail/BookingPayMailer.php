<?php

/**
 * Class BookingPayMailer
 */

namespace App\Mail;

use App\Models\Mail;
use Illuminate\Mail\Mailable;

use App\Models\Language;
use Illuminate\Support\Facades\Log;

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

        // Apply that user's language - or default
        $defaultLocale = config('app.fallback_locale');
        $oldLocale = \App::getLocale();
        $userLang = Language::find( $this->userData->language1_id );
        $userLocale = $userLang ? $userLang->code : $defaultLocale;
        \App::setLocale($userLocale);

        $templateView = 'mailsv2.newBookingPay';
        $footerView = 'mailsv2.newFooter';

        $templateMail = Mail::where('type', 'payment_link')->where('school_id', $this->schoolData->id)
            ->where('lang', $userLocale)->first();

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
            'reference' => $this->bookingData->payrexx_reference ?? $this->bookingData->payyo_reference,
            'bookingNotes' => $this->bookingData->notes,
            'booking' => $this->bookingData,
            'courses' => $this->bookingData->parseBookedGroupedWithCourses(),
            'bookings' => $this->bookingData->bookingUsers,
            'hasCancellationInsurance' => $this->bookingData->has_cancellation_insurance,
            'amount' => number_format($this->bookingData->price_total - $this->bookingData->paid_total, 2),
            'currency' => $this->bookingData->currency,
            'actionURL' => $this->payLink,
            'footerView' => $footerView
        ];

        $subject = __('emails.bookingPay.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->userData->email)
            ->subject($subject)
            ->view($templateView)->with($templateData);

    }
}
