<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompareSchoolDataCommand extends Command
{
    protected $signature = 'boukii:compare-school-data
        {--source-db=boukii_dev : Conexion origen}
        {--target-db=boukii_pro : Conexion destino}
        {--src-school= : School ID origen}
        {--dst-school= : School ID destino}
        {--report= : Ruta para volcar el reporte JSON}
        {--limit=10 : Limite de ejemplos en diferencias}';

    protected $description = 'Compara datos de una escuela entre dos BDs (dev→prod) para verificar identidad (extrapolando IDs).';

    public function handle(): int
    {
        $srcDb = (string) $this->option('source-db');
        $dstDb = (string) $this->option('target-db');
        $srcSchool = (int) $this->option('src-school');
        $dstSchool = (int) $this->option('dst-school');
        $limit = (int) $this->option('limit');

        if (!$srcSchool || !$dstSchool) {
            $this->error('Faltan --src-school y/o --dst-school');
            return 1;
        }

        $report = [
            'params' => compact('srcDb', 'dstDb', 'srcSchool', 'dstSchool'),
            'summary' => [],
            'diffs' => [],
        ];

        // 1) Mapas básicos de IDs por equivalencias estables
        $userMap = $this->mapByEmail($srcDb, $dstDb, 'users');
        $clientMap = $this->mapByEmail($srcDb, $dstDb, 'clients');
        $monitorMap = $this->mapByEmail($srcDb, $dstDb, 'monitors');

        $seasonMap = $this->mapSeasonsByName($srcDb, $dstDb, $srcSchool, $dstSchool);
        $courseMap = $this->mapCoursesByNameSeason($srcDb, $dstDb, $srcSchool, $dstSchool, $seasonMap);

        // 2) Conteos comparativos
        $report['summary']['counts'] = [
            'bookings' => $this->compareCount($srcDb, $dstDb, 'bookings', $srcSchool, $dstSchool),
            'booking_users' => $this->compareBookingUsersCount($srcDb, $dstDb, $srcSchool, $dstSchool),
            'booking_user_extras' => $this->compareBookingUserExtrasCount($srcDb, $dstDb, $srcSchool, $dstSchool),
            'booking_logs' => $this->compareRelatedCount($srcDb, $dstDb, 'booking_logs', $srcSchool, $dstSchool),
            'payments' => $this->compareRelatedCount($srcDb, $dstDb, 'payments', $srcSchool, $dstSchool),
            'vouchers_log' => $this->compareRelatedCount($srcDb, $dstDb, 'vouchers_log', $srcSchool, $dstSchool),
        ];

        // 3) Detección básica de bookings faltantes en destino (por mapeo de (course_id, client_id))
        $missing = $this->findMissingBookings($srcDb, $dstDb, $srcSchool, $dstSchool, $courseMap, $clientMap, $limit);
        $report['diffs']['bookings_missing_in_target'] = $missing;

        // 4) Salida
        if ($path = $this->option('report')) {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Reporte guardado en: {$path}");
        }

        // Resumen corto en consola
        $this->info('Resumen conteos (src vs dst):');
        foreach ($report['summary']['counts'] as $k => $v) {
            $this->line(sprintf(" - %s: %d vs %d", $k, $v['src'] ?? -1, $v['dst'] ?? -1));
        }
        if (!empty($missing)) {
            $this->warn('Bookings faltantes (ejemplos):');
            foreach ($missing as $row) {
                $this->line(sprintf("   booking_id_src=%d, src(course_id=%d,client_id=%d)", $row['booking_id_src'], $row['course_id_src'], $row['client_id_src']));
            }
        } else {
            $this->info('No se detectaron bookings faltantes en el muestreo.');
        }

        return 0;
    }

    private function mapByEmail(string $srcDb, string $dstDb, string $table): array
    {
        $map = [];
        try {
            $src = DB::connection($srcDb)->table($table)->whereNotNull('email')->select('id','email')->get();
            $dst = DB::connection($dstDb)->table($table)->whereNotNull('email')->select('id','email')->get()->keyBy('email');
            foreach ($src as $row) {
                if (isset($dst[$row->email])) {
                    $map[$row->id] = $dst[$row->email]->id;
                }
            }
        } catch (\Throwable $e) {}
        return $map;
    }

    private function mapSeasonsByName(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool): array
    {
        $map = [];
        try {
            $src = DB::connection($srcDb)->table('seasons')->where('school_id', $srcSchool)->select('id','name')->get();
            $dst = DB::connection($dstDb)->table('seasons')->where('school_id', $dstSchool)->select('id','name')->get()->keyBy('name');
            foreach ($src as $row) {
                if (isset($dst[$row->name])) {
                    $map[$row->id] = $dst[$row->name]->id;
                }
            }
        } catch (\Throwable $e) {}
        return $map;
    }

    private function mapCoursesByNameSeason(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool, array $seasonMap): array
    {
        $map = [];
        try {
            $src = DB::connection($srcDb)->table('courses')->where('school_id', $srcSchool)->select('id','name','season_id')->get();
            $dst = DB::connection($dstDb)->table('courses')->where('school_id', $dstSchool)->select('id','name','season_id')->get();
            // Index destino por (name, season_dst)
            $index = [];
            foreach ($dst as $row) {
                $index[$row->name.'#'.$row->season_id] = $row->id;
            }
            foreach ($src as $row) {
                $seasonDst = $seasonMap[$row->season_id] ?? null;
                if ($seasonDst && isset($index[$row->name.'#'.$seasonDst])) {
                    $map[$row->id] = $index[$row->name.'#'.$seasonDst];
                }
            }
        } catch (\Throwable $e) {}
        return $map;
    }

    private function compareCount(string $srcDb, string $dstDb, string $table, int $srcSchool, int $dstSchool): array
    {
        try {
            $src = DB::connection($srcDb)->table($table)->where('school_id', $srcSchool)->count();
        } catch (\Throwable $e) { $src = -1; }
        try {
            $dst = DB::connection($dstDb)->table($table)->where('school_id', $dstSchool)->count();
        } catch (\Throwable $e) { $dst = -1; }
        return ['src' => $src, 'dst' => $dst];
    }

    private function compareRelatedCount(string $srcDb, string $dstDb, string $table, int $srcSchool, int $dstSchool): array
    {
        try {
            $srcBookings = DB::connection($srcDb)->table('bookings')->where('school_id', $srcSchool)->pluck('id')->toArray();
            $src = empty($srcBookings) ? 0 : DB::connection($srcDb)->table($table)->whereIn('booking_id', $srcBookings)->count();
        } catch (\Throwable $e) { $src = -1; }
        try {
            $dstBookings = DB::connection($dstDb)->table('bookings')->where('school_id', $dstSchool)->pluck('id')->toArray();
            $dst = empty($dstBookings) ? 0 : DB::connection($dstDb)->table($table)->whereIn('booking_id', $dstBookings)->count();
        } catch (\Throwable $e) { $dst = -1; }
        return ['src' => $src, 'dst' => $dst];
    }

    private function compareBookingUsersCount(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool): array
    {
        try {
            $srcBookings = DB::connection($srcDb)->table('bookings')->where('school_id', $srcSchool)->pluck('id')->toArray();
            $src = empty($srcBookings) ? 0 : DB::connection($srcDb)->table('booking_users')->whereIn('booking_id', $srcBookings)->count();
        } catch (\Throwable $e) { $src = -1; }
        try {
            $dstBookings = DB::connection($dstDb)->table('bookings')->where('school_id', $dstSchool)->pluck('id')->toArray();
            $dst = empty($dstBookings) ? 0 : DB::connection($dstDb)->table('booking_users')->whereIn('booking_id', $dstBookings)->count();
        } catch (\Throwable $e) { $dst = -1; }
        return ['src' => $src, 'dst' => $dst];
    }

    private function compareBookingUserExtrasCount(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool): array
    {
        try {
            $srcBU = DB::connection($srcDb)->table('bookings')->where('school_id', $srcSchool)->pluck('id')->toArray();
            $srcBU = empty($srcBU) ? [] : DB::connection($srcDb)->table('booking_users')->whereIn('booking_id', $srcBU)->pluck('id')->toArray();
            $src = empty($srcBU) ? 0 : DB::connection($srcDb)->table('booking_user_extras')->whereIn('booking_user_id', $srcBU)->count();
        } catch (\Throwable $e) { $src = -1; }
        try {
            $dstBU = DB::connection($dstDb)->table('bookings')->where('school_id', $dstSchool)->pluck('id')->toArray();
            $dstBU = empty($dstBU) ? [] : DB::connection($dstDb)->table('booking_users')->whereIn('booking_id', $dstBU)->pluck('id')->toArray();
            $dst = empty($dstBU) ? 0 : DB::connection($dstDb)->table('booking_user_extras')->whereIn('booking_user_id', $dstBU)->count();
        } catch (\Throwable $e) { $dst = -1; }
        return ['src' => $src, 'dst' => $dst];
    }

    private function findMissingBookings(string $srcDb, string $dstDb, int $srcSchool, int $dstSchool, array $courseMap, array $clientMap, int $limit): array
    {
        $missing = [];
        try {
            $srcRows = DB::connection($srcDb)->table('bookings')
                ->where('school_id', $srcSchool)
                ->select('id as booking_id_src','client_main_id as client_id_src','price_total','currency','created_at')
                ->get();
            foreach ($srcRows as $row) {
                $clientDst = $clientMap[$row->client_id_src] ?? null;
                if (!$clientDst) {
                    $missing[] = (array)$row;
                    if (count($missing) >= $limit) break;
                    continue;
                }
                $found = DB::connection($dstDb)->table('bookings')
                    ->where('school_id', $dstSchool)
                    ->where('client_main_id', $clientDst)
                    ->where('price_total', $row->price_total)
                    ->where('currency', $row->currency)
                    ->exists();
                if (!$found) {
                    $missing[] = (array)$row;
                    if (count($missing) >= $limit) break;
                }
            }
        } catch (\Throwable $e) {}
        return $missing;
    }
}
