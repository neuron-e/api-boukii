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
            // Agregar identificador único para agrupar subgroups homónimos (misma fecha/nivel)
            // Formato: SG-XXXXXX (e.g., SG-000001, SG-000002)
            // Todos los instancias de "A1" en diferentes fechas compartirán el mismo subgroup_dates_id
            $table->string('subgroup_dates_id', 50)->nullable()->unique()->after('course_group_id');

            // Índices para búsquedas frecuentes
            $table->index(['course_id', 'degree_id', 'subgroup_dates_id']);
            $table->index(['course_group_id', 'subgroup_dates_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'degree_id', 'subgroup_dates_id']);
            $table->dropIndex(['course_group_id', 'subgroup_dates_id']);
            $table->dropUnique(['subgroup_dates_id']);
            $table->dropColumn('subgroup_dates_id');
        });
    }
};
