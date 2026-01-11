<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_activity_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('course_id');
            $table->unsignedTinyInteger('course_type')->nullable();
            $table->unsignedBigInteger('sport_id')->nullable();

            $table->date('activity_date')->nullable();
            $table->date('booking_created_at')->nullable();

            $table->string('source', 50)->nullable();
            $table->string('payment_method', 50)->nullable();

            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_test')->default(false);

            $table->unsignedInteger('participants')->default(0);
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->decimal('pending_amount', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['booking_id', 'group_id', 'activity_date'], 'analytics_activity_facts_booking_group_date_uniq');
            $table->index(['school_id', 'season_id'], 'aaf_school_season_idx');
            $table->index(['school_id', 'season_id', 'activity_date'], 'aaf_school_season_activity_idx');
            $table->index(['school_id', 'season_id', 'booking_created_at'], 'aaf_school_season_booking_idx');
            $table->index(['school_id', 'season_id', 'course_id'], 'aaf_school_season_course_idx');
            $table->index(['school_id', 'season_id', 'source'], 'aaf_school_season_source_idx');
            $table->index(['school_id', 'season_id', 'payment_method'], 'aaf_school_season_payment_idx');
            $table->index(['school_id', 'season_id', 'is_test'], 'aaf_school_season_test_idx');
            $table->index(['school_id', 'season_id', 'is_cancelled'], 'aaf_school_season_cancel_idx');
            $table->index(['school_id', 'season_id', 'client_id'], 'aaf_school_season_client_idx');

            // Foreign keys intentionally omitted to avoid mismatches with legacy schemas.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_activity_facts');
    }
};
