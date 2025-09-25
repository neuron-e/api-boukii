<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEJORA CRÍTICA: Tabla para alertas críticas del sistema
 *
 * Almacena alertas automáticas y manuales del sistema para:
 * - Errores críticos detectados automáticamente
 * - Problemas de performance severos
 * - Alertas de capacidad y disponibilidad
 * - Seguimiento de resolución de incidencias
 * - Historial de problemas del sistema
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();

            // Información básica de la alerta
            $table->string('type', 50)->index(); // error, performance, capacity, system
            $table->enum('severity', ['info', 'warning', 'critical'])->index();
            $table->string('message', 500);
            $table->json('metadata')->nullable();

            // Origen de la alerta
            $table->string('source', 50)->default('system'); // frontend, backend, auto-generated, manual
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Estado y resolución
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active')->index();
            $table->bigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            // Seguimiento temporal
            $table->timestamp('first_occurrence')->nullable();
            $table->timestamp('last_occurrence')->nullable();
            $table->integer('occurrence_count')->default(1);

            // Notificaciones
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->json('notification_channels')->nullable(); // email, slack, sms, etc.

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['type', 'severity']);
            $table->index(['status', 'created_at']);
            $table->index(['severity', 'status']);
            $table->index(['created_at', 'type']);
            $table->index(['notification_sent', 'severity']);

            // Foreign key para el usuario que resolvió la alerta
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_alerts');
    }
};