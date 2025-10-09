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
            DELETE t1 FROM clients_sports t1
            INNER JOIN clients_sports t2
            WHERE t1.id > t2.id
                AND t1.client_id = t2.client_id
                AND t1.sport_id = t2.sport_id
                AND t1.school_id = t2.school_id
        ");

        Schema::table('clients_sports', function (Blueprint $table) {
            // Add unique index to prevent duplicates
            $table->unique(['client_id', 'sport_id', 'school_id'], 'client_sport_school_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients_sports', function (Blueprint $table) {
            $table->dropUnique('client_sport_school_unique');
        });
    }
};
