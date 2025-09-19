<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasColumn('course_dates', 'interval_id')) {
            Schema::table('course_dates', function (Blueprint $table) {
                $table->unsignedBigInteger('interval_id')->nullable()->after('hour_end');
            });
        }
        if (!Schema::hasColumn('course_dates', 'order')) {
            Schema::table('course_dates', function (Blueprint $table) {
                $table->integer('order')->nullable()->after('interval_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('course_dates', 'interval_id') || Schema::hasColumn('course_dates', 'order')) {
            Schema::table('course_dates', function (Blueprint $table) {
                $drops = [];
                if (Schema::hasColumn('course_dates', 'interval_id')) { $drops[] = 'interval_id'; }
                if (Schema::hasColumn('course_dates', 'order')) { $drops[] = 'order'; }
                if (!empty($drops)) { $table->dropColumn($drops); }
            });
        }
    }
};
