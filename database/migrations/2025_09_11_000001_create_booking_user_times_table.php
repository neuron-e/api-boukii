<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('booking_user_times')) {
            Schema::create('booking_user_times', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('school_id')->nullable();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('booking_user_id');
                $table->unsignedBigInteger('client_id')->nullable();
                $table->dateTime('date');
                $table->unsignedBigInteger('time_ms');
                $table->string('source')->nullable();
                $table->string('external_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['course_id']);
                $table->index(['booking_user_id']);
                $table->index(['client_id']);
                $table->index(['school_id']);
                $table->unique(['booking_user_id', 'date', 'time_ms', 'source', 'external_id'], 'times_uidx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_user_times');
    }
};
