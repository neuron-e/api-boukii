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
        Schema::create('course_interval_subgroups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_interval_group_id'); // Unsigned - matches course_interval_groups.id
            $table->unsignedBigInteger('course_subgroup_id'); // Unsigned - matches course_subgroups.id

            // Configuración específica para este subgrupo en este intervalo
            $table->integer('max_participants')->nullable()
                  ->comment('Override del max_participants del subgrupo base. Null = usar del subgrupo base');

            // Estado
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Índices
            $table->index(['course_interval_group_id', 'course_subgroup_id'], 'idx_interval_subgroup');
            $table->unique(['course_interval_group_id', 'course_subgroup_id'], 'unique_interval_subgroup');

            // Foreign keys
            $table->foreign('course_interval_group_id')->references('id')->on('course_interval_groups')->onDelete('cascade');
            $table->foreign('course_subgroup_id')->references('id')->on('course_subgroups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_interval_subgroups');
    }
};
