<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncBookingUsersCommand extends Command
{
    protected $signature = 'boukii:sync-booking-users
        {--source-db=boukii_dev : Conexion origen}
        {--target-db=boukii_pro : Conexion destino}
        {--src-school= : School ID origen}
        {--dst-school= : School ID destino}
        {--dry-run : Mostrar cambios sin escribir}';

    protected $description = 'Sincroniza booking_users desde DEV a PROD mapeando bookings, clients, courses, groups, subgroups, dates, degrees y monitors.';

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

        [$srcStartCol, $srcEndCol] = $this->getDateTimeColumns($srcDb);
        [$dstStartCol, $dstEndCol] = $this->getDateTimeColumns($dstDb);

        // Build mappings
        $clientMap = $this->mapByEmail($srcDb, $dstDb, 'clients');
        $monitorMap = $this->mapByEmail($srcDb, $dstDb, 'monitors', $srcSchool, $dstSchool);
        $courseMap = $this->buildCourseMap($srcDb, $dstDb, $srcSchool, $dstSchool);
        $courseDateMap = $this->buildCourseDateMap($srcDb, $dstDb, $courseMap, $srcStartCol, $srcEndCol, $dstStartCol, $dstEndCol);
        $subgroupMap = $this->buildSubgroupMap($srcDb, $dstDb, $courseMap, $courseDateMap, $srcSchool, $dstSchool);
        $this->info('Map sizes -> clients: '.count($clientMap).', monitors: '.count($monitorMap).', courses: '.count($courseMap).', dates: '.count($courseDateMap).', subgroups: '.count($subgroupMap));

        // Build booking mapping by heuristic (client_main_id + price_total + created_at date)
        $bookingMap = $this->buildBookingMap($srcDb, $dstDb, $srcSchool, $dstSchool, $clientMap);
        $this->info('Bookings mapeados: '.count($bookingMap));

        // Fetch booking_users in DEV for school
        $devBookingIds = DB::connection($srcDb)->table('bookings')->where('school_id', $srcSchool)->pluck('id')->toArray();
        if (empty($devBookingIds)) { $this->info('No hay bookings en DEV.'); return 0; }

        $devBU = DB::connection($srcDb)->table('booking_users')->whereIn('booking_id', $devBookingIds)->get();
        $this->info('BookingUsers DEV: '.$devBU->count());

        $added = 0; $updated = 0; $skipped = 0; $errors = 0;
        DB::connection($dstDb)->beginTransaction();
        try {
            foreach ($devBU as $row) {
                $prodBookingId = $bookingMap[$row->booking_id] ?? null;
                $prodClientId  = $clientMap[$row->client_id] ?? null;
                $prodCourseId  = isset($row->course_id) ? ($courseMap[$row->course_id] ?? null) : null;
                $prodCourseDateId = isset($row->course_date_id) ? ($courseDateMap[$row->course_date_id] ?? null) : null;
                $prodMonitorId = isset($row->monitor_id) ? ($monitorMap[$row->monitor_id] ?? null) : null;
                $prodSubgroupId = isset($row->course_subgroup_id) ? ($subgroupMap[$row->course_subgroup_id] ?? null) : null;

                // Resolve course_group_id via subgroup or direct mapping
                $prodCourseGroupId = null;
                if (Schema::connection($dstDb)->hasColumn('booking_users','course_group_id')) {
                    if ($prodSubgroupId) {
                        $prodCourseGroupId = DB::connection($dstDb)->table('course_subgroups')->where('id',$prodSubgroupId)->value('course_group_id');
                    } elseif (isset($row->course_group_id)) {
                        $prodCourseGroupId = $this->resolveProdGroupId($srcDb, $dstDb, $row->course_group_id, $courseMap, $courseDateMap, $srcSchool, $dstSchool);
                    }
                }

                // Degree mapping
                $prodDegreeId = null;
                if (isset($row->degree_id) && Schema::connection($dstDb)->hasColumn('booking_users','degree_id')) {
                    $refCourseId = $prodCourseId ?: ($prodCourseGroupId ? DB::connection($dstDb)->table('course_groups')->where('id',$prodCourseGroupId)->value('course_id') : null);
                    $prodSportId = $refCourseId ? DB::connection($dstDb)->table('courses')->where('id',$refCourseId)->value('sport_id') : null;
                    $prodDegreeId = $this->resolveProdDegree($srcDb, $dstDb, $row->degree_id, $dstSchool, $prodSportId);
                }

                // Si no encontramos booking mapeado, intentar localizar booking_user existente directamente
                $existing = null;
                if ((!$prodBookingId) && $prodClientId) {
                    $probe = DB::connection($dstDb)->table('booking_users')
                        ->where('school_id', $dstSchool)
                        ->where('client_id', $prodClientId);
                    if (Schema::connection($dstDb)->hasColumn('booking_users','course_id') && $prodCourseId)      { $probe->where('course_id',$prodCourseId); }
                    if (Schema::connection($dstDb)->hasColumn('booking_users','course_date_id') && $prodCourseDateId) { $probe->where('course_date_id',$prodCourseDateId); }
                    $existing = $probe->orderBy('id')->first();
                    if ($existing) { $prodBookingId = $existing->booking_id; }
                }
                if (!$prodBookingId || !$prodClientId) { $skipped++; continue; }

                // Find existing booking_user in PROD
                $q = DB::connection($dstDb)->table('booking_users')->where('booking_id',$prodBookingId)->where('client_id',$prodClientId);
                if (Schema::connection($dstDb)->hasColumn('booking_users','course_id'))      { $prodCourseId     ? $q->where('course_id',$prodCourseId)           : $q->whereNull('course_id'); }
                if (Schema::connection($dstDb)->hasColumn('booking_users','course_date_id')) { $prodCourseDateId ? $q->where('course_date_id',$prodCourseDateId) : $q->whereNull('course_date_id'); }
                $existing = $existing ?: $q->first();

                // Prepare payload with only existing columns
                $payload = [];
                $setIf = function(string $col, $val) use (&$payload, $dstDb) {
                    if (Schema::connection($dstDb)->hasColumn('booking_users', $col)) { $payload[$col] = $val; }
                };

                $setIf('school_id', $dstSchool);
                $setIf('booking_id', $prodBookingId);
                $setIf('client_id', $prodClientId);
                $setIf('course_id', $prodCourseId);
                $setIf('course_date_id', $prodCourseDateId);
                $setIf('course_group_id', $prodCourseGroupId);
                $setIf('course_subgroup_id', $prodSubgroupId);
                $setIf('degree_id', $prodDegreeId);
                $setIf('monitor_id', $prodMonitorId);
                $setIf('date', $row->date ?? null);
                $setIf('hour_start', $row->hour_start ?? null);
                $setIf('hour_end', $row->hour_end ?? null);
                $setIf('price', $row->price ?? null);
                $setIf('currency', $row->currency ?? null);
                $setIf('notes', $row->notes ?? null);
                $setIf('notes_school', $row->notes_school ?? null);
                $setIf('status', $row->status ?? null);
                $setIf('attended', $row->attended ?? null);
                $setIf('group_changed', $row->group_changed ?? null);
                $setIf('accepted', $row->accepted ?? null);
                $setIf('color', $row->color ?? null);
                $payload['updated_at'] = now();
                if (!$existing) { $payload['created_at'] = $row->created_at ?? now(); }

                if ($existing) {
                    // Only fill missing (null) fields by default
                    $toUpdate = [];
                    foreach ($payload as $k=>$v) {
                        if ($k === 'booking_id' || $k === 'client_id') continue;
                        if (!isset($existing->$k) || is_null($existing->$k)) { $toUpdate[$k] = $v; }
                    }
                    if (!empty($toUpdate)) {
                        if ($dry) {
                            $this->line('DRY: update booking_users id='.$existing->id.' set '.json_encode($toUpdate));
                        } else {
                            DB::connection($dstDb)->table('booking_users')->where('id',$existing->id)->update($toUpdate);
                            $updated++;
                        }
                    } else {
                        $skipped++;
                    }
                } else {
                    if ($dry) {
                        $this->line('DRY: insert booking_users '.json_encode($payload));
                    } else {
                        DB::connection($dstDb)->table('booking_users')->insert($payload);
                        $added++;
                    }
                }
            }
            $dry ? DB::connection($dstDb)->rollBack() : DB::connection($dstDb)->commit();
        } catch (\Throwable $e) {
            DB::connection($dstDb)->rollBack();
            $this->error('Error: '.$e->getMessage());
            return 1;
        }

        $this->info("BookingUsers añadidos: $added | actualizados: $updated | omitidos: $skipped");
        return 0;
    }

    private function getDateTimeColumns(string $conn): array
    {
        $start = Schema::connection($conn)->hasColumn('course_dates','hour_start') ? 'hour_start' : (Schema::connection($conn)->hasColumn('course_dates','start_time') ? 'start_time' : null);
        $end   = Schema::connection($conn)->hasColumn('course_dates','hour_end')   ? 'hour_end'   : (Schema::connection($conn)->hasColumn('course_dates','end_time')   ? 'end_time'   : null);
        return [$start, $end];
    }

    private function mapByEmail(string $srcDb, string $dstDb, string $table, ?int $srcSchool = null, ?int $dstSchool = null): array
    {
        $map = [];
        try {
            $src = DB::connection($srcDb)->table($table)->whereNotNull('email')->select('id','email');
            if ($table === 'monitors' && $srcSchool) {
                $src->join('monitors_schools','monitors.id','=','monitors_schools.monitor_id')->where('monitors_schools.school_id',$srcSchool);
            }
            $srcRows = $src->get();
            $dst = DB::connection($dstDb)->table($table)->whereNotNull('email')->select('id','email');
            if ($table === 'monitors' && $dstSchool) {
                $dst->join('monitors_schools','monitors.id','=','monitors_schools.monitor_id')->where('monitors_schools.school_id',$dstSchool);
            }
            $dstRows = $dst->get()->keyBy('email');
            foreach ($srcRows as $r) { if (isset($dstRows[$r->email])) $map[$r->id] = $dstRows[$r->email]->id; }
        } catch (\Throwable $e) {}
        return $map;
    }

    private function buildCourseMap(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool): array
    {
        $devHasSlug = Schema::connection($srcDb)->hasColumn('courses','slug');
        $proHasSlug = Schema::connection($dstDb)->hasColumn('courses','slug');
        $dev = DB::connection($srcDb)->table('courses')->select('id','name'); if ($devHasSlug) $dev->addSelect('slug');
        $pro = DB::connection($dstDb)->table('courses')->select('id','name'); if ($proHasSlug) $pro->addSelect('slug');
        $devCourses = $dev->where('school_id',$srcSchool)->get();
        $prodCourses = $pro->where('school_id',$dstSchool)->get();
        $byName=[]; $bySlug=[]; foreach($prodCourses as $pc){ $byName[mb_strtolower(trim($pc->name))]=$pc->id; if($proHasSlug && $pc->slug){ $bySlug[mb_strtolower(trim($pc->slug))]=$pc->id; }}
        $map=[]; foreach($devCourses as $dc){ $nk=mb_strtolower(trim($dc->name)); $sk=$devHasSlug && $dc->slug? mb_strtolower(trim($dc->slug)) : null; if(isset($byName[$nk])) $map[$dc->id]=$byName[$nk]; elseif($sk && isset($bySlug[$sk])) $map[$dc->id]=$bySlug[$sk]; }
        return $map;
    }

    private function buildCourseDateMap(string $srcDb, string $dstDb, array $courseMap, ?string $srcStartCol, ?string $srcEndCol, ?string $dstStartCol, ?string $dstEndCol): array
    {
        $map = [];
        if (empty($courseMap)) return $map; $needTime = $srcStartCol && $srcEndCol && $dstStartCol && $dstEndCol;
        $devDates = DB::connection($srcDb)->table('course_dates')->whereIn('course_id', array_keys($courseMap))->select('id','course_id','date'); if ($needTime) $devDates->addSelect($srcStartCol.' as hs', $srcEndCol.' as he'); $devDates=$devDates->get();
        foreach ($devDates as $d) { $prodCourseId = $courseMap[$d->course_id] ?? null; if(!$prodCourseId) continue; $q = DB::connection($dstDb)->table('course_dates')->where('course_id',$prodCourseId)->where('date',$d->date); if($needTime){ $q->where($dstStartCol,$d->hs)->where($dstEndCol,$d->he);} $prod=$q->first(); if($prod){ $map[$d->id]=$prod->id; } }
        return $map;
    }

    private function resolveProdDegree(string $srcDb, string $dstDb, $devDegreeId, int $dstSchool, ?int $prodSportId): ?int
    {
        if (!$devDegreeId) return null;
        try { $dd = DB::connection($srcDb)->table('degrees')->where('id',$devDegreeId)->first(); if(!$dd) return null; $q=DB::connection($dstDb)->table('degrees')->where('school_id',$dstSchool)->where('name',$dd->name); if(Schema::connection($dstDb)->hasColumn('degrees','level') && isset($dd->level)) $q->where('level',$dd->level); if(Schema::connection($dstDb)->hasColumn('degrees','annotation') && isset($dd->annotation)) $q->where('annotation',$dd->annotation); if(Schema::connection($dstDb)->hasColumn('degrees','sport_id') && $prodSportId) $q->where('sport_id',$prodSportId); $pd=$q->first(); return $pd?->id; } catch(\Throwable $e){ return null; }
    }

    private function buildSubgroupMap(string $srcDb, string $dstDb, array $courseMap, array $courseDateMap, int $srcSchool, int $dstSchool): array
    {
        $map = [];
        // Dev groups for mapped courses
        $srcGroups = DB::connection($srcDb)->table('course_groups')->whereIn('course_id', array_keys($courseMap) ?: [-1])->pluck('id')->toArray();
        if (empty($srcGroups)) return $map;
        $subs = DB::connection($srcDb)->table('course_subgroups')->whereIn('course_group_id',$srcGroups)->get();
        foreach ($subs as $sg) {
            $prodGroupId = $this->resolveProdGroupId($srcDb, $dstDb, $sg->course_group_id, $courseMap, $courseDateMap, $srcSchool, $dstSchool);
            if (!$prodGroupId) continue;
            $q = DB::connection($dstDb)->table('course_subgroups')->where('course_group_id',$prodGroupId);
            if (Schema::connection($dstDb)->hasColumn('course_subgroups','course_id') && isset($sg->course_id)) {
                $prodCourseId = $courseMap[$sg->course_id] ?? null;
                $prodCourseId ? $q->where('course_id',$prodCourseId) : $q->whereNull('course_id');
            }
            if (Schema::connection($dstDb)->hasColumn('course_subgroups','course_date_id') && isset($sg->course_date_id)) {
                $prodCourseDateId = $courseDateMap[$sg->course_date_id] ?? null;
                $prodCourseDateId ? $q->where('course_date_id',$prodCourseDateId) : $q->whereNull('course_date_id');
            }
            if (Schema::connection($dstDb)->hasColumn('course_subgroups','degree_id') && isset($sg->degree_id)) {
                // approximate: ignore degree exact mapping here; will be set on booking_user
            }
            if (Schema::connection($dstDb)->hasColumn('course_subgroups','max_participants')) {
                $q->where('max_participants', $sg->max_participants ?? null);
            }
            $match = $q->first();
            if ($match) $map[$sg->id] = $match->id;
        }
        return $map;
    }

    private function resolveProdGroupId(string $srcDb, string $dstDb, int $devGroupId, array $courseMap, array $courseDateMap, int $srcSchool, int $dstSchool): ?int
    {
        $g = DB::connection($srcDb)->table('course_groups')->where('id', $devGroupId)->first();
        if (!$g) return null;
        $prodCourseId = $courseMap[$g->course_id] ?? null; if (!$prodCourseId) return null;
        $hasDegSrc = Schema::connection($srcDb)->hasColumn('course_groups','degree_id');
        $hasDegDst = Schema::connection($dstDb)->hasColumn('course_groups','degree_id');
        $hasCDSrc  = Schema::connection($srcDb)->hasColumn('course_groups','course_date_id');
        $hasCDDst  = Schema::connection($dstDb)->hasColumn('course_groups','course_date_id');
        $prodDegreeId = null; if ($hasDegSrc && $hasDegDst && $g->degree_id) { $prodSportId = DB::connection($dstDb)->table('courses')->where('id',$prodCourseId)->value('sport_id'); $prodDegreeId = $this->resolveProdDegree($srcDb,$dstDb,$g->degree_id,$dstSchool,$prodSportId); }
        $prodCourseDateId = null; if ($hasCDSrc && $hasCDDst && $g->course_date_id) { $prodCourseDateId = $courseDateMap[$g->course_date_id] ?? null; }
        $q = DB::connection($dstDb)->table('course_groups')->where('course_id',$prodCourseId);
        if ($hasDegDst) { $prodDegreeId ? $q->where('degree_id',$prodDegreeId) : $q->whereNull('degree_id'); }
        if ($hasCDDst)  { $prodCourseDateId ? $q->where('course_date_id',$prodCourseDateId) : $q->whereNull('course_date_id'); }
        foreach (['age_min','age_max','recommended_age','teachers_min','teachers_max','teacher_min_degree','auto'] as $col) { if (Schema::connection($dstDb)->hasColumn('course_groups',$col)) { $q->where($col, $g->{$col} ?? null); } }
        $match = $q->first(); return $match?->id;
    }

    private function buildBookingMap(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool, array $clientMap): array
    {
        $map = [];
        $srcBookings = DB::connection($srcDb)->table('bookings')->where('school_id',$srcSchool)->select('id','client_main_id','price_total','currency','created_at')->get();
        foreach ($srcBookings as $b) {
            $clientDst = $clientMap[$b->client_main_id] ?? null; if (!$clientDst) continue;
            // Buscar match por client_main_id + price_total + fecha (mismo día)
            $q = DB::connection($dstDb)->table('bookings')->where('school_id',$dstSchool)->where('client_main_id',$clientDst)->where('price_total',$b->price_total)->where('currency',$b->currency);
            // restringir por día de creación
            $date = substr((string)$b->created_at,0,10);
            if ($date) { $q->whereDate('created_at', $date); }
            $cand = $q->orderBy('id')->first();
            if ($cand) { $map[$b->id] = $cand->id; }
        }
        return $map;
    }
}
