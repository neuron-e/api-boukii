<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_reservation_lines')) {
            return;
        }

        Schema::table('rental_reservation_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_reservation_lines', 'period_type')) {
                $table->string('period_type')->default('full_day')->after('variant_id');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'start_date')) {
                $table->date('start_date')->nullable()->after('period_type');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'start_time')) {
                $table->time('start_time')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'qty_assigned')) {
                $table->integer('qty_assigned')->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'status')) {
                $table->string('status')->default('pending')->after('qty_assigned');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'service_total')) {
                $table->decimal('service_total', 10, 2)->default(0)->after('line_total');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'discount_total')) {
                $table->decimal('discount_total', 10, 2)->default(0)->after('service_total');
            }
            if (!Schema::hasColumn('rental_reservation_lines', 'notes')) {
                $table->text('notes')->nullable()->after('discount_total');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rental_reservation_lines')) {
            return;
        }

        Schema::table('rental_reservation_lines', function (Blueprint $table) {
            foreach ([
                'notes',
                'discount_total',
                'service_total',
                'status',
                'qty_assigned',
                'end_time',
                'start_time',
                'end_date',
                'start_date',
                'period_type',
            ] as $column) {
                if (Schema::hasColumn('rental_reservation_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

