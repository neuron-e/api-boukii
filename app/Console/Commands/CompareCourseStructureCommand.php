<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompareCourseStructureCommand extends Command
{
    protected $signature = 'boukii:compare-course-structure 
        {--source-db=boukii_dev}
        {--target-db=boukii_pro}
        {--src-school=}
        {--dst-school=}';

    protected $description = 'Compara por curso (name/slug) el número de fechas y subgrupos por fecha entre DEV y PROD.';

    public function handle(): int
    {
        $srcDb = (string) $this->option('source-db');
        $dstDb = (string) $this->option('target-db');
        $srcSchool = (int) $this->option('src-school');
        $dstSchool = (int) $this->option('dst-school');
        if (!$srcSchool || !$dstSchool) { $this->error('Faltan --src-school y/o --dst-school'); return 1; }

        $devHasSlug = \Illuminate\Support\Facades\Schema::connection($srcDb)->hasColumn('courses','slug');
        $proHasSlug = \Illuminate\Support\Facades\Schema::connection($dstDb)->hasColumn('courses','slug');

        $dev = DB::connection($srcDb)->table('courses')->where('school_id',$srcSchool)->select('id','name'); if ($devHasSlug) $dev->addSelect('slug');
        $pro = DB::connection($dstDb)->table('courses')->where('school_id',$dstSchool)->select('id','name'); if ($proHasSlug) $pro->addSelect('slug');
        $devCourses = $dev->get();
        $proCourses = $pro->get();
        $byName = []; $bySlug=[]; foreach ($proCourses as $c) { $byName[mb_strtolower(trim($c->name))]=$c; if ($proHasSlug && $c->slug) $bySlug[mb_strtolower(trim($c->slug))]=$c; }

        $rows = [];
        foreach ($devCourses as $dc) {
            $key = mb_strtolower(trim($dc->name));
            $pc = $byName[$key] ?? (($devHasSlug && $dc->slug) ? ($bySlug[mb_strtolower(trim($dc->slug))] ?? null) : null);
            if (!$pc) continue;

            $devDates = DB::connection($srcDb)->table('course_dates')->where('course_id',$dc->id)->pluck('id');
            $proDates = DB::connection($dstDb)->table('course_dates')->where('course_id',$pc->id)->pluck('id');
            $devSubs = DB::connection($srcDb)->table('course_subgroups')->whereIn('course_date_id',$devDates)->count();
            $proSubs = DB::connection($dstDb)->table('course_subgroups')->whereIn('course_date_id',$proDates)->count();

            $rows[] = [
                'name' => $dc->name,
                'dates_dev' => count($devDates),
                'dates_pro' => count($proDates),
                'subs_dev' => $devSubs,
                'subs_pro' => $proSubs,
            ];
        }

        usort($rows, fn($a,$b) => strcmp($a['name'],$b['name']));
        $this->table(['Course','Dates DEV','Dates PRO','Subgroups DEV','Subgroups PRO'],$rows);
        return 0;
    }
}

