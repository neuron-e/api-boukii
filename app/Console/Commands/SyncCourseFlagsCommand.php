<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCourseFlagsCommand extends Command
{
    protected $signature = 'boukii:sync-course-flags 
        {--source-db=boukii_dev : Conexion origen}
        {--target-db=boukii_pro : Conexion destino}
        {--src-school= : School ID origen}
        {--dst-school= : School ID destino}
        {--dry-run : Mostrar cambios sin escribir}';

    protected $description = 'Sincroniza flags y campos clave de courses (is_flexible, course_type, capacity) de DEV a PROD para una escuela, extrapolando IDs.';

    public function handle(): int
    {
        $srcDb = (string) $this->option('source-db');
        $dstDb = (string) $this->option('target-db');
        $srcSchool = (int) $this->option('src-school');
        $dstSchool = (int) $this->option('dst-school');
        $dry = (bool) $this->option('dry-run');

        if (!$srcSchool || !$dstSchool) { $this->error('Faltan --src-school y/o --dst-school'); return 1; }

        // Map courses by name/slug
        $devHasSlug = $this->hasCol($srcDb,'courses','slug');
        $proHasSlug = $this->hasCol($dstDb,'courses','slug');
        $src = DB::connection($srcDb)->table('courses')->where('school_id',$srcSchool)->select('id','name','course_type','is_flexible'); if ($this->hasCol($srcDb,'courses','capacity')) { $src->addSelect('capacity'); } if ($devHasSlug) $src->addSelect('slug');
        $dst = DB::connection($dstDb)->table('courses')->where('school_id',$dstSchool)->select('id','name','course_type','is_flexible'); if ($this->hasCol($dstDb,'courses','capacity')) { $dst->addSelect('capacity'); } if ($proHasSlug) $dst->addSelect('slug');
        $devCourses = $src->get();
        $dstCourses = $dst->get();

        $byName = [];$bySlug=[]; foreach($dstCourses as $c){ $byName[mb_strtolower(trim($c->name))]=$c; if($proHasSlug && $c->slug) $bySlug[mb_strtolower(trim($c->slug))]=$c; }

        $updates=0; $skipped=0;
        foreach ($devCourses as $dc) {
            $key = mb_strtolower(trim($dc->name));
            $target = $byName[$key] ?? (($devHasSlug && $dc->slug) ? ($bySlug[mb_strtolower(trim($dc->slug))] ?? null) : null);
            if (!$target) { $skipped++; continue; }

            $payload = [];
            if (isset($dc->is_flexible) && $this->hasCol($dstDb,'courses','is_flexible') && $target->is_flexible != $dc->is_flexible) {
                $payload['is_flexible'] = $dc->is_flexible;
            }
            if (isset($dc->course_type) && $this->hasCol($dstDb,'courses','course_type') && $target->course_type != $dc->course_type) {
                $payload['course_type'] = $dc->course_type;
            }
            if ($this->hasCol($dstDb,'courses','capacity') && isset($dc->capacity) && $target->capacity != $dc->capacity) {
                $payload['capacity'] = $dc->capacity;
            }

            if (!empty($payload)) {
                if ($dry) {
                    $this->line('DRY: update course id='.$target->id.' set '.json_encode($payload));
                } else {
                    DB::connection($dstDb)->table('courses')->where('id',$target->id)->update($payload);
                }
                $updates++;
            }
        }

        $this->info("Cursos actualizados: $updates | no mapeados: $skipped");
        return 0;
    }

    private function hasCol(string $conn, string $table, string $col): bool
    { return \Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($table, $col); }
}
