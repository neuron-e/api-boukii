<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('school_colors', 'price')) {
            Schema::table('school_colors', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable()->after('default');
            });
        }

        if (!Schema::hasColumn('monitor_nwd', 'price')) {
            Schema::table('monitor_nwd', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->nullable()->after('user_nwd_subtype_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('school_colors', 'price')) {
            Schema::table('school_colors', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }

        if (Schema::hasColumn('monitor_nwd', 'price')) {
            Schema::table('monitor_nwd', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }
    }
};
