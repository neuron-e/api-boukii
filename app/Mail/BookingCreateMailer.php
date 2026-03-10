<?php

/**
 * Class BookingCreateMailer
 */

namespace App\Mail;

use App\Models\Mail;
use App\Models\RentalReservation;
use Illuminate\Mail\Mailable;
use App\Support\LocaleHelper;

/**
 * When a new Booking is created, whatever the chosen payment method,
 * send buyer user the details.
 * @see \App\Http\Controllers\Admin\BookingController::createBooking()
 */
class BookingCreateMailer extends Mailable
{
    private $schoolData;
    private $bookingData;
    private $userData;
    private $paid;


    /**
     * Create a new message instance.
     *
     * @param \App\Models\School $schoolData Where it was bought
     * @param \App\Models\Booking $bookingData What
     * @param \App\Models\User $userData Who
     * @return void
     */
    public function __construct($schoolData, $bookingData, $userData, $paid)
    {
        $this->schoolData = $schoolData;
        $this->bookingData = $bookingData;
        $this->userData = $userData;
        $this->paid = $paid;
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

        $templateView = 'mailsv2.newBookingCreate';
        $footerView = 'mailsv2.newFooter';

        $templateMail = Mail::where('type', 'booking_confirm')->where('school_id', $this->schoolData->id)
            ->where('lang', $userLocale)->first();

        $groupedActivities = $this->bookingData->buildGroupedActivitiesFromBookingUsers(
            $this->bookingData->bookingUsers
        );
        $linkedRentals = RentalReservation::query()
            ->where('booking_id', $this->bookingData->id)
            ->where('school_id', $this->schoolData->id)
            ->with(['pickupPoint:id,name,address', 'lines.variant.item', 'lines.item'])
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(function (RentalReservation $reservation) {
                $items = $reservation->lines->map(function ($line) {
                    $variant = $line->variant;
                    $item = $line->item ?: $variant?->item;
                    $variantName = trim((string) ($variant?->name ?: ''));

                    if ($variantName === '') {
                        $itemName = trim((string) ($item?->name ?: ''));
                        $sizeLabel = trim((string) ($variant?->size_label ?: ''));
                        $variantName = trim($itemName . ($sizeLabel !== '' ? ' ' . $sizeLabel : ''));
                    }

                    return [
                        'variant_name' => $variantName !== '' ? $variantName : ('#' . (int) $line->id),
                        'quantity' => (int) ($line->quantity ?? 0),
                        'line_total' => round((float) ($line->line_total ?? 0), 2),
                    ];
                })->values()->all();

                return [
                    'id' => (int) $reservation->id,
                    'reference' => $reservation->reference ?: ('#' . (int) $reservation->id),
                    'start_date' => optional($reservation->start_date)->format('Y-m-d'),
                    'end_date' => optional($reservation->end_date)->format('Y-m-d'),
                    'total' => round((float) ($reservation->total ?? 0), 2),
                    'currency' => $reservation->currency ?: ($this->bookingData->currency ?: 'CHF'),
                    'pickup_point_name' => $reservation->pickupPoint?->name,
                    'pickup_point_address' => $reservation->pickupPoint?->address,
                    'items' => $items,
                ];
            })
            ->values()
            ->all();
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
            'courses' => $this->bookingData->parseBookedGroupedWithCourses(),
            'groupedActivities' => $groupedActivities,
            'bookings' => $this->bookingData->bookingUsers,
            'hasCancellationInsurance' => $this->bookingData->has_cancellation_insurance,
            'actionURL' => null,
            'footerView' => $footerView,
            'paid'=> $this->paid,
            'voucherUsed' => $voucherUsed,
            'voucherIncludedInPrice' => $voucherIncludedInPrice,
            'displayTotal' => number_format($displayTotal, 2, '.', ''),
            'displaySubtotal' => number_format($displaySubtotal, 2, '.', ''),
            'linkedRentals' => $linkedRentals,
        ];

        $subject = __('emails.bookingCreate.subject');
/*        \App::setLocale($oldLocale);*/

        return $this->to($this->userData->email)
            ->subject($subject)
            ->view($templateView)->with($templateData);
    }
}
