<?php

/**
 * Class BookingCreateMailer
 */

namespace App\Mail;

use App\Models\Mail;
use Illuminate\Mail\Mailable;
use App\Support\LocaleHelper;

class BookingInfoMailer extends Mailable
{
    private $schoolData;
    private $bookingData;
    private $userData;


    /**
     * Create a new message instance.
     *
     * @param \App\Models\School $schoolData Where it was bought
     * @param \App\Models\Booking $bookingData What
     * @param \App\Models\User $userData Who
     * @return void
     */
    public function __construct($schoolData, $bookingData, $userData)
    {
        $this->schoolData = $schoolData;
        $this->bookingData = $bookingData;
        $this->userData = $userData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $oldLocale = \App::getLocale();
        $userLocale = LocaleHelper::resolve(null, $this->userData, $this->bookingData);
        \App::setLocale($userLocale);

        $templateView = 'mailsv2.newBookingInfo';
        $footerView = 'mailsv2.newFooter';

        $templateMail = Mail::where('type', 'booking_confirm')->where('school_id', $this->schoolData->id)
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
            'reference' => '#' . $this->bookingData->id,
            'bookingNotes' => $this->bookingData->notes,
            'booking' => $this->bookingData,
            'bookings' => $this->bookingData->bookingUsers,
            'courses' => $this->bookingData->parseBookedGroupedWithCourses(),
            'hasCancellationInsurance' => $this->bookingData->has_cancellation_insurance,
            'actionURL' => null,
            'footerView' => $footerView
        ];

        $subject = __('emails.bookingInfo.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->userData->email)
                    ->subject($subject)
                    ->view($templateView)->with($templateData);
    }
}
