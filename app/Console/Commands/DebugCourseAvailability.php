<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugCourseAvailability extends Command
{
    protected $signature = 'boukii:debug-course-availability {course_id} {--db=}';
    protected $description = 'Calcula disponibilidad de un curso usando Utils (Admin) y muestra totales.';

    public function handle(): int
    {
        $courseId = (int) $this->argument('course_id');
        $db = $this->option('db');
        if ($db) { DB::setDefaultConnection($db); }

        $course = Course::with(['courseDates.courseSubgroups.bookingUsers.booking'])
            ->find($courseId);
        if (!$course) { $this->error('Curso no encontrado'); return 1; }

        // reutilizar trait Utils del Admin CourseController
        $ctrl = app(AdminCourseController::class);
        $ref = new \ReflectionClass($ctrl);
        $method = $ref->getMethod('getCourseAvailability');
        $method->setAccessible(true);
        $availability = $method->invoke($ctrl, $course, []);

        $this->info("Course: {$course->name} (#{$course->id}) type={$course->course_type} flex=".($course->is_flexible?1:0));
        foreach ($availability as $k=>$v) { $this->line(sprintf(" - %s: %s", $k, $v)); }

        // Extra: listar booking_users activos por fecha y subgroup
        $this->line('Detalles booking_users activos:');
        $this->line(' course_date_ids: '.implode(',', $course->courseDates->pluck('id')->all()));
        $rows = [];
        foreach ($course->courseDates as $cd) {
            foreach ($cd->courseSubgroups as $sg) {
                $count = $sg->bookingUsers()->where('status',1)->whereHas('booking', fn($q)=>$q->where('status','!=',2))->count();
                if ($count > 0) {
                    $rows[] = [$cd->date, $sg->id, $count];
                }
            }
        }
        if ($rows) { $this->table(['date','subgroup_id','count'],$rows); } else { $this->line(' - Ninguno'); }

        // Dump the first booking_user record details for troubleshooting
        $first = \App\Models\BookingUser::where('course_id', $course->id)
            ->where('status',1)
            ->whereHas('booking', fn($q)=>$q->where('status','!=',2))
            ->orderBy('id')->first();
        if ($first) {
            $this->line('Sample booking_user fields:');
            $this->line(' id='.$first->id.' date='.$first->date.' hour_start='.$first->hour_start.' hour_end='.$first->hour_end.' course_date_id='.$first->course_date_id.' course_subgroup_id='.$first->course_subgroup_id);
        }
        return 0;
    }
}
