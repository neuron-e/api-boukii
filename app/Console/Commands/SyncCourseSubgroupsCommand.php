<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncCourseSubgroupsCommand extends Command
{
    protected $signature = 'boukii:sync-subgroups
        {--source-db=boukii_dev : Conexion origen}
        {--target-db=boukii_pro : Conexion destino}
        {--src-school= : School ID origen}
        {--dst-school= : School ID destino}
        {--dry-run : Mostrar cambios sin escribir}';

    protected $description = 'Sincroniza course_subgroups faltantes desde DEV a PROD mapeando course_groups y course_dates existentes.';

    public function handle(): int
    {
        $srcDb = (string) $this->option('source-db');
        $dstDb = (string) $this->option('target-db');
        $srcSchool = (int) $this->option('src-school');
        $dstSchool = (int) $this->option('dst-school');
        $dry = (bool) $this->option('dry-run');

        if (!$srcSchool || !$dstSchool) {
            $this->error('Faltan --src-school y/o --dst-school');
            return 1;
        }

        // Verificación básica
        if (!Schema::connection($srcDb)->hasTable('course_subgroups') || !Schema::connection($dstDb)->hasTable('course_subgroups')) {
            $this->error('Tabla course_subgroups no existe en alguna conexión.');
            return 1;
        }

        // Columnas de tiempo de course_dates
        [$srcStartCol, $srcEndCol] = $this->getDateTimeColumns($srcDb);
        [$dstStartCol, $dstEndCol] = $this->getDateTimeColumns($dstDb);

        // Construir mapas base
        $courseMap = $this->buildCourseMap($srcDb, $dstDb, $srcSchool, $dstSchool);
        if (empty($courseMap)) {
            $this->warn('No se encontraron cursos mapeados.');
        }
        $courseDateMap = $this->buildCourseDateMap($srcDb, $dstDb, $courseMap, $srcStartCol, $srcEndCol, $dstStartCol, $dstEndCol);
        $monitorMap = $this->buildMonitorMapByEmail($srcDb, $dstDb, $srcSchool, $dstSchool);

        $added = 0; $skipped = 0; $errors = 0; $mappedGroups = 0;

        // Cargar subgroups DEV vinculados a los cursos de la escuela
        $srcGroupIds = DB::connection($srcDb)->table('course_groups')
            ->whereIn('course_id', array_keys($courseMap) ?: [-1])
            ->pluck('id')->toArray();

        if (empty($srcGroupIds)) {
            $this->info('No hay course_groups relevantes en DEV. Nada que hacer.');
            return 0;
        }

        $srcSubs = DB::connection($srcDb)->table('course_subgroups')
            ->whereIn('course_group_id', $srcGroupIds)
            ->orderBy('course_group_id')->orderBy('id')
            ->get();

        $this->info('Subgroups DEV cargados: ' . $srcSubs->count());

        DB::connection($dstDb)->beginTransaction();
        try {
            foreach ($srcSubs as $sg) {
                // Mapear group
                $prodGroupId = $this->resolveProdGroupId($srcDb, $dstDb, $sg->course_group_id, $courseMap, $courseDateMap, $srcSchool, $dstSchool);
                if (!$prodGroupId) { $skipped++; continue; }
                $mappedGroups++;

                // Mapear course_id en subgroup si existe
                $prodCourseId = null;
                if ($this->hasCol($srcDb, 'course_subgroups', 'course_id') && $this->hasCol($dstDb, 'course_subgroups', 'course_id') && isset($sg->course_id)) {
                    $prodCourseId = $courseMap[$sg->course_id] ?? null;
                    if ($sg->course_id && !$prodCourseId) { $this->warn("Sin mapping de course_id para subgroup {$sg->id}"); }
                }

                // Mapear course_date_id si existe
                $prodCourseDateId = null;
                if ($this->hasCol($srcDb, 'course_subgroups', 'course_date_id') && $this->hasCol($dstDb, 'course_subgroups', 'course_date_id') && isset($sg->course_date_id)) {
                    $prodCourseDateId = $courseDateMap[$sg->course_date_id] ?? null;
                    if ($sg->course_date_id && !$prodCourseDateId) { $this->warn("Sin mapping de course_date_id para subgroup {$sg->id}"); }
                }

                // Mapear degree si existe
                $prodDegreeId = null;
                if ($this->hasCol($srcDb, 'course_subgroups', 'degree_id') && $this->hasCol($dstDb, 'course_subgroups', 'degree_id') && isset($sg->degree_id)) {
                    // Determinar sport del curso del group en PROD
                    $refCourseId = $prodCourseId ?: (DB::connection($dstDb)->table('course_groups')->where('id', $prodGroupId)->value('course_id'));
                    $prodSportId = $refCourseId ? DB::connection($dstDb)->table('courses')->where('id', $refCourseId)->value('sport_id') : null;
                    $prodDegreeId = $this->resolveProdDegree($srcDb, $dstDb, $sg->degree_id, $dstSchool, $prodSportId);
                }

                // Mapear monitor si existe
                $prodMonitorId = null;
                if ($this->hasCol($srcDb, 'course_subgroups', 'monitor_id') && $this->hasCol($dstDb, 'course_subgroups', 'monitor_id') && isset($sg->monitor_id)) {
                    $prodMonitorId = $monitorMap[$sg->monitor_id] ?? null; // si no existe, queda null
                }

                // Dedupe por firma básica
                $q = DB::connection($dstDb)->table('course_subgroups')->where('course_group_id', $prodGroupId);
                if ($this->hasCol($dstDb, 'course_subgroups', 'course_id'))      { $prodCourseId      ? $q->where('course_id', $prodCourseId)           : $q->whereNull('course_id'); }
                if ($this->hasCol($dstDb, 'course_subgroups', 'course_date_id')) { $prodCourseDateId  ? $q->where('course_date_id', $prodCourseDateId) : $q->whereNull('course_date_id'); }
                if ($this->hasCol($dstDb, 'course_subgroups', 'degree_id'))      { $prodDegreeId      ? $q->where('degree_id', $prodDegreeId)          : $q->whereNull('degree_id'); }
                if ($this->hasCol($dstDb, 'course_subgroups', 'monitor_id'))     { $prodMonitorId     ? $q->where('monitor_id', $prodMonitorId)        : $q->whereNull('monitor_id'); }
                if ($this->hasCol($dstDb, 'course_subgroups', 'max_participants')) { $q->where('max_participants', $sg->max_participants ?? null); }

                if ($q->exists()) { $skipped++; continue; }

                $payload = [
                    'course_group_id'  => $prodGroupId,
                    'created_at'       => $sg->created_at ?? now(),
                    'updated_at'       => $sg->updated_at ?? now(),
                ];
                if ($this->hasCol($dstDb, 'course_subgroups', 'max_participants')) $payload['max_participants'] = $sg->max_participants ?? null;
                if ($this->hasCol($dstDb, 'course_subgroups', 'course_id'))       $payload['course_id'] = $prodCourseId;
                if ($this->hasCol($dstDb, 'course_subgroups', 'course_date_id'))  $payload['course_date_id'] = $prodCourseDateId;
                if ($this->hasCol($dstDb, 'course_subgroups', 'degree_id'))       $payload['degree_id'] = $prodDegreeId;
                if ($this->hasCol($dstDb, 'course_subgroups', 'monitor_id'))      $payload['monitor_id'] = $prodMonitorId;

                if ($dry) {
                    $this->line("DRY: insert course_subgroups " . json_encode($payload));
                } else {
                    DB::connection($dstDb)->table('course_subgroups')->insert($payload);
                    $added++;
                }
            }
            $dry ? DB::connection($dstDb)->rollBack() : DB::connection($dstDb)->commit();
        } catch (\Throwable $e) {
            DB::connection($dstDb)->rollBack();
            $this->error('Error: ' . $e->getMessage());
            $errors++;
        }

        $this->info("Mapeos de groups resueltos: $mappedGroups");
        $this->info("Subgroups añadidos: $added | ya existentes/omitidos: $skipped | errores: $errors");
        return $errors ? 1 : 0;
    }

    private function hasCol(string $conn, string $table, string $col): bool
    { return Schema::connection($conn)->hasColumn($table, $col); }

    private function getDateTimeColumns(string $conn): array
    {
        $start = Schema::connection($conn)->hasColumn('course_dates','hour_start') ? 'hour_start' : (Schema::connection($conn)->hasColumn('course_dates','start_time') ? 'start_time' : null);
        $end   = Schema::connection($conn)->hasColumn('course_dates','hour_end')   ? 'hour_end'   : (Schema::connection($conn)->hasColumn('course_dates','end_time')   ? 'end_time'   : null);
        return [$start, $end];
    }

    private function buildCourseMap(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool): array
    {
        $devHasSlug = Schema::connection($srcDb)->hasColumn('courses','slug');
        $proHasSlug = Schema::connection($dstDb)->hasColumn('courses','slug');

        $dev = DB::connection($srcDb)->table('courses')->select('id','name');
        if ($devHasSlug) $dev->addSelect('slug');
        $devCourses = $dev->where('school_id', $srcSchool)->get();

        $pro = DB::connection($dstDb)->table('courses')->select('id','name');
        if ($proHasSlug) $pro->addSelect('slug');
        $prodCourses = $pro->where('school_id', $dstSchool)->get();

        $byName = [];
        $bySlug = [];
        foreach ($prodCourses as $pc) {
            $byName[mb_strtolower(trim($pc->name))] = $pc->id;
            if ($proHasSlug && $pc->slug) $bySlug[mb_strtolower(trim($pc->slug))] = $pc->id;
        }

        $map = [];
        foreach ($devCourses as $dc) {
            $nk = mb_strtolower(trim($dc->name));
            $sk = $devHasSlug && $dc->slug ? mb_strtolower(trim($dc->slug)) : null;
            if (isset($byName[$nk])) $map[$dc->id] = $byName[$nk];
            elseif ($sk && isset($bySlug[$sk])) $map[$dc->id] = $bySlug[$sk];
        }
        return $map;
    }

    private function buildCourseDateMap(string $srcDb, string $dstDb, array $courseMap, ?string $srcStartCol, ?string $srcEndCol, ?string $dstStartCol, ?string $dstEndCol): array
    {
        $map = [];
        if (empty($courseMap)) return $map;
        $needTime = $srcStartCol && $srcEndCol && $dstStartCol && $dstEndCol;

        $devDates = DB::connection($srcDb)->table('course_dates')
            ->whereIn('course_id', array_keys($courseMap))
            ->select('id','course_id','date');
        if ($needTime) $devDates->addSelect($srcStartCol.' as hs', $srcEndCol.' as he');
        $devDates = $devDates->get();

        foreach ($devDates as $d) {
            $prodCourseId = $courseMap[$d->course_id] ?? null;
            if (!$prodCourseId) continue;
            $q = DB::connection($dstDb)->table('course_dates')
                ->where('course_id', $prodCourseId)
                ->where('date', $d->date);
            if ($needTime) {
                $q->where($dstStartCol, $d->hs)->where($dstEndCol, $d->he);
            }
            $prod = $q->first();
            if ($prod) $map[$d->id] = $prod->id;
        }
        return $map;
    }

    private function resolveProdDegree(string $srcDb, string $dstDb, $devDegreeId, int $dstSchool, ?int $prodSportId): ?int
    {
        if (!$devDegreeId) return null;
        try {
            $dd = DB::connection($srcDb)->table('degrees')->where('id', $devDegreeId)->first();
            if (!$dd) return null;
            $q = DB::connection($dstDb)->table('degrees')
                ->where('school_id', $dstSchool)
                ->where('name', $dd->name);
            if (Schema::connection($dstDb)->hasColumn('degrees','level') && isset($dd->level)) $q->where('level', $dd->level);
            if (Schema::connection($dstDb)->hasColumn('degrees','annotation') && isset($dd->annotation)) $q->where('annotation', $dd->annotation);
            if (Schema::connection($dstDb)->hasColumn('degrees','sport_id') && $prodSportId) $q->where('sport_id', $prodSportId);
            $pd = $q->first();
            return $pd?->id;
        } catch (\Throwable $e) { return null; }
    }

    private function buildMonitorMapByEmail(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool): array
    {
        $map = [];
        if (!Schema::connection($srcDb)->hasTable('monitors') || !Schema::connection($dstDb)->hasTable('monitors')) return $map;
        try {
            $srcMonitors = DB::connection($srcDb)->table('monitors')
                ->join('monitors_schools','monitors.id','=','monitors_schools.monitor_id')
                ->where('monitors_schools.school_id', $srcSchool)
                ->whereNotNull('monitors.email')
                ->select('monitors.id','monitors.email')
                ->get();
            $dstMonitors = DB::connection($dstDb)->table('monitors')
                ->join('monitors_schools','monitors.id','=','monitors_schools.monitor_id')
                ->where('monitors_schools.school_id', $dstSchool)
                ->whereNotNull('monitors.email')
                ->select('monitors.id','monitors.email')
                ->get()->keyBy('email');
            foreach ($srcMonitors as $m) {
                if (isset($dstMonitors[$m->email])) $map[$m->id] = $dstMonitors[$m->email]->id;
            }
        } catch (\Throwable $e) {}
        return $map;
    }

    private function resolveProdGroupId(string $srcDb, string $dstDb, int $devGroupId, array $courseMap, array $courseDateMap, int $srcSchool, int $dstSchool): ?int
    {
        $g = DB::connection($srcDb)->table('course_groups')->where('id', $devGroupId)->first();
        if (!$g) return null;

        $prodCourseId = $courseMap[$g->course_id] ?? null;
        if (!$prodCourseId) return null;

        $hasDegSrc = Schema::connection($srcDb)->hasColumn('course_groups','degree_id');
        $hasDegDst = Schema::connection($dstDb)->hasColumn('course_groups','degree_id');
        $hasCDSrc  = Schema::connection($srcDb)->hasColumn('course_groups','course_date_id');
        $hasCDDst  = Schema::connection($dstDb)->hasColumn('course_groups','course_date_id');

        $prodDegreeId = null;
        if ($hasDegSrc && $hasDegDst && $g->degree_id) {
            $prodSportId = DB::connection($dstDb)->table('courses')->where('id', $prodCourseId)->value('sport_id');
            $prodDegreeId = $this->resolveProdDegree($srcDb, $dstDb, $g->degree_id, $dstSchool, $prodSportId);
        }

        $prodCourseDateId = null;
        if ($hasCDSrc && $hasCDDst && $g->course_date_id) {
            $prodCourseDateId = $courseDateMap[$g->course_date_id] ?? null;
        }

        // Intentar match por firma robusta si existen columnas
        $q = DB::connection($dstDb)->table('course_groups')->where('course_id', $prodCourseId);
        if ($hasDegDst) { $prodDegreeId ? $q->where('degree_id', $prodDegreeId) : $q->whereNull('degree_id'); }
        if ($hasCDDst)  { $prodCourseDateId ? $q->where('course_date_id', $prodCourseDateId) : $q->whereNull('course_date_id'); }

        // Campos adicionales si existen para desambiguar
        foreach (['age_min','age_max','recommended_age','teachers_min','teachers_max','teacher_min_degree','auto'] as $col) {
            if ($this->hasCol($dstDb,'course_groups',$col)) {
                $q->where($col, $g->{$col} ?? null);
            }
        }

        $match = $q->first();
        if ($match) return $match->id;

        // Si no encontramos grupo destino y el usuario avisó que ya existen, no crear; devolver null
        $this->warn("Group DEV {$devGroupId} sin equivalente en PROD; se omite su subgroups.");
        return null;
    }
}

