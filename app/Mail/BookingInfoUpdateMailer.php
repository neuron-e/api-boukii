<?php

/**
 * Class BookingCreateMailer
 */

namespace App\Mail;

use App\Models\Mail;
use Illuminate\Mail\Mailable;
use App\Support\LocaleHelper;

class BookingInfoUpdateMailer extends Mailable
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

        $templateView = 'mailsv2.newBookingInfoChange';
        $footerView = 'mailsv2.newFooter';


        $templateMail = Mail::where('type', 'booking_change')->where('school_id', $this->schoolData->id)
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
        $priceTotalStored = (float) ($this->bookingData->price_total ?? 0);
        $priceTva = (float) ($this->bookingData->price_tva ?? 0);
        $priceReduction = (float) ($this->bookingData->price_reduction ?? 0);
        $hasReduction = !empty($this->bookingData->has_reduction) && $priceReduction > 0;
        $displayTotal = $priceTotalStored;
        if (!$voucherIncludedInPrice && $voucherUsed > 0) {
            $displayTotal = max(0, $displayTotal - $voucherUsed);
        }
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
            'reference' => '#' . $this->bookingData->id,
            'bookingNotes' => $this->bookingData->notes,
            'booking' => $this->bookingData,
            'bookings' => $this->bookingData->bookingUsers,
            'courses' => $this->bookingData->parseBookedGroupedWithCourses(),
            'groupedActivities' => $groupedActivities,
            'hasCancellationInsurance' => $this->bookingData->has_cancellation_insurance,
            'actionURL' => null,
            'footerView' => $footerView,
            'voucherUsed' => $voucherUsed,
            'voucherIncludedInPrice' => $voucherIncludedInPrice,
            'displayTotal' => number_format($displayTotal, 2, '.', ''),
            'displaySubtotal' => number_format($displaySubtotal, 2, '.', '')
        ];

        $subject = __('emails.bookingInfo.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->userData->email)
                    ->subject($subject)
                    ->view($templateView)->with($templateData);
    }
}
