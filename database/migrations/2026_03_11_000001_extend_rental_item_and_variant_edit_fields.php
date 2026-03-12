<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rental_items')) {
            Schema::table('rental_items', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_items', 'image')) {
                    $table->longText('image')->nullable()->after('description');
                }
            });
        }

        if (Schema::hasTable('rental_variants')) {
            Schema::table('rental_variants', function (Blueprint $table) {
                if (!Schema::hasColumn('rental_variants', 'serial_prefix')) {
                    $table->string('serial_prefix')->nullable()->after('barcode');
                }
                if (!Schema::hasColumn('rental_variants', 'purchase_date')) {
                    $table->date('purchase_date')->nullable()->after('active');
                }
                if (!Schema::hasColumn('rental_variants', 'last_maintenance_date')) {
                    $table->date('last_maintenance_date')->nullable()->after('purchase_date');
                }
                if (!Schema::hasColumn('rental_variants', 'notes')) {
                    $table->text('notes')->nullable()->after('last_maintenance_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rental_items')) {
            Schema::table('rental_items', function (Blueprint $table) {
                if (Schema::hasColumn('rental_items', 'image')) {
                    $table->dropColumn('image');
                }
            });
        }

        if (Schema::hasTable('rental_variants')) {
            Schema::table('rental_variants', function (Blueprint $table) {
                if (Schema::hasColumn('rental_variants', 'notes')) {
                    $table->dropColumn('notes');
                }
                if (Schema::hasColumn('rental_variants', 'last_maintenance_date')) {
                    $table->dropColumn('last_maintenance_date');
                }
                if (Schema::hasColumn('rental_variants', 'purchase_date')) {
                    $table->dropColumn('purchase_date');
                }
                if (Schema::hasColumn('rental_variants', 'serial_prefix')) {
                    $table->dropColumn('serial_prefix');
                }
            });
        }
    }
};

