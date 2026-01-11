<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->date('month')->nullable();
            $table->string('payment_method', 50);

            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('revenue_received', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['school_id', 'season_id'], 'apm_school_season_idx');
            $table->index(['school_id', 'season_id', 'month'], 'apm_school_season_month_idx');
            $table->index(['school_id', 'season_id', 'payment_method'], 'apm_school_season_method_idx');

            // Foreign keys intentionally omitted to avoid mismatches with legacy schemas.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_payment_methods');
    }
};
