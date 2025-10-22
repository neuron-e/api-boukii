<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('discounts_codes', 'name')) {
                $table->string('name')->nullable()->after('code');
            }

            if (!Schema::hasColumn('discounts_codes', 'applicable_to')) {
                $table->string('applicable_to')->default('all')->after('max_discount_amount');
            }

            if (!Schema::hasColumn('discounts_codes', 'client_ids')) {
                $table->json('client_ids')->nullable()->after('course_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            if (Schema::hasColumn('discounts_codes', 'client_ids')) {
                $table->dropColumn('client_ids');
            }
            if (Schema::hasColumn('discounts_codes', 'applicable_to')) {
                $table->dropColumn('applicable_to');
            }
            if (Schema::hasColumn('discounts_codes', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
