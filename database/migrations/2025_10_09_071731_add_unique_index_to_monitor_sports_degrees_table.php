<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, remove duplicates before adding the unique constraint
        DB::statement("
            DELETE t1 FROM monitor_sports_degrees t1
            INNER JOIN monitor_sports_degrees t2
            WHERE t1.id > t2.id
                AND t1.monitor_id = t2.monitor_id
                AND t1.sport_id = t2.sport_id
                AND t1.school_id = t2.school_id
        ");

        Schema::table('monitor_sports_degrees', function (Blueprint $table) {
            // Add unique index to prevent duplicates
            $table->unique(['monitor_id', 'sport_id', 'school_id'], 'monitor_sport_school_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitor_sports_degrees', function (Blueprint $table) {
            $table->dropUnique('monitor_sport_school_unique');
        });
    }
};
