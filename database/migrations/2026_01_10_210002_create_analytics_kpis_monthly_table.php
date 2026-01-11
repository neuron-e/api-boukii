<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_kpis_monthly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->date('month')->nullable();

            $table->unsignedInteger('total_bookings')->default(0);
            $table->unsignedInteger('production_bookings')->default(0);
            $table->unsignedInteger('cancelled_bookings')->default(0);
            $table->unsignedInteger('test_bookings')->default(0);
            $table->unsignedInteger('total_clients')->default(0);
            $table->unsignedInteger('total_participants')->default(0);

            $table->decimal('revenue_expected', 15, 2)->default(0);
            $table->decimal('revenue_received', 15, 2)->default(0);
            $table->decimal('revenue_pending', 15, 2)->default(0);
            $table->decimal('overpayment_amount', 15, 2)->default(0);
            $table->decimal('unpaid_with_debt_amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['school_id', 'season_id'], 'akm_school_season_idx');
            $table->index(['school_id', 'season_id', 'month'], 'akm_school_season_month_idx');

            // Foreign keys intentionally omitted to avoid mismatches with legacy schemas.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_kpis_monthly');
    }
};
