<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseInterval;
use App\Models\CourseDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateExistingCoursesToIntervals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courses:migrate-intervals {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar cursos existentes V3 al sistema de intervalos V4 (retrocompatible)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Ejecutando en modo DRY-RUN (sin cambios reales)');
        }

        $this->info('🚀 Iniciando migración de cursos al sistema de intervalos V4...');

        // Obtener todos los cursos que no tienen intervals_config_mode configurado
        $courses = Course::whereNull('intervals_config_mode')
            ->orWhere('intervals_config_mode', 'unified')
            ->get();

        $this->info("📊 Total de cursos a procesar: {$courses->count()}");

        $bar = $this->output->createProgressBar($courses->count());
        $bar->start();

        $stats = [
            'processed' => 0,
            'intervals_created' => 0,
            'dates_linked' => 0,
            'errors' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($courses as $course) {
                try {
                    $this->migrateCourse($course, $stats, $dryRun);
                    $stats['processed']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $this->error("\n❌ Error al procesar curso ID {$course->id}: {$e->getMessage()}");
                }
                $bar->advance();
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine(2);
                $this->info('🔄 Cambios revertidos (dry-run)');
            } else {
                DB::commit();
                $this->newLine(2);
                $this->info('✅ Migración completada y confirmada');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ Error fatal en la migración: {$e->getMessage()}");
            return 1;
        }

        $bar->finish();

        // Mostrar estadísticas
        $this->newLine(2);
        $this->info('📈 Estadísticas de migración:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Cursos procesados', $stats['processed']],
                ['Intervalos creados', $stats['intervals_created']],
                ['Fechas vinculadas', $stats['dates_linked']],
                ['Errores', $stats['errors']],
            ]
        );

        return 0;
    }

    private function migrateCourse(Course $course, array &$stats, bool $dryRun): void
    {
        // 1. Asegurar que el curso tenga intervals_config_mode = 'unified'
        if (!$dryRun) {
            $course->intervals_config_mode = 'unified';
            $course->save();
        }

        // 2. Obtener todos los intervals únicos del curso (basado en interval_id de course_dates)
        $intervalIds = CourseDate::where('course_id', $course->id)
            ->whereNotNull('interval_id')
            ->distinct()
            ->pluck('interval_id')
            ->sort()
            ->values();

        if ($intervalIds->isEmpty()) {
            // Si no hay intervalos, crear uno único por defecto
            $this->createDefaultInterval($course, $stats, $dryRun);
            return;
        }

        // 3. Crear un CourseInterval por cada interval_id único
        foreach ($intervalIds as $index => $intervalId) {
            $this->createIntervalFromLegacy($course, $intervalId, $index, $stats, $dryRun);
        }
    }

    private function createDefaultInterval(Course $course, array &$stats, bool $dryRun): void
    {
        if ($dryRun) {
            $stats['intervals_created']++;
            return;
        }

        $interval = CourseInterval::create([
            'course_id' => $course->id,
            'name' => 'Período único',
            'start_date' => $course->date_start,
            'end_date' => $course->date_end,
            'display_order' => 0,
            'config_mode' => 'inherit',
            'booking_mode' => $course->is_flexible ? 'flexible' : 'package',
        ]);

        $stats['intervals_created']++;

        // Vincular todas las fechas del curso a este intervalo
        $linkedCount = CourseDate::where('course_id', $course->id)
            ->update(['course_interval_id' => $interval->id]);

        $stats['dates_linked'] += $linkedCount;
    }

    private function createIntervalFromLegacy(Course $course, int $intervalId, int $index, array &$stats, bool $dryRun): void
    {
        // Obtener fechas de este intervalo
        $dates = CourseDate::where('course_id', $course->id)
            ->where('interval_id', $intervalId)
            ->orderBy('date')
            ->get();

        if ($dates->isEmpty()) {
            return;
        }

        $startDate = $dates->first()->date;
        $endDate = $dates->last()->date;

        if ($dryRun) {
            $stats['intervals_created']++;
            $stats['dates_linked'] += $dates->count();
            return;
        }

        $interval = CourseInterval::create([
            'course_id' => $course->id,
            'name' => "Intervalo " . ($index + 1),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'display_order' => $index,
            'config_mode' => 'inherit',
            'booking_mode' => $course->is_flexible ? 'flexible' : 'package',
        ]);

        $stats['intervals_created']++;

        // Vincular las fechas de este interval_id al nuevo CourseInterval
        $linkedCount = CourseDate::where('course_id', $course->id)
            ->where('interval_id', $intervalId)
            ->update(['course_interval_id' => $interval->id]);

        $stats['dates_linked'] += $linkedCount;
    }
}
