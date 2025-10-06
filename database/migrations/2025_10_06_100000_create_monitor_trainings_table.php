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
        Schema::create('monitor_trainings', function (Blueprint $table) {
            $table->id();
            $table->integer('monitor_id')->unsigned();
            $table->integer('sport_id')->unsigned();
            $table->integer('school_id')->unsigned();
            $table->string('training_name');
            $table->text('training_proof')->nullable(); // PDF file stored as base64 or file path
            $table->timestamps();
            $table->softDeletes();

            $table->index('monitor_id');
            $table->index('sport_id');
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_trainings');
    }
};
