<?php

/**
 * Class BookingCancelMailer
 */

namespace App\Mail;

use App\Models\Mail;
use Illuminate\Mail\Mailable;

use App\Models\Language;

/**
 * When a Booking is cancelled (either all of it, or just one of its sub-bookings), inform the buyer.
 * @see \App\Http\Controllers\Admin\BookingController::cancelBookingFull() + cancelBookingUser()
 */
class BookingCancelMailer extends Mailable
{
    private $schoolData;
    private $bookingData;
    private $cancelledLines;
    private $userData;
    private $voucherData;


    /**
     * Create a new message instance.
     *
     * @param \App\Models\School $schoolData Where it was bought
     * @param \App\Models\Booking $bookingData That was cancelled
     * @param mixed[] $cancelledLines Alike Bookings2->parseBookedCourses()
     * @param \App\Models\User $userData Who
     * @return void
     */
    public function __construct($schoolData, $bookingData, $cancelledLines, $userData, $voucherData)
    {
        $this->schoolData = $schoolData;
        $this->bookingData = $bookingData;
        $this->cancelledLines = $cancelledLines;
        $this->userData = $userData;
        $this->voucherData = $voucherData;
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

        $templateView = 'mailsv2.BookingCancel';
        $footerView = 'mailsv2.footer';

        $templateMail = Mail::where('type', 'booking_cancel')->where('school_id', $this->schoolData->id)
            ->where('lang', $userLocale)->first();

        $voucherCode = "";
        if(isset($this->voucherData->code)) $voucherCode = $this->voucherData->code;
        $voucherAmount = "";
        if(isset($this->voucherData->quantity)) $voucherAmount = number_format($this->voucherData->quantity, 2);

        $templateData = [
            'titleTemplate' => $templateMail ? $templateMail->title : '',
            'bodyTemplate' => $templateMail ? $templateMail->body: '',
            'userName' => trim($this->userData->first_name . ' ' . $this->userData->last_name),
            'schoolName' => $this->schoolData->name,
            'schoolLogo' => $this->schoolData->logo,
            'schoolEmail' => $this->schoolData->contact_email,
            'schoolConditionsURL' => $this->schoolData->conditions_url,
            'reference' => '#' . $this->bookingData->id,
            'bookingNotes' => $this->bookingData->notes,
            'booking' => $this->bookingData,
            'courses' => $this->parseBookedGroupedCourses($this->cancelledLines),
            'voucherCode' => $voucherCode,
            'voucherAmount' => $voucherAmount,
            'actionURL' => null,
            'footerView' => $footerView
        ];

        $subject = __('emails.bookingCancel.subject');
        \App::setLocale($oldLocale);

        return $this->to($this->userData->email)
                    ->subject($subject)
                    ->view($templateView)->with($templateData);
    }

    public function parseBookedGroupedCourses($bookingUsers)
    {

        $groupedCourses = $bookingUsers->groupBy(['course.course_type', 'client_id',
            'course_id', 'degree_id', 'course_date_id']);

        return $groupedCourses;
    }
}
