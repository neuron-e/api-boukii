<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingPaymentNoticeLog;
use App\Models\BookingUser;
use App\Models\BookingUserExtra;
use App\Models\BookingUserTime;
use App\Models\Course;
use App\Models\CourseDate;
use App\Models\CourseDiscount;
use App\Models\CourseExtra;
use App\Models\CourseGroup;
use App\Models\CourseInterval;
use App\Models\CourseIntervalDiscount;
use App\Models\CourseIntervalGroup;
use App\Models\CourseIntervalMonitor;
use App\Models\CourseIntervalSubgroup;
use App\Models\CourseSubgroup;
use App\Models\Payment;
use App\Models\School;
use App\Models\VouchersLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class WipeSchoolData extends Command
{
    protected $signature = 'school:wipe-data
        {school_id : ID de la escuela a limpiar}
        {--force : Omite la confirmacion interactiva}';

    protected $description = 'Elimina cursos y reservas de una escuela manteniendo ajustes, niveles, deportes, monitores y clientes.';

    public function handle(): int
    {
        $schoolId = (int) $this->argument('school_id');
        $force = (bool) $this->option('force');

        $school = School::find($schoolId);

        if (!$school) {
            $this->error("No se encontro la escuela {$schoolId}");
            return self::FAILURE;
        }

        if (
            !$force &&
            !$this->confirm("Se eliminaran todas las reservas y cursos de {$school->name} ({$school->id}). Continuar?")
        ) {
            $this->warn('Operacion cancelada.');
            return self::INVALID;
        }

        try {
            $this->info("Iniciando purga de datos para {$school->name} ({$school->id})");

            $bookingStats = $this->purgeBookings($schoolId);
            $courseStats = $this->purgeCourses($schoolId);

            $this->info("Reservas eliminadas: {$bookingStats['bookings']} (participantes: {$bookingStats['booking_users']})");
            $this->info("Cursos eliminados: {$courseStats['courses']}");
            $this->info('Purgado completado correctamente.');
        } catch (Throwable $exception) {
            $this->error('No se pudo completar la purga: ' . $exception->getMessage());
            report($exception);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Elimina reservas y datos dependientes para la escuela indicada.
     */
    private function purgeBookings(int $schoolId): array
    {
        $stats = [
            'bookings' => 0,
            'booking_users' => 0,
        ];

        Booking::withTrashed()
            ->where('school_id', $schoolId)
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$stats) {
                DB::transaction(function () use ($bookings, &$stats) {
                    $bookingIds = $bookings->pluck('id');

                    if ($bookingIds->isEmpty()) {
                        return;
                    }

                    $stats['bookings'] += $bookingIds->count();

                    $bookingUserIds = BookingUser::withTrashed()
                        ->whereIn('booking_id', $bookingIds)
                        ->pluck('id');

                    $stats['booking_users'] += $bookingUserIds->count();

                    if ($bookingUserIds->isNotEmpty()) {
                        BookingUserExtra::withTrashed()
                            ->whereIn('booking_user_id', $bookingUserIds)
                            ->forceDelete();

                        BookingUserTime::whereIn('booking_user_id', $bookingUserIds)->delete();

                        BookingPaymentNoticeLog::whereIn('booking_user_id', $bookingUserIds)->delete();
                    }

                    BookingLog::withTrashed()
                        ->whereIn('booking_id', $bookingIds)
                        ->forceDelete();

                    BookingPaymentNoticeLog::whereIn('booking_id', $bookingIds)->delete();

                    Payment::withTrashed()
                        ->whereIn('booking_id', $bookingIds)
                        ->forceDelete();

                    VouchersLog::withTrashed()
                        ->whereIn('booking_id', $bookingIds)
                        ->forceDelete();

                    DB::table('discount_code_usages')
                        ->whereIn('booking_id', $bookingIds)
                        ->delete();

                    BookingUser::withTrashed()
                        ->whereIn('id', $bookingUserIds)
                        ->forceDelete();

                    Booking::withTrashed()
                        ->whereIn('id', $bookingIds)
                        ->forceDelete();
                });
            });

        return $stats;
    }

    /**
     * Elimina cursos y tablas hijas para la escuela indicada.
     */
    private function purgeCourses(int $schoolId): array
    {
        $stats = [
            'courses' => 0,
        ];

        Course::withTrashed()
            ->where('school_id', $schoolId)
            ->orderBy('id')
            ->chunkById(100, function ($courses) use (&$stats) {
                DB::transaction(function () use (&$stats, $courses) {
                    $courseIds = $courses->pluck('id');

                    if ($courseIds->isEmpty()) {
                        return;
                    }

                    $stats['courses'] += $courseIds->count();

                    $intervalIds = CourseInterval::whereIn('course_id', $courseIds)->pluck('id');
                    $intervalGroupIds = CourseIntervalGroup::whereIn('course_id', $courseIds)->pluck('id');

                    if ($intervalGroupIds->isNotEmpty()) {
                        CourseIntervalSubgroup::whereIn('course_interval_group_id', $intervalGroupIds)->delete();
                    }

                    CourseIntervalMonitor::whereIn('course_id', $courseIds)->delete();
                    CourseIntervalGroup::whereIn('id', $intervalGroupIds)->delete();
                    CourseIntervalDiscount::whereIn('course_id', $courseIds)->delete();

                    CourseSubgroup::withTrashed()
                        ->whereIn('course_id', $courseIds)
                        ->forceDelete();

                    CourseGroup::withTrashed()
                        ->whereIn('course_id', $courseIds)
                        ->forceDelete();

                    CourseDate::withTrashed()
                        ->whereIn('course_id', $courseIds)
                        ->forceDelete();

                    CourseInterval::whereIn('course_id', $courseIds)->delete();

                    CourseExtra::withTrashed()
                        ->whereIn('course_id', $courseIds)
                        ->forceDelete();

                    CourseDiscount::whereIn('course_id', $courseIds)->delete();

                    Course::withTrashed()
                        ->whereIn('id', $courseIds)
                        ->forceDelete();
                });
            });

        return $stats;
    }
}
