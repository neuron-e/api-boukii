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
        if (!Schema::hasTable('booking_user_times')) {
            Schema::create('booking_user_times', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_user_id');
                $table->unsignedBigInteger('course_date_id');
                $table->integer('lap_no')->nullable()->default(1);
                $table->integer('time_ms')->comment('Time in milliseconds');
                $table->enum('status', ['valid', 'invalid', 'dns', 'dnf'])->default('valid');
                $table->string('source')->nullable()->comment('e.g., microgate_reipro');
                $table->string('device_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                
                // Foreign key constraints
                $table->foreign('booking_user_id')->references('id')->on('booking_users')->onDelete('cascade');
                $table->foreign('course_date_id')->references('id')->on('course_dates')->onDelete('cascade');
                
                // Indexes for performance
                $table->index('booking_user_id');
                $table->index('course_date_id');
                $table->index(['booking_user_id', 'course_date_id']);
                
                // Unique constraint for idempotency (one time per user per course date per lap)
                $table->unique(['booking_user_id', 'course_date_id', 'lap_no'], 'booking_user_times_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_user_times');
    }
};
