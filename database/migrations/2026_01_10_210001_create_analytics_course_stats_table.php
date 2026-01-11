<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_course_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->unsignedBigInteger('course_id');
            $table->unsignedTinyInteger('course_type')->nullable();
            $table->unsignedBigInteger('sport_id')->nullable();
            $table->date('month')->nullable();

            $table->string('source', 50)->nullable();
            $table->string('payment_method', 50)->nullable();

            $table->unsignedInteger('participants')->default(0);
            $table->unsignedInteger('bookings')->default(0);
            $table->decimal('revenue_expected', 15, 2)->default(0);
            $table->decimal('revenue_received', 15, 2)->default(0);
            $table->decimal('revenue_pending', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['school_id', 'season_id'], 'acs_school_season_idx');
            $table->index(['school_id', 'season_id', 'month'], 'acs_school_season_month_idx');
            $table->index(['school_id', 'season_id', 'course_id'], 'acs_school_season_course_idx');
            $table->index(['school_id', 'season_id', 'source'], 'acs_school_season_source_idx');
            $table->index(['school_id', 'season_id', 'payment_method'], 'acs_school_season_payment_idx');
            $table->index(['school_id', 'season_id', 'course_type'], 'acs_school_season_type_idx');

            // Foreign keys intentionally omitted to avoid mismatches with legacy schemas.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_course_stats');
    }
};
