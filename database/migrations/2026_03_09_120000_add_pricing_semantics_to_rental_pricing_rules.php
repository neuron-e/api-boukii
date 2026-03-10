<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_pricing_rules')) {
            return;
        }

        Schema::table('rental_pricing_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_pricing_rules', 'pricing_mode')) {
                $table->string('pricing_mode')->nullable()->after('period_type');
            }
            if (!Schema::hasColumn('rental_pricing_rules', 'min_days')) {
                $table->integer('min_days')->nullable()->after('pricing_mode');
            }
            if (!Schema::hasColumn('rental_pricing_rules', 'max_days')) {
                $table->integer('max_days')->nullable()->after('min_days');
            }
            if (!Schema::hasColumn('rental_pricing_rules', 'priority')) {
                $table->integer('priority')->default(100)->after('max_days');
            }
        });

        DB::table('rental_pricing_rules')
            ->whereNull('pricing_mode')
            ->update([
                'pricing_mode' => DB::raw("CASE WHEN period_type IN ('week', 'season') THEN 'flat' ELSE 'per_day' END"),
            ]);

        DB::table('rental_pricing_rules')
            ->whereNull('priority')
            ->update([
                'priority' => 100,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('rental_pricing_rules')) {
            return;
        }

        Schema::table('rental_pricing_rules', function (Blueprint $table) {
            foreach (['priority', 'max_days', 'min_days', 'pricing_mode'] as $column) {
                if (Schema::hasColumn('rental_pricing_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
