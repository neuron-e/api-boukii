<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\FinanceController;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Traits\FinanceCacheKeyTrait;
class PrecalculateDashboards extends Command
{
    use FinanceCacheKeyTrait;

    protected $signature = 'dashboard:precalculate
                          {--school= : ID de escuela específica}
                          {--force : Forzar recálculo aunque exista cache}
                          {--season= : ID de temporada específica}';

    protected $description = 'Pre-calcular dashboards financieros con cache keys consistentes';

    public function handle()
    {
        $this->info('🚀 Iniciando pre-cálculo de dashboards con cache consistente...');

        $schools = $this->option('school')
            ? School::where('id', $this->option('school'))->get()
            : School::where('active', true)->get();

        $bar = $this->output->createProgressBar($schools->count());

        foreach ($schools as $school) {
            $this->precalculateForSchool($school);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Pre-cálculo completado con cache keys consistentes');
    }

    private function precalculateForSchool(School $school): void
    {
        try {
            $controller = new FinanceController(app()->make(\App\Http\Services\BookingPriceCalculatorService::class));

            // Pre-calcular para diferentes niveles
            $levels = ['fast', 'balanced'];

            foreach ($levels as $level) {

                // 🚀 OBTENER FECHAS REALES DE LA TEMPORADA
                $seasonDates = $this->getCurrentSeasonDates($school->id, $this->option('season'));

                // 🚀 GENERAR CACHE KEY IGUAL QUE EL DASHBOARD REAL
                $cacheKey = $this->generateFinanceCacheKey(
                    $school->id,
                    $seasonDates['start_date'],
                    $seasonDates['end_date'],
                    $seasonDates['season_id'],
                    $level
                );

                $this->line("  🔍 Cache key: {$cacheKey}");
                $this->line("  📅 Fechas: {$seasonDates['start_date']} → {$seasonDates['end_date']}");

                if ($this->option('force') || !Cache::has($cacheKey)) {

                    // 🚀 CREAR REQUEST CON FECHAS EXACTAS
                    $request = new \Illuminate\Http\Request([
                        'school_id' => $school->id,
                        'start_date' => $seasonDates['start_date'],
                        'end_date' => $seasonDates['end_date'],
                        'season_id' => $seasonDates['season_id'],
                        'optimization_level' => $level,
                        'include_test_detection' => false,
                        'include_payrexx_analysis' => false,
                        'include_critical_issues' => false,
                        'lean_response' => true,
                        'cache_ttl' => 1800
                    ]);

                    $startTime = microtime(true);
                    $response = $controller->getSeasonFinancialDashboard($request);
                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

                    if ($response->getStatusCode() === 200) {
                        $this->line("  ✅ {$school->name} ({$level}) - {$executionTime}ms");

                        // Verificar que el cache se guardó correctamente
                        if (Cache::has($cacheKey)) {
                            $this->line("  💾 Cache guardado correctamente");
                        } else {
                            $this->warn("  ⚠️  Cache NO se guardó");
                        }
                    } else {
                        $this->error("  ❌ Error en {$school->name} ({$level})");
                    }
                } else {
                    $this->line("  ⚡ {$school->name} ({$level}) - Ya en cache");
                }
            }

        } catch (\Exception $e) {
            Log::channel('cron')->error("Error pre-calculando dashboard para escuela {$school->id}: " . $e->getMessage());
            $this->error("  ❌ Error en {$school->name}: {$e->getMessage()}");
        }
    }
}
