<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rental_subcategories')) {
            return;
        }

        Schema::table('rental_subcategories', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_subcategories', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('category_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rental_subcategories')) {
            return;
        }

        Schema::table('rental_subcategories', function (Blueprint $table) {
            if (Schema::hasColumn('rental_subcategories', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};

