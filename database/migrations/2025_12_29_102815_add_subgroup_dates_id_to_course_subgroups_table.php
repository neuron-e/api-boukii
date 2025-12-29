<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade la columna subgroup_dates_id para identificar subgrupos homónimos
     * que comparten el mismo curso, grupo y nivel a través de diferentes fechas
     */
    public function up(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            // Añadir columna subgroup_dates_id después de course_group_id
            $table->string('subgroup_dates_id', 50)->nullable()->after('course_group_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subgroups', function (Blueprint $table) {
            $table->dropColumn('subgroup_dates_id');
        });
    }
};
