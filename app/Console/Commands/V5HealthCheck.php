<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\User;
use App\Services\V5MonitoringService;
use App\V5\Models\Season;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V5HealthCheck extends Command
{
    protected $signature = 'boukii:v5:health {--user_multi=multi@test.local} {--user_single=single@test.local}';

    protected $description = 'Ejecuta un health check de V5 (DB, tablas, usuarios de prueba, temporadas y sistema de monitorización).';

    public function handle(V5MonitoringService $monitoring): int
    {
        $failures = 0;
        $this->info('🩺 Boukii V5 Health Check');

        // 1) Conectividad DB
        try {
            DB::connection()->getPdo();
            $this->line('✅ DB connection');
        } catch (\Throwable $e) {
            $this->error('❌ DB connection failed: ' . $e->getMessage());
            $failures++;
        }

        // 2) Tablas clave
        $requiredTables = ['users', 'schools', 'seasons', 'personal_access_tokens'];
        foreach ($requiredTables as $t) {
            if (Schema::hasTable($t)) {
                $this->line("✅ Table: {$t}");
            } else {
                $this->error("❌ Missing table: {$t}");
                $failures++;
            }
        }

        // 3) Usuarios de prueba
        $userMultiEmail = (string)$this->option('user_multi');
        $userSingleEmail = (string)$this->option('user_single');
        $userMulti = User::where('email', $userMultiEmail)->first();
        $userSingle = User::where('email', $userSingleEmail)->first();

        if (!$userMulti || !$userSingle) {
            $this->error('❌ Test users not found (ejecuta: php artisan boukii:v5:seed-test-users)');
            $failures++;
        } else {
            $this->line("✅ Test user (multi): {$userMultiEmail}");
            $this->line("✅ Test user (single): {$userSingleEmail}");
        }

        // 4) Schools y temporadas (para users de prueba)
        if ($userMulti) {
            $multiSchoolIds = DB::table('school_users')->where('user_id', $userMulti->id)->pluck('school_id')->toArray();
            $this->line('ℹ️  Multi user schools: ' . implode(',', $multiSchoolIds));
            foreach ($multiSchoolIds as $sid) {
                $actives = Season::where('school_id', $sid)->where('is_active', true)->count();
                if ($actives > 0) {
                    $this->line("   ✅ School {$sid}: {$actives} season(es) activa(s)");
                } else {
                    $this->warn("   ⚠️  School {$sid}: no active seasons");
                }
            }
        }
        if ($userSingle) {
            $singleSchoolIds = DB::table('school_users')->where('user_id', $userSingle->id)->pluck('school_id')->toArray();
            $this->line('ℹ️  Single user schools: ' . implode(',', $singleSchoolIds));
            foreach ($singleSchoolIds as $sid) {
                $actives = Season::where('school_id', $sid)->where('is_active', true)->count();
                if ($actives > 0) {
                    $this->line("   ✅ School {$sid}: {$actives} season(es) activa(s)");
                } else {
                    $this->warn("   ⚠️  School {$sid}: no active seasons");
                }
            }
        }

        // 5) Sistema de monitorización
        try {
            $stats = $monitoring->getSystemStats();
            $status = $stats['system_health']['status'] ?? 'unknown';
            $checks = $stats['system_health']['checks'] ?? [];
            $this->line('ℹ️  Monitoring status: ' . $status);
            foreach ($checks as $name => $ok) {
                $this->line(($ok ? '✅ ' : '❌ ') . $name);
                if (!$ok) $failures++;
            }
        } catch (\Throwable $e) {
            $this->error('❌ Monitoring check failed: ' . $e->getMessage());
            $failures++;
        }

        $this->line('');
        if ($failures > 0) {
            $this->warn("Health check completed with {$failures} issue(s)");
            return 1;
        }
        $this->info('✅ Health check OK');
        return 0;
    }
}

