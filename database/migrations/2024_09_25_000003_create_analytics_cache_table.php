<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEJORA CRÍTICA: Tabla para cache persistente de métricas de analytics
 *
 * Almacena métricas calculadas y cacheadas para:
 * - Dashboard de métricas en tiempo real
 * - Reportes agregados por período
 * - Métricas de sistema y performance
 * - Reducir carga de consultas complejas
 * - Historial de tendencias temporales
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_cache', function (Blueprint $table) {
            $table->id();

            // Identificación del cache
            $table->string('cache_key', 255)->unique()->index();
            $table->string('metric_type', 100)->index(); // dashboard, booking, performance, error, user_activity
            $table->string('period', 50)->nullable(); // daily, weekly, monthly, realtime

            // Datos de la métrica
            $table->json('metric_data');
            $table->decimal('numeric_value', 15, 4)->nullable()->index(); // Para consultas numéricas rápidas

            // Configuración de cache
            $table->timestamp('expires_at')->index();
            $table->boolean('is_expired')->default(false)->index();
            $table->integer('hit_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();

            // Metadatos para filtrado y agrupación
            $table->bigInteger('school_id')->nullable()->index();
            $table->string('filters_hash', 64)->nullable(); // Hash de los filtros aplicados
            $table->json('filters')->nullable(); // Filtros originales

            $table->timestamps();

            // Índices para consultas de cache
            $table->index(['metric_type', 'expires_at']);
            $table->index(['school_id', 'metric_type']);
            $table->index(['cache_key', 'expires_at']);
            $table->index(['is_expired', 'expires_at']);
            $table->index(['period', 'metric_type']);

            // Foreign key para school
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_cache');
    }
};