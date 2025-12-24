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
        if (!app()->isLocal()) {
            abort(404);
        }

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
        $templateMail = MailTemplate::where('type', 'booking_confirm')
            ->where('school_id', $booking->school_id)
            ->where('lang', $lang)
            ->first();

        if (!$templateMail) {
            $templateMail = MailTemplate::where('type', 'booking_confirm')
                ->where('school_id', $booking->school_id)
                ->first();
        }

        $titleTemplate = $templateMail ? $templateMail->title : '';
        $bodyTemplate = $templateMail ? $templateMail->body : '';
        $userName = trim(($booking->clientMain->first_name ?? '') . ' ' . ($booking->clientMain->last_name ?? ''));

        $schoolPhone = $booking->school->contact_phone ?? null;
        $schoolEmail = $booking->school->contact_email ?? null;
        $schoolConditionsURL = $booking->school->conditions_url ?? null;

        $data = [
            'titleTemplate' => $titleTemplate,
            'bodyTemplate' => $bodyTemplate,
            'userName' => $userName,
            'reference' => '#' . $booking->id,
            'booking' => $booking,
            'courses' => $courses,
            'bookings' => $booking->bookingUsers,
            'bookingNotes' => $booking->notes,
            'paid' => $booking->paid,
            'actionURL' => null,
            'footerView' => 'mailsv2.newFooter',
            'schoolLogo' => $booking->school->logo ?? null,
            'schoolName' => $booking->school->name ?? '',
            'schoolDescription' => $booking->school->description ?? '',
            'schoolPhone' => $schoolPhone,
            'schoolEmail' => $schoolEmail,
            'schoolConditionsURL' => $schoolConditionsURL,
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

        return view('mailsv2.newBookingCreate', $data);
    }
}
