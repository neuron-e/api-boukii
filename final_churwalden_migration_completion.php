<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * CONFIG
 * Ajusta school IDs y conexiones si procede
 */
$DEV_CONN   = 'boukii_dev';
$PROD_CONN  = 'boukii_pro';
$SRC_SCHOOL = 13; // DEV
$DST_SCHOOL = 15; // PROD

echo "=== MIGRATE COURSES RELATED (DATES, GROUPS, SUBGROUPS) ===\n";
echo "DEV school_id=$SRC_SCHOOL  →  PROD school_id=$DST_SCHOOL\n\n";

$dev = DB::connection($DEV_CONN);
$pro = DB::connection($PROD_CONN);

/* --------------------------- Helpers --------------------------- */

function logi($m){ echo $m . "\n"; }
function logw($m){ echo "⚠️  $m\n"; }
function loge($m){ echo "❌ $m\n"; }

function hascol($connName,$table,$col){ return Schema::connection($connName)->hasColumn($table,$col); }
function hastable($connName,$table){ return Schema::connection($connName)->hasTable($table); }
function cols($conn,$table){ return Schema::connection($conn->getName())->getColumnListing($table); }

function safeInsert($conn,$table,$data){
    $allowed = array_flip(cols($conn,$table));
    $payload = array_intersect_key($data,$allowed);
    return $conn->table($table)->insert($payload);
}
function safeInsertGetId($conn,$table,$data){
    $allowed = array_flip(cols($conn,$table));
    $payload = array_intersect_key($data,$allowed);
    return $conn->table($table)->insertGetId($payload);
}

/**
 * Mapa cursos DEV→PROD por name/slug (no crea cursos)
 */
function buildCourseMap($dev,$pro,$srcSchool,$dstSchool){
    $devHasSlug = hascol($dev->getName(),'courses','slug');
    $proHasSlug = hascol($pro->getName(),'courses','slug');

    $devCourses = $dev->table('courses')
        ->select('id','name', $devHasSlug?'slug':DB::raw('NULL as slug'))
        ->where('school_id',$srcSchool)->get();

    $pq = $pro->table('courses')->select('id','name');
    if($proHasSlug) $pq->addSelect('slug');
    $prodCourses = $pq->where('school_id',$dstSchool)->get();

    $byName = [];
    $bySlug = [];
    foreach($prodCourses as $pc){
        $byName[mb_strtolower(trim($pc->name))] = $pc->id;
        if($proHasSlug && $pc->slug){
            $bySlug[mb_strtolower(trim($pc->slug))] = $pc->id;
        }
    }

    $map = [];
    foreach($devCourses as $dc){
        $nk = mb_strtolower(trim($dc->name));
        $sk = $dc->slug? mb_strtolower(trim($dc->slug)) : null;

        if(isset($byName[$nk])){ $map[$dc->id] = $byName[$nk]; }
        elseif($sk && isset($bySlug[$sk])){ $map[$dc->id] = $bySlug[$sk]; }
        else { logw("Curso DEV {$dc->id} '{$dc->name}' no encontrado en PROD (name/slug). Se omite su info relacionada."); }
    }

    logi("Cursos mapeados DEV→PROD: ".count($map));
    return $map;
}

/**
 * Índice sport por curso PROD
 * [prod_course_id => sport_id]
 */
function buildProdCourseSport($pro,$dstSchool){
    $rows = $pro->table('courses')->select('id','sport_id')->where('school_id',$dstSchool)->get();
    $idx=[]; foreach($rows as $r){ $idx[$r->id] = $r->sport_id; } return $idx;
}

/**
 * Resolver degree PROD equivalente por (name, level, annotation, sport_id) dentro de school destino,
 * validando además que sport_id coincida con el del curso PROD implicado.
 */
function resolveProdDegree($dev,$pro,$devDegreeId,$dstSchool,$prodCourseSportId){
    foreach(['name','level','annotation','sport_id','school_id'] as $c){
        if(!hascol($dev->getName(),'degrees',$c) || !hascol($pro->getName(),'degrees',$c)){
            logw("Degrees sin columna '$c' en algún entorno. Omito degree $devDegreeId.");
            return null;
        }
    }
    $dd = $dev->table('degrees')->where('id',$devDegreeId)->first();
    if(!$dd){ logw("Degree DEV $devDegreeId no existe."); return null; }
    if($prodCourseSportId===null){ logw("Curso PROD sin sport_id, no puedo validar degree DEV {$dd->id}."); return null; }

    $pd = $pro->table('degrees')
        ->where('school_id',$dstSchool)
        ->where('name',$dd->name)
        ->where('level',$dd->level)
        ->where('annotation',$dd->annotation)
        ->where('sport_id',$prodCourseSportId)
        ->first();

    if($pd) return $pd->id;
    logw("Sin equivalente PROD para degree DEV {$dd->id} ({$dd->name}, level={$dd->level}, ann={$dd->annotation}, sport_id=$prodCourseSportId).");
    return null;
}

/**
 * Mapa de monitores DEV→PROD.
 * Prioridad: email → (first_name,last_name,birth_date) → phone → telephone.
 * Además valida pertenencia a school destino (pivot monitors_schools o active_school).
 * Devuelve [dev_monitor_id => prod_monitor_id]
 */
function buildMonitorMap($dev,$pro,$dstSchool,$srcSchool){
    if(!hastable($dev->getName(),'monitors') || !hastable($pro->getName(),'monitors')) return [];

    $hasPivotDev = hastable($dev->getName(),'monitors_schools');
    $hasPivotPro = hastable($pro->getName(),'monitors_schools');
    $hasActiveSchoolDev = hascol($dev->getName(),'monitors','active_school');
    $hasActiveSchoolPro = hascol($pro->getName(),'monitors','active_school');

    $prodMonitors = $pro->table('monitors')->get();
    $map=[];

    foreach($prodMonitors as $pm){
        // filtra monitores de la escuela destino
        $inSchool = true;
        if($hasPivotPro){
            $inSchool = $pro->table('monitors_schools')
                ->where('monitor_id',$pm->id)->where('school_id',$dstSchool)->exists();
        } elseif ($hasActiveSchoolPro){
            $inSchool = ((int)$pm->active_school === (int)$dstSchool);
        }
        if(!$inSchool) continue;

        $cand = null;
        if(!empty($pm->email)){
            $cand = $dev->table('monitors')->where('email',$pm->email)->first();
        }
        if(!$cand && $pm->first_name && $pm->last_name && $pm->birth_date){
            $cand = $dev->table('monitors')
                ->where('first_name',$pm->first_name)
                ->where('last_name',$pm->last_name)
                ->where('birth_date',$pm->birth_date)
                ->first();
        }
        if(!$cand && !empty($pm->phone)){
            $cand = $dev->table('monitors')->where('phone',$pm->phone)->first();
        }
        if(!$cand && !empty($pm->telephone)){
            $cand = $dev->table('monitors')->where('telephone',$pm->telephone)->first();
        }
        if($cand){
            // (opcional) validar pertenencia a school en DEV
            if($hasPivotDev){
                $inDev = $dev->table('monitors_schools')
                    ->where('monitor_id',$cand->id)->where('school_id',$srcSchool)->exists();
                if(!$inDev && $hasActiveSchoolDev){
                    $inDev = ((int)$cand->active_school === (int)$srcSchool);
                }
                // no bloqueamos si no está; sólo es informativo
            }
            $map[$cand->id] = $pm->id;
        }
    }
    logi("Monitores mapeados DEV→PROD: ".count($map));
    return $map;
}

/**
 * Construye mapa course_date DEV→PROD por (course_id_mapeado + date + hour_start + hour_end).
 * Devuelve [dev_course_date_id => prod_course_date_id]
 */
function buildCourseDateMap($dev,$pro,$courseMap){
    $map = [];
    if(empty($courseMap)) return $map;

    $devDates = $dev->table('course_dates')
        ->whereIn('course_id', array_keys($courseMap))
        ->select('id','course_id','date','hour_start','hour_end','active')
        ->get();

    foreach($devDates as $d){
        $prodCourseId = $courseMap[$d->course_id] ?? null;
        if(!$prodCourseId) continue;

        $prod = $pro->table('course_dates')
            ->where('course_id',$prodCourseId)
            ->where('date',$d->date)
            ->where('hour_start',$d->hour_start)
            ->where('hour_end',$d->hour_end)
            ->first();

        if($prod){ $map[$d->id] = $prod->id; }
    }
    logi("CourseDate mapeados DEV→PROD: ".count($map));
    return $map;
}

/* --------------------------- Build maps base --------------------------- */

$courseMap = buildCourseMap($dev,$pro,$SRC_SCHOOL,$DST_SCHOOL);
if(!$courseMap){ loge("No hay cursos mapeados. Abort."); exit(0); }

$prodCourseSport = buildProdCourseSport($pro,$DST_SCHOOL);
$monitorMap      = buildMonitorMap($dev,$pro,$DST_SCHOOL,$SRC_SCHOOL);

/* --------------------------- 1/3: COURSE_DATES --------------------------- */

logi("\n== 1/3: COURSE_DATES ==");
$addedDates = 0;

$devDates = $dev->table('course_dates')
    ->whereIn('course_id', array_keys($courseMap))
    ->orderBy('course_id')
    ->orderBy('date')
    ->orderBy('hour_start')
    ->get();

$pro->beginTransaction();
try{
    foreach($devDates as $d){
        $prodCourseId = $courseMap[$d->course_id] ?? null;
        if(!$prodCourseId) continue;

        $exists = $pro->table('course_dates')
            ->where('course_id',$prodCourseId)
            ->where('date',$d->date)
            ->where('hour_start',$d->hour_start)
            ->where('hour_end',$d->hour_end)
            ->exists();
        if($exists) continue;

        $payload = [
            'course_id'  => $prodCourseId,
            'date'       => $d->date,
            'hour_start' => $d->hour_start,
            'hour_end'   => $d->hour_end,
            'active'     => $d->active ?? 1,
            'created_at' => $d->created_at,
            'updated_at' => $d->updated_at,
        ];
        // extras si existen en ambas
        foreach(['availability','capacity','interval_id','order'] as $extra){
            if(hascol($dev->getName(),'course_dates',$extra) && hascol($pro->getName(),'course_dates',$extra)){
                $payload[$extra] = $d->{$extra};
            }
        }

        safeInsert($pro,'course_dates',$payload);
        $addedDates++;
    }
    $pro->commit();
    logi("✓ Nuevos course_dates añadidos: $addedDates");
}catch(\Throwable $e){
    $pro->rollBack();
    loge("Error en course_dates: ".$e->getMessage());
    exit(1);
}

/* Mapa definitivo de CourseDate DEV→PROD (incluye los recién creados) */
$courseDateMap = buildCourseDateMap($dev,$pro,$courseMap);

/* --------------------------- 2/3: COURSE_GROUPS --------------------------- */

logi("\n== 2/3: COURSE_GROUPS ==");
$addedGroups = 0; $groupMap = [];

$devGroups = $dev->table('course_groups')
    ->whereIn('course_id', array_keys($courseMap))
    ->orderBy('course_id')
    ->orderBy('course_date_id')
    ->orderBy('degree_id')
    ->get();

$hasDegDev = hascol($dev->getName(),'course_groups','degree_id');
$hasDegPro = hascol($pro->getName(),'course_groups','degree_id');
$hasCDDev  = hascol($dev->getName(),'course_groups','course_date_id');
$hasCDPro  = hascol($pro->getName(),'course_groups','course_date_id');

$pro->beginTransaction();
try{
    foreach($devGroups as $g){
        $prodCourseId = $courseMap[$g->course_id] ?? null;
        if(!$prodCourseId) continue;

        // course_date_id mapeado (si la columna existe)
        $prodCourseDateId = null;
        if($hasCDDev && $hasCDPro && $g->course_date_id){
            $prodCourseDateId = $courseDateMap[$g->course_date_id] ?? null;
            if($prodCourseDateId===null){
                logw("Omito group DEV {$g->id} por no encontrar course_date equivalente.");
                continue;
            }
        }

        // degree_id mapeado (si existe)
        $prodDegreeId = null;
        if($hasDegDev && $hasDegPro && $g->degree_id){
            $prodDegreeId = resolveProdDegree(
                $dev,$pro,(int)$g->degree_id,$DST_SCHOOL,$prodCourseSport[$prodCourseId] ?? null
            );
            if($prodDegreeId===null){
                logw("Omito group DEV {$g->id} por degree sin equivalente.");
                continue;
            }
        }

        // Dedupe por “firma”: (course_id, degree_id?, course_date_id?, y parámetros principales)
        $q = $pro->table('course_groups')->where('course_id',$prodCourseId);
        if($hasDegPro){ $prodDegreeId ? $q->where('degree_id',$prodDegreeId) : $q->whereNull('degree_id'); }
        if($hasCDPro){  $prodCourseDateId ? $q->where('course_date_id',$prodCourseDateId) : $q->whereNull('course_date_id'); }
        $q->where('age_min', $g->age_min ?? null)
            ->where('age_max', $g->age_max ?? null)
            ->where('recommended_age', $g->recommended_age ?? null)
            ->where('teachers_min', $g->teachers_min ?? null)
            ->where('teachers_max', $g->teachers_max ?? null)
            ->where('teacher_min_degree', $g->teacher_min_degree ?? null)
            ->where('auto', $g->auto ?? 1);
        $exists = $q->first();

        if($exists){
            $groupMap[$g->id] = $exists->id;
            continue;
        }

        $payload = [
            'course_id'          => $prodCourseId,
            'age_min'            => $g->age_min ?? null,
            'age_max'            => $g->age_max ?? null,
            'recommended_age'    => $g->recommended_age ?? null,
            'teachers_min'       => $g->teachers_min ?? null,
            'teachers_max'       => $g->teachers_max ?? null,
            'teacher_min_degree' => $g->teacher_min_degree ?? null,
            'observations'       => $g->observations ?? null,
            'auto'               => $g->auto ?? 1,
            'created_at'         => $g->created_at,
            'updated_at'         => $g->updated_at,
        ];
        if($hasDegPro) $payload['degree_id'] = $prodDegreeId;
        if($hasCDPro)  $payload['course_date_id'] = $prodCourseDateId;

        // extras compatibles si existen
        foreach(['active','price','settings'] as $extra){
            if(hascol($dev->getName(),'course_groups',$extra) && hascol($pro->getName(),'course_groups',$extra)){
                $payload[$extra] = $g->{$extra};
            }
        }

        $newId = safeInsertGetId($pro,'course_groups',$payload);
        $groupMap[$g->id] = $newId;
        $addedGroups++;
    }
    $pro->commit();
    logi("✓ Nuevos course_groups añadidos: $addedGroups");
}catch(\Throwable $e){
    $pro->rollBack();
    loge("Error en course_groups: ".$e->getMessage());
    exit(1);
}

/* --------------------------- 3/3: COURSE_SUBGROUPS --------------------------- */

logi("\n== 3/3: COURSE_SUBGROUPS ==");
$addedSubgroups = 0;

if($groupMap){
    $devSubs = $dev->table('course_subgroups')
        ->whereIn('course_group_id', array_keys($groupMap))
        ->orderBy('course_group_id')
        ->orderBy('course_date_id')
        ->orderBy('degree_id')
        ->orderBy('monitor_id')
        ->get();

    $hasMonDev = hascol($dev->getName(),'course_subgroups','monitor_id');
    $hasMonPro = hascol($pro->getName(),'course_subgroups','monitor_id');
    $hasSubDegDev = hascol($dev->getName(),'course_subgroups','degree_id');
    $hasSubDegPro = hascol($pro->getName(),'course_subgroups','degree_id');
    $hasSubCDDev  = hascol($dev->getName(),'course_subgroups','course_date_id');
    $hasSubCDPro  = hascol($pro->getName(),'course_subgroups','course_date_id');
    $hasSubCourseDev = hascol($dev->getName(),'course_subgroups','course_id');
    $hasSubCoursePro = hascol($pro->getName(),'course_subgroups','course_id');

    $pro->beginTransaction();
    try{
        foreach($devSubs as $sg){
            $prodGroupId = $groupMap[$sg->course_group_id] ?? null;
            if(!$prodGroupId) continue;

            // course_id en subgroup (si existe)
            $prodCourseId = null;
            if($hasSubCourseDev && $hasSubCoursePro && $sg->course_id){
                $prodCourseId = $courseMap[$sg->course_id] ?? null;
                if($prodCourseId===null){
                    logw("Omito subgroup DEV {$sg->id} por course_id sin mapping.");
                    continue;
                }
            }

            // course_date_id en subgroup (si existe)
            $prodCourseDateId = null;
            if($hasSubCDDev && $hasSubCDPro && $sg->course_date_id){
                $prodCourseDateId = $courseDateMap[$sg->course_date_id] ?? null;
                if($prodCourseDateId===null){
                    logw("Omito subgroup DEV {$sg->id} por course_date sin mapping.");
                    continue;
                }
            }

            // degree_id en subgroup (si existe)
            $prodDegreeId = null;
            if($hasSubDegDev && $hasSubDegPro && $sg->degree_id){
                // sport del curso PROD: usa course_id del subgroup si lo hay; si no, del group
                $refCourseId = $prodCourseId;
                if(!$refCourseId){
                    $g = $pro->table('course_groups')->select('course_id')->where('id',$prodGroupId)->first();
                    $refCourseId = $g?->course_id;
                }
                $prodDegreeId = resolveProdDegree(
                    $dev,$pro,(int)$sg->degree_id,$DST_SCHOOL,$refCourseId ? ($prodCourseSport[$refCourseId] ?? null) : null
                );
                if($prodDegreeId===null){
                    logw("Omito subgroup DEV {$sg->id} por degree sin equivalente.");
                    continue;
                }
            }

            // monitor_id
            $prodMonitorId = null;
            if($hasMonDev && $hasMonPro && $sg->monitor_id){
                $prodMonitorId = $monitorMap[$sg->monitor_id] ?? null; // si no hay match, se deja null
            }

            // dedupe por “firma” (sin name)
            $q = $pro->table('course_subgroups')->where('course_group_id',$prodGroupId);
            if($hasSubCoursePro){ $prodCourseId ? $q->where('course_id',$prodCourseId) : $q->whereNull('course_id'); }
            if($hasSubCDPro){    $prodCourseDateId ? $q->where('course_date_id',$prodCourseDateId) : $q->whereNull('course_date_id'); }
            if($hasSubDegPro){   $prodDegreeId ? $q->where('degree_id',$prodDegreeId) : $q->whereNull('degree_id'); }
            if($hasMonPro){      $prodMonitorId ? $q->where('monitor_id',$prodMonitorId) : $q->whereNull('monitor_id'); }
            $q->where('max_participants', $sg->max_participants ?? null);

            if($q->exists()) continue;

            $payload = [
                'course_group_id'  => $prodGroupId,
                'max_participants' => $sg->max_participants ?? null,
                'created_at'       => $sg->created_at,
                'updated_at'       => $sg->updated_at,
            ];
            if($hasSubCoursePro) $payload['course_id'] = $prodCourseId;
            if($hasSubCDPro)     $payload['course_date_id'] = $prodCourseDateId;
            if($hasSubDegPro)    $payload['degree_id'] = $prodDegreeId;
            if($hasMonPro)       $payload['monitor_id'] = $prodMonitorId;

            safeInsert($pro,'course_subgroups',$payload);
            $addedSubgroups++;
        }
        $pro->commit();
        logi("✓ Nuevos course_subgroups añadidos: $addedSubgroups");
    }catch(\Throwable $e){
        $pro->rollBack();
        loge("Error en course_subgroups: ".$e->getMessage());
        exit(1);
    }
} else {
    logw("No hay course_groups mapeados → se omiten subgroups.");
}

/* --------------------------- Resumen --------------------------- */

echo "\n=== RESUMEN ===\n";
echo "course_dates     +$addedDates\n";
echo "course_groups    +$addedGroups\n";
echo "course_subgroups +$addedSubgroups\n";
echo "OK\n";
