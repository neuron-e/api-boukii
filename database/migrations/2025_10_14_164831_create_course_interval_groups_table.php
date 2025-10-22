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
        Schema::create('course_interval_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id'); // Unsigned - matches courses.id
            $table->unsignedBigInteger('course_interval_id'); // Unsigned - matches course_intervals.id
            $table->unsignedBigInteger('course_group_id'); // Unsigned - matches course_groups.id

            // Configuración específica para este intervalo
            $table->integer('max_participants')->nullable()
                  ->comment('Override del max_participants del grupo base. Null = usar del grupo');

            // Estado
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Índices
            $table->index(['course_interval_id', 'course_group_id'], 'idx_interval_group');
            $table->unique(['course_interval_id', 'course_group_id'], 'unique_interval_group');

            // Foreign keys
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('course_interval_id')->references('id')->on('course_intervals')->onDelete('cascade');
            $table->foreign('course_group_id')->references('id')->on('course_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_interval_groups');
    }
};
