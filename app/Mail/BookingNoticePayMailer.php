<?php

/**
 * Class BookingNoticePayMailer
 */

namespace App\Mail;

use App\Models\Mail;
use Illuminate\Mail\Mailable;
use App\Support\LocaleHelper;

class BookingNoticePayMailer extends Mailable
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

        $templateView = 'mailsv2.newBookingPayNotice';
        $footerView = 'mailsv2.newFooter';

        $templateMail = Mail::where('type', 'payment_reminder')->where('school_id', $this->schoolData->id)
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
            'reference' => $this->bookingData->payrexx_reference,
            'bookingNotes' => $this->bookingData->notes,
            'booking' => $this->bookingData,
            'courses' => $this->bookingData->parseBookedGroupedWithCourses(),
            'bookings' => $this->bookingData->bookingUsers,
            'client' => $this->bookingData->clientMain,
            'hasCancellationInsurance' => $this->bookingData->has_cancellation_insurance,
            'amount' => number_format($this->bookingData->price_total - $this->bookingData->paid_total, 2),
            'currency' => $this->bookingData->currency,
            'actionURL' => $this->payLink,
            'footerView' => $footerView
        ];

        $subject = __('emails.bookingNoticePay.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->userData->email)
                    ->subject($subject)
                    ->view($templateView)->with($templateData);

    }
}
