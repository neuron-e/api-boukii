<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEJORA CRÍTICA: Tabla para eventos de analytics y monitoring
 *
 * Almacena todos los eventos de analytics del frontend para:
 * - Tracking de reservas y navegación
 * - Métricas de performance
 * - Detección de errores críticos
 * - Análisis de comportamiento de usuarios
 * - Generación de reportes detallados
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();

            // Identificadores del evento
            $table->string('event_id', 100)->unique()->index();
            $table->string('category', 50)->index(); // booking, navigation, interaction, error, performance, system
            $table->string('action', 100)->index();
            $table->string('label', 255)->nullable();
            $table->decimal('value', 15, 2)->nullable(); // Para métricas numéricas como tiempo de respuesta
            $table->boolean('critical')->default(false)->index();

            // Información de sesión y usuario
            $table->string('session_id', 100)->index();
            $table->bigInteger('user_id')->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('ip_address', 45)->nullable();

            // Metadatos del evento (JSON para flexibilidad)
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamp('timestamp')->index(); // Timestamp del evento desde el frontend
            $table->timestamps(); // Laravel timestamps

            // Índices para optimizar consultas frecuentes
            $table->index(['category', 'action']);
            $table->index(['category', 'timestamp']);
            $table->index(['user_id', 'timestamp']);
            $table->index(['critical', 'timestamp']);
            $table->index(['created_at', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};