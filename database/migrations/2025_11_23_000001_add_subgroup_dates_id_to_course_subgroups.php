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
            // Verificar si la columna ya existe antes de añadirla
            if (!Schema::hasColumn('course_subgroups', 'subgroup_dates_id')) {
                // Agregar identificador para agrupar subgroups homónimos (misma fecha/nivel)
                // Formato: SG-XXXXXX (e.g., SG-000001, SG-000002)
                // Todos los instancias de "A1" en diferentes fechas compartirán el mismo subgroup_dates_id
                // NOTA: NO es UNIQUE porque múltiples filas (diferentes fechas) comparten el mismo ID
                $table->string('subgroup_dates_id', 50)->nullable()->after('course_group_id');
            }

            // Verificar si el índice ya existe
            if (!$this->indexExists('course_subgroups', 'idx_subgroup_dates_id')) {
                $table->index('subgroup_dates_id', 'idx_subgroup_dates_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            if ($this->indexExists('course_subgroups', 'idx_subgroup_dates_id')) {
                $table->dropIndex('idx_subgroup_dates_id');
            }
            if (Schema::hasColumn('course_subgroups', 'subgroup_dates_id')) {
                $table->dropColumn('subgroup_dates_id');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return count($indexes) > 0;
    }
};
