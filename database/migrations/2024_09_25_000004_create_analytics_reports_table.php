<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEJORA CRÍTICA: Tabla para reportes generados de analytics
 *
 * Almacena reportes detallados y programados para:
 * - Reportes automáticos periódicos
 * - Reportes personalizados por administradores
 * - Exportación de datos históricos
 * - Análisis de tendencias a largo plazo
 * - Auditoría y compliance
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();

            // Información básica del reporte
            $table->string('title', 255);
            $table->string('type', 50)->index(); // summary, detailed, custom, scheduled, export
            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])->default('pending')->index();

            // Configuración del reporte
            $table->json('filters'); // Filtros aplicados
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->json('metrics_requested'); // Métricas solicitadas

            // Datos del reporte generado
            $table->json('summary_data')->nullable();
            $table->json('detailed_data')->nullable();
            $table->text('insights')->nullable(); // Insights automáticos generados
            $table->json('recommendations')->nullable();

            // Metadatos de generación
            $table->bigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->integer('generation_time_seconds')->nullable();
            $table->bigInteger('total_events_analyzed')->default(0);

            // Configuración de archivo y exportación
            $table->string('file_path', 500)->nullable();
            $table->string('file_format', 20)->nullable(); // pdf, csv, xlsx, json
            $table->bigInteger('file_size_bytes')->nullable();
            $table->timestamp('file_expires_at')->nullable();

            // Programación automática (para reportes recurrentes)
            $table->string('schedule_frequency', 50)->nullable(); // daily, weekly, monthly, quarterly
            $table->json('schedule_config')->nullable();
            $table->timestamp('next_scheduled_at')->nullable();

            // Notificaciones y distribución
            $table->json('recipients')->nullable(); // Lista de destinatarios
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['type', 'status']);
            $table->index(['generated_by', 'created_at']);
            $table->index(['start_date', 'end_date']);
            $table->index(['status', 'created_at']);
            $table->index(['schedule_frequency', 'next_scheduled_at']);
            $table->index(['file_expires_at']);

            // Foreign keys
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};