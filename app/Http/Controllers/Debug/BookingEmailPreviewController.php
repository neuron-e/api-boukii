<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Mail as MailTemplate;
use Illuminate\Http\Request;

class BookingEmailPreviewController extends Controller
{
    public function show(Request $request, $id)
    {
        $booking = Booking::with([
            'school',
            'clientMain.language1',
            'bookingUsers' => function ($query) {
                $query->with([
                    'course.sport',
                    'degree',
                    'client.language1',
                    'monitor.language1',
                    'courseExtras',
                    'courseDate',
                ]);
            }
        ])->find($id);

        if (!$booking) {
            abort(404, 'Booking not found');
        }

        $courses = $booking->parseBookedGroupedWithCourses();

        $lang = $request->input('lang') ?? optional($booking->clientMain->language1)->code ?? config('app.locale');
        $typeInput = strtolower(trim((string) $request->input('type', 'confirm')));
        if (str_starts_with($typeInput, 'mailsv2.')) {
            $typeInput = substr($typeInput, strlen('mailsv2.'));
        }

        $typeAliases = [
            'booking_confirm' => 'confirm',
            'booking_info' => 'info',
            'booking_change' => 'info_change',
            'booking_cancel' => 'cancel',
            'payment_link' => 'pay',
            'payment_reminder' => 'pay_notice',
            'newbookingcreate' => 'confirm',
            'newbookinginfo' => 'info',
            'newbookinginfochange' => 'info_change',
            'newbookingcancel' => 'cancel',
            'newbookingpay' => 'pay',
            'newbookingpaynotice' => 'pay_notice',
        ];
        if (isset($typeAliases[$typeInput])) {
            $typeInput = $typeAliases[$typeInput];
        }

        $templates = [
            'confirm' => ['view' => 'mailsv2.newBookingCreate', 'mail_type' => 'booking_confirm'],
            'info' => ['view' => 'mailsv2.newBookingInfo', 'mail_type' => 'booking_confirm'],
            'info_change' => ['view' => 'mailsv2.newBookingInfoChange', 'mail_type' => 'booking_change'],
            'cancel' => ['view' => 'mailsv2.newBookingCancel', 'mail_type' => 'booking_cancel'],
            'pay' => ['view' => 'mailsv2.newBookingPay', 'mail_type' => 'payment_link'],
            'pay_notice' => ['view' => 'mailsv2.newBookingPayNotice', 'mail_type' => 'payment_reminder'],
        ];

        if (!isset($templates[$typeInput])) {
            abort(400, 'Unsupported email type');
        }

        $templateView = $templates[$typeInput]['view'];
        $templateMailType = $templates[$typeInput]['mail_type'];

        $templateMail = MailTemplate::where('type', $templateMailType)
            ->where('school_id', $booking->school_id)
            ->where('lang', $lang)
            ->first();

        if (!$templateMail) {
            $templateMail = MailTemplate::where('type', $templateMailType)
                ->where('school_id', $booking->school_id)
                ->first();
        }

        $titleTemplate = $templateMail ? $templateMail->title : '';
        $bodyTemplate = $templateMail ? $templateMail->body : '';
        $userName = trim(($booking->clientMain->first_name ?? '') . ' ' . ($booking->clientMain->last_name ?? ''));

        $schoolPhone = $booking->school->contact_phone ?? null;
        $schoolEmail = $booking->school->contact_email ?? null;
        $schoolConditionsURL = $booking->school->conditions_url ?? null;

        $actionURL = $request->input('action_url');
        if (!$actionURL && in_array($typeInput, ['pay', 'pay_notice'], true)) {
            $actionURL = 'https://pay.example.com';
        }

        $referenceValue = '#' . $booking->id;
        if (in_array($typeInput, ['pay', 'pay_notice'], true) && $booking->payrexx_reference) {
            $referenceValue = $booking->payrexx_reference;
        }

        $data = [
            'titleTemplate' => $titleTemplate,
            'bodyTemplate' => $bodyTemplate,
            'userName' => $userName,
            'reference' => $referenceValue,
            'booking' => $booking,
            'groupedActivities' => $booking->getGroupedActivitiesAttribute(),
            'courses' => $courses,
            'bookings' => $booking->bookingUsers,
            'bookingNotes' => $booking->notes,
            'paid' => $booking->paid,
            'actionURL' => $actionURL,
            'footerView' => 'mailsv2.newFooter',
            'schoolLogo' => $booking->school->logo ?? null,
            'schoolName' => $booking->school->name ?? '',
            'schoolDescription' => $booking->school->description ?? '',
            'schoolPhone' => $schoolPhone,
            'schoolEmail' => $schoolEmail,
            'schoolConditionsURL' => $schoolConditionsURL,
            'client' => $booking->clientMain,
            'amount' => number_format(($booking->price_total ?? 0) - ($booking->paid_total ?? 0), 2),
            'currency' => $booking->currency,
        ];

        $data['message'] = new class {
            public function embedData(?string $bytes, string $name, string $mime): string
            {
                if (! $bytes) {
                    return '';
                }

                return sprintf('data:%s;base64,%s', $mime, base64_encode($bytes));
            }
        };

        return view($templateView, $data);
    }
}
