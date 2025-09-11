<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectCourseAvailabilityCommand extends Command
{
    protected $signature = 'boukii:inspect-course-availability 
        {--db=} {--school=} {--course=} {--like : Match course by LIKE}';
    protected $description = 'Muestra un desglose por curso de fechas, subgrupos y reservas (booking_users) con filtros status y booking.status.';

    public function handle(): int
    {
        $db = (string) $this->option('db');
        $school = (int) $this->option('school');
        $courseName = (string) $this->option('course');
        $like = (bool) $this->option('like');
        if (!$db || !$school || !$courseName) { $this->error('Uso: --db=conexion --school=ID --course="Nombre" [--like]'); return 1; }

        $cq = DB::connection($db)->table('courses')->where('school_id',$school);
        $like ? $cq->where('name','like',"%$courseName%") : $cq->where('name',$courseName);
        $courses = $cq->get(['id','name','course_type','is_flexible']);
        if ($courses->isEmpty()) { $this->warn('Curso no encontrado'); return 0; }

        foreach ($courses as $c) {
            $dates = DB::connection($db)->table('course_dates')->where('course_id',$c->id)->orderBy('date')->get(['id','date','hour_start','hour_end']);
            $rows = [];
            foreach ($dates as $d) {
                $subgroups = DB::connection($db)->table('course_subgroups')->where('course_date_id',$d->id)->count();
                $maxp = DB::connection($db)->table('course_subgroups')->where('course_date_id',$d->id)->sum('max_participants');
                $bookingsOk = DB::connection($db)->table('booking_users')
                    ->join('bookings','bookings.id','=','booking_users.booking_id')
                    ->where('booking_users.course_date_id',$d->id)
                    ->where('booking_users.course_id',$c->id)
                    ->where('booking_users.status',1)
                    ->where('bookings.status','!=',2)
                    ->count();
                $withSubgroup = DB::connection($db)->table('booking_users')
                    ->join('bookings','bookings.id','=','booking_users.booking_id')
                    ->where('booking_users.course_date_id',$d->id)
                    ->where('booking_users.course_id',$c->id)
                    ->whereNotNull('booking_users.course_subgroup_id')
                    ->where('booking_users.status',1)
                    ->where('bookings.status','!=',2)
                    ->count();
                $withoutSubgroup = $bookingsOk - $withSubgroup;
                $rows[] = [
                    'date' => $d->date,
                    'subgroups' => $subgroups,
                    'max_participants_sum' => $maxp,
                    'booking_users_ok' => $bookingsOk,
                    'with_subgroup' => $withSubgroup,
                    'without_subgroup' => $withoutSubgroup,
                ];
            }
            $this->info("Course: {$c->name} (id={$c->id}, type={$c->course_type}, flexible=".($c->is_flexible?1:0).")");
            $this->table(['date','subgroups','sum(max_participants)','booking_users (status=1, booking!=2)','with_subgroup','without_subgroup'],$rows);
        }
        return 0;
    }
}
