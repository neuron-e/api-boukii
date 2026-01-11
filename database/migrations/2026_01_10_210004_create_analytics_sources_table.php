<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->date('month')->nullable();
            $table->string('source', 50);

            $table->unsignedInteger('bookings')->default(0);
            $table->decimal('revenue_expected', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['school_id', 'season_id'], 'as_school_season_idx');
            $table->index(['school_id', 'season_id', 'month'], 'as_school_season_month_idx');
            $table->index(['school_id', 'season_id', 'source'], 'as_school_season_source_idx');

            // Foreign keys intentionally omitted to avoid mismatches with legacy schemas.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_sources');
    }
};
