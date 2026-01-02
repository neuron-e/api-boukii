<?php

namespace App\Console\Commands;

use App\Http\Services\BookingPriceSnapshotService;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\ClientsSchool;
use App\Models\ClientsUtilizer;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseSubgroup;
use App\Models\Payment;
use App\Models\School;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverMissingBooking extends Command
{
    protected $signature = 'bookings:recover-missing
        {--school-id= : School id}
        {--school-slug= : School slug}
        {--course-id= : Course id}
        {--course-name= : Course name}
        {--course-date-id= : Course date id}
        {--date= : Booking date (YYYY-MM-DD)}
        {--start= : Start time (HH:MM)}
        {--end= : End time (HH:MM)}
        {--price= : Total price}
        {--currency=CHF : Currency}
        {--source=booking_page : Booking source}
        {--original-booking-id= : Old booking id (stored in old_id)}
        {--created-at= : Created at datetime}
        {--main-client-id= : Main client id}
        {--main-email= : Main client email}
        {--main-first= : Main client first name}
        {--main-last= : Main client last name}
        {--main-birthdate= : Main client birth date (YYYY-MM-DD, required if new)}
        {--main-phone= : Main client phone}
        {--main-address= : Main client address}
        {--main-zip= : Main client zip/postal code}
        {--main-city= : Main client city}
        {--main-country=CH : Main client country code}
        {--participant-id= : Participant client id}
        {--participant-first= : Participant first name}
        {--participant-last= : Participant last name}
        {--participant-birthdate= : Participant birth date (YYYY-MM-DD, required if new)}
        {--monitor-id= : Monitor id}
        {--degree-id= : Degree id}
        {--course-group-id= : Course group id}
        {--course-subgroup-id= : Course subgroup id}
        {--payment-method-id=2 : Payment method id}
        {--payrexx-reference= : Payrexx reference}
        {--payrexx-transaction= : Payrexx transaction id}
        {--allow-duplicate-reference : Allow reuse of payrexx_reference}
        {--dry-run : Preview changes without writing}';

    protected $description = 'Recreate a missing booking (Payrexx paid) with a new ID.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $school = $this->resolveSchool();
        if (!$school) {
            $this->error('School not found. Provide --school-id or --school-slug.');
            return 1;
        }

        $course = $this->resolveCourse($school);
        if (!$course) {
            $this->error('Course not found. Provide --course-id or --course-name.');
            return 1;
        }

        $courseDate = $this->resolveCourseDate($course);
        if (!$courseDate) {
            $this->error('Course date not found. Provide --course-date-id or course date/time details.');
            return 1;
        }

        $priceTotal = $this->option('price');
        if (!is_numeric($priceTotal)) {
            $this->error('Missing or invalid --price.');
            return 1;
        }

        $createdAt = $this->parseCreatedAt($this->option('created-at'));
        if (!$createdAt) {
            $this->error('Invalid --created-at (expected datetime).');
            return 1;
        }

        $mainClient = $this->resolveMainClient();
        if (!$mainClient) {
            return 1;
        }

        $participant = $this->resolveParticipant();
        if (!$participant) {
            return 1;
        }

        $monitorId = $this->option('monitor-id');
        $courseSubgroupId = $this->option('course-subgroup-id');
        if (!$monitorId && $courseSubgroupId) {
            $monitorId = CourseSubgroup::where('id', $courseSubgroupId)->value('monitor_id');
        }

        $payload = [
            'school_id' => $school->id,
            'course_id' => $course->id,
            'course_date_id' => $courseDate->id,
            'date' => $this->option('date') ?? $courseDate->date?->format('Y-m-d'),
            'hour_start' => $this->option('start') ?? $courseDate->hour_start,
            'hour_end' => $this->option('end') ?? $courseDate->hour_end,
            'price_total' => (float) $priceTotal,
            'currency' => (string) $this->option('currency'),
            'source' => (string) $this->option('source'),
            'old_id' => $this->option('original-booking-id'),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'main_client_id' => $mainClient->id,
            'participant_id' => $participant->id,
            'monitor_id' => $monitorId ? (int) $monitorId : null,
            'degree_id' => $this->option('degree-id') ? (int) $this->option('degree-id') : null,
            'course_group_id' => $this->option('course-group-id') ? (int) $this->option('course-group-id') : null,
            'course_subgroup_id' => $courseSubgroupId ? (int) $courseSubgroupId : null,
            'payment_method_id' => (int) $this->option('payment-method-id'),
            'payrexx_reference' => $this->option('payrexx-reference'),
            'payrexx_transaction' => $this->option('payrexx-transaction'),
        ];

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be written');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            return 0;
        }

        if (!empty($payload['old_id'])) {
            $existing = Booking::where('old_id', $payload['old_id'])->first();
            if ($existing) {
                $this->error("A booking already exists with old_id={$payload['old_id']} (ID {$existing->id}).");
                return 1;
            }
        }

        if (!empty($payload['payrexx_reference'])) {
            $existing = Booking::where('payrexx_reference', $payload['payrexx_reference'])->first();
            if ($existing && !$this->option('allow-duplicate-reference')) {
                $this->error("A booking already exists with payrexx_reference={$payload['payrexx_reference']} (ID {$existing->id}).");
                $this->line('Use --allow-duplicate-reference to proceed if you are restoring a lost booking.');
                return 1;
            }
            if ($existing) {
                $this->warn("Continuing with duplicate payrexx_reference on booking ID {$existing->id}.");
            }
        }

        if (!empty($payload['payrexx_transaction'])) {
            $existing = Booking::where('payrexx_transaction', $payload['payrexx_transaction'])->first();
            if ($existing) {
                $this->error("A booking already exists with payrexx_transaction={$payload['payrexx_transaction']} (ID {$existing->id}).");
                return 1;
            }
        }

        DB::transaction(function () use ($payload, $course, $createdAt, $mainClient, $participant, $school) {
            ClientsSchool::firstOrCreate(
                ['client_id' => $mainClient->id, 'school_id' => $school->id],
                ['status_updated_at' => $createdAt, 'accepted_at' => $createdAt]
            );
            ClientsSchool::firstOrCreate(
                ['client_id' => $participant->id, 'school_id' => $school->id],
                ['status_updated_at' => $createdAt, 'accepted_at' => $createdAt]
            );

            ClientsUtilizer::firstOrCreate(
                ['main_id' => $mainClient->id, 'client_id' => $participant->id]
            );

            $booking = Booking::create([
                'school_id' => $payload['school_id'],
                'client_main_id' => $payload['main_client_id'],
                'price_total' => $payload['price_total'],
                'currency' => $payload['currency'],
                'source' => $payload['source'],
                'status' => 1,
                'paxes' => 1,
                'paid_total' => $payload['price_total'],
                'paid' => true,
                'payment_method_id' => $payload['payment_method_id'],
                'payrexx_reference' => $payload['payrexx_reference'],
                'payrexx_transaction' => $payload['payrexx_transaction'],
                'old_id' => $payload['old_id'],
                'meeting_point' => $course->meeting_point,
                'meeting_point_address' => $course->meeting_point_address,
                'meeting_point_instructions' => $course->meeting_point_instructions,
            ]);

            BookingUser::create([
                'school_id' => $payload['school_id'],
                'booking_id' => $booking->id,
                'client_id' => $payload['participant_id'],
                'price' => $payload['price_total'],
                'currency' => $payload['currency'],
                'course_id' => $payload['course_id'],
                'course_date_id' => $payload['course_date_id'],
                'course_group_id' => $payload['course_group_id'],
                'course_subgroup_id' => $payload['course_subgroup_id'],
                'monitor_id' => $payload['monitor_id'],
                'degree_id' => $payload['degree_id'],
                'date' => $payload['date'],
                'hour_start' => $payload['hour_start'],
                'hour_end' => $payload['hour_end'],
                'group_id' => 1,
                'accepted' => $payload['course_subgroup_id'] !== null,
                'attended' => false,
                'status' => 1,
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'school_id' => $payload['school_id'],
                'amount' => $payload['price_total'],
                'status' => 'paid',
                'payrexx_reference' => $payload['payrexx_reference'],
                'payrexx_transaction' => $payload['payrexx_transaction'],
            ]);

            BookingLog::create([
                'booking_id' => $booking->id,
                'action' => 'recovered_missing',
                'description' => 'Recovered missing booking from Payrexx transaction',
                'user_id' => null,
            ]);

            $booking->refreshPaymentTotalsFromPayments();

            app(BookingPriceSnapshotService::class)->createSnapshotFromCalculator(
                $booking,
                null,
                'recovery',
                'Snapshot created by bookings:recover-missing'
            );

            $booking->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();

            BookingUser::where('booking_id', $booking->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            Payment::where('booking_id', $booking->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            BookingLog::where('booking_id', $booking->id)
                ->where('action', 'recovered_missing')
                ->update([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

            Log::channel('bookings')->info('RECOVER_MISSING_BOOKING', [
                'booking_id' => $booking->id,
                'old_booking_id' => $payload['old_id'],
                'school_id' => $payload['school_id'],
            ]);
        });

        $this->info('Missing booking recovered successfully.');

        return 0;
    }

    private function resolveSchool(): ?School
    {
        $schoolId = $this->option('school-id');
        if ($schoolId) {
            return School::find($schoolId);
        }

        $schoolSlug = $this->option('school-slug');
        if ($schoolSlug) {
            return School::where('slug', $schoolSlug)->first();
        }

        return null;
    }

    private function resolveCourse(School $school): ?Course
    {
        $courseId = $this->option('course-id');
        if ($courseId) {
            return Course::where('school_id', $school->id)->find($courseId);
        }

        $courseName = $this->option('course-name');
        if ($courseName) {
            return Course::where('school_id', $school->id)
                ->where('name', $courseName)
                ->first();
        }

        return null;
    }

    private function resolveCourseDate(Course $course): ?CourseDate
    {
        $courseDateId = $this->option('course-date-id');
        if ($courseDateId) {
            return CourseDate::where('course_id', $course->id)->find($courseDateId);
        }

        $date = $this->option('date');
        $start = $this->option('start');
        $end = $this->option('end');
        if ($date && $start && $end) {
            return CourseDate::where('course_id', $course->id)
                ->whereDate('date', $date)
                ->whereTime('hour_start', $start)
                ->whereTime('hour_end', $end)
                ->first();
        }

        return null;
    }

    private function parseCreatedAt(?string $createdAt): ?Carbon
    {
        if (!$createdAt) {
            return null;
        }

        try {
            return Carbon::parse($createdAt);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function resolveMainClient(): ?Client
    {
        $mainClientId = $this->option('main-client-id');
        if ($mainClientId) {
            return Client::find($mainClientId);
        }

        $email = $this->option('main-email');
        if (!$email) {
            $this->error('Missing --main-email.');
            return null;
        }

        $client = Client::where('email', $email)->first();
        if ($client) {
            return $client;
        }

        $birthDate = $this->option('main-birthdate');
        if (!$birthDate) {
            $this->error('Main client not found. Provide --main-birthdate to create.');
            return null;
        }

        return Client::create([
            'email' => $email,
            'first_name' => $this->option('main-first'),
            'last_name' => $this->option('main-last'),
            'birth_date' => $birthDate,
            'phone' => $this->option('main-phone'),
            'address' => $this->option('main-address'),
            'cp' => $this->option('main-zip'),
            'city' => $this->option('main-city'),
            'country' => $this->option('main-country'),
        ]);
    }

    private function resolveParticipant(): ?Client
    {
        $participantId = $this->option('participant-id');
        if ($participantId) {
            return Client::find($participantId);
        }

        $first = $this->option('participant-first');
        $last = $this->option('participant-last');
        if (!$first || !$last) {
            $this->error('Missing participant info (--participant-id or --participant-first/--participant-last).');
            return null;
        }

        $existing = Client::where('first_name', $first)
            ->where('last_name', $last)
            ->first();
        if ($existing) {
            return $existing;
        }

        $birthDate = $this->option('participant-birthdate');
        if (!$birthDate) {
            $this->error('Participant not found. Provide --participant-birthdate to create.');
            return null;
        }

        return Client::create([
            'first_name' => $first,
            'last_name' => $last,
            'birth_date' => $birthDate,
        ]);
    }
}
