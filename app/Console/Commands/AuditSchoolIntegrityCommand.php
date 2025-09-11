<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditSchoolIntegrityCommand extends Command
{
    protected $signature = 'boukii:audit-school-integrity
        {--db=boukii_pro : Conexion a auditar}
        {--school= : School ID}
        {--course-like= : Filtro por nombre de curso (LIKE)}';

    protected $description = 'Audita integridad relacional de cursos y reservas para una school: courses→dates→groups→subgroups y booking_users (status=1).';

    public function handle(): int
    {
        $db = (string) $this->option('db');
        $school = (int) $this->option('school');
        $like = $this->option('course-like');
        if (!$school) { $this->error('Falta --school'); return 1; }

        $cq = DB::connection($db)->table('courses')->where('school_id',$school);
        if ($like) { $cq->where('name','like',"%$like%"); }
        $courses = $cq->get(['id','name','course_type','is_flexible','online','active']);
        if ($courses->isEmpty()) { $this->warn('No hay cursos para el filtro'); return 0; }

        $summary = [];
        foreach ($courses as $c) {
            $courseId = $c->id;
            $dates = DB::connection($db)->table('course_dates')->where('course_id',$courseId)->get(['id','date','active']);

            $issues = [
                'bad_course_date_fk' => 0,
                'bad_subgroup_fk' => 0,
                'missing_group_for_subgroup' => 0,
                'degree_cross_sport' => 0,
                'monitor_missing' => 0,
            ];
            $buCount = 0; $buActive = 0; $buPerDate = [];

            $bus = DB::connection($db)->table('booking_users')
                ->join('bookings','bookings.id','=','booking_users.booking_id')
                ->where('booking_users.school_id',$school)
                ->where('booking_users.course_id',$courseId)
                ->select('booking_users.*','bookings.status as booking_status')
                ->get();

            foreach ($bus as $bu) {
                $buCount++;
                if ($bu->status == 1 && $bu->booking_status != 2) {
                    $buActive++;
                    $buPerDate[$bu->course_date_id ?? 0] = ($buPerDate[$bu->course_date_id ?? 0] ?? 0) + 1;
                }
                // course_date pertenece al curso
                if ($bu->course_date_id) {
                    $cd = DB::connection($db)->table('course_dates')->where('id',$bu->course_date_id)->first(['id','course_id']);
                    if (!$cd || (int)$cd->course_id !== (int)$courseId) { $issues['bad_course_date_fk']++; }
                }
                // subgroup pertenece al date/grupo del curso
                if ($bu->course_subgroup_id) {
                    $sg = DB::connection($db)->table('course_subgroups')->where('id',$bu->course_subgroup_id)->first(['id','course_group_id','course_date_id']);
                    if ($sg) {
                        $grp = DB::connection($db)->table('course_groups')->where('id',$sg->course_group_id)->first(['id','course_id','course_date_id']);
                        if (!$grp || (int)$grp->course_id !== (int)$courseId) { $issues['bad_subgroup_fk']++; }
                        if (!$grp) { $issues['missing_group_for_subgroup']++; }
                        if ($bu->course_date_id && $sg->course_date_id && (int)$sg->course_date_id !== (int)$bu->course_date_id) {
                            $issues['bad_subgroup_fk']++;
                        }
                    } else { $issues['bad_subgroup_fk']++; }
                }
            }

            $summary[] = [
                'course' => $c->name,
                'id' => $courseId,
                'type' => $c->course_type,
                'flex' => (int)$c->is_flexible,
                'online' => (int)$c->online,
                'active' => (int)$c->active,
                'dates' => $dates->count(),
                'subgroups' => DB::connection($db)->table('course_subgroups')->whereIn('course_group_id', function($q) use ($db,$courseId){ $q->from('course_groups')->select('id')->where('course_id',$courseId); })->count(),
                'bu_total' => $buCount,
                'bu_active' => $buActive,
                'bu_per_date' => json_encode($buPerDate),
                'issues' => json_encode(array_filter($issues)),
            ];
        }

        $this->table(['course','id','type','flex','online','active','dates','subgroups','bu_total','bu_active','bu_per_date','issues'],$summary);
        return 0;
    }
}

