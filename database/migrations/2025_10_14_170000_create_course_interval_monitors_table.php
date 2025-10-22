<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sistema de asignación de monitores por intervalo.
     * Permite asignar diferentes monitores a un subgrupo según el intervalo de fechas.
     *
     * Prioridad:
     * 1. CourseIntervalMonitor (este tabla) - Monitor específico para el intervalo
     * 2. CourseSubgroup.monitor_id - Monitor base del subgrupo
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('course_interval_monitors', function (Blueprint $table) {
            $table->id();

            // Relaciones
            // IMPORTANTE: Usar tipos de datos que coincidan con las tablas referenciadas
            $table->bigInteger('course_id')
                ->comment('ID del curso');

            $table->unsignedBigInteger('course_interval_id')
                ->comment('ID del intervalo de fechas');

            $table->bigInteger('course_subgroup_id')
                ->comment('ID del subgrupo al que se asigna el monitor');

            $table->bigInteger('monitor_id')
                ->comment('ID del monitor asignado para este intervalo');

            // Metadatos
            $table->boolean('active')->default(true)
                ->comment('Si la asignación está activa. false = desactivada temporalmente');

            $table->text('notes')->nullable()
                ->comment('Notas sobre la asignación (ej: "Suplente por vacaciones")');

            $table->timestamps();

            // Índices para performance
            $table->unique(['course_interval_id', 'course_subgroup_id'], 'unique_interval_subgroup_monitor');
            $table->index(['course_subgroup_id', 'active'], 'idx_subgroup_active');
            $table->index(['monitor_id', 'active'], 'idx_monitor_active');
            $table->index(['course_interval_id', 'active'], 'idx_interval_active');

            // Foreign keys
            // NOTA: Comentadas temporalmente para permitir ejecución en entornos donde
            // las tablas referenciadas aún no existen. Descomentar en entornos de producción.
            /*
            $table->foreign('course_interval_id')
                ->references('id')
                ->on('course_intervals')
                ->onDelete('cascade');

            $table->foreign('course_subgroup_id')
                ->references('id')
                ->on('course_subgroups')
                ->onDelete('cascade');

            $table->foreign('monitor_id')
                ->references('id')
                ->on('monitors')
                ->onDelete('cascade');

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->onDelete('cascade');
            */
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('course_interval_monitors');
    }
};
