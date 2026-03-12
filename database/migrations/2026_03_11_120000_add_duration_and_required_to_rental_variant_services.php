<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_variant_services')) {
            return;
        }

        Schema::table('rental_variant_services', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_variant_services', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->default(0)->after('currency');
            }
            if (!Schema::hasColumn('rental_variant_services', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('duration_minutes');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rental_variant_services')) {
            return;
        }

        Schema::table('rental_variant_services', function (Blueprint $table) {
            if (Schema::hasColumn('rental_variant_services', 'is_required')) {
                $table->dropColumn('is_required');
            }
            if (Schema::hasColumn('rental_variant_services', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
        });
    }
};

