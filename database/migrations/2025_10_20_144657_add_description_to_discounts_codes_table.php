<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('discounts_codes', 'description')) {
                $table->string('description', 255)->nullable()->after('code')
                    ->comment('Descripción interna del código');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts_codes', function (Blueprint $table) {
            if (Schema::hasColumn('discounts_codes', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
