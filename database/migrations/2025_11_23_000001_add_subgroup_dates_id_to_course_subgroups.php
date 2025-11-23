<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            // Agregar identificador para agrupar subgroups homónimos (misma fecha/nivel)
            // Formato: SG-XXXXXX (e.g., SG-000001, SG-000002)
            // Todos los instancias de "A1" en diferentes fechas compartirán el mismo subgroup_dates_id
            // NOTA: NO es UNIQUE porque múltiples filas (diferentes fechas) comparten el mismo ID
            $table->string('subgroup_dates_id', 50)->nullable()->after('course_group_id');

            // Simple index for lookups by subgroup_dates_id
            $table->index('subgroup_dates_id', 'idx_subgroup_dates_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex('idx_subgroup_dates_id');
            $table->dropColumn('subgroup_dates_id');
        });
    }
};
