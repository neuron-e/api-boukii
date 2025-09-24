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
        // Reset all group_changed values to 0 (default state)
        DB::table('booking_users')->update(['group_changed' => 0]);

        // Also ensure the column has a proper default value
        Schema::table('booking_users', function (Blueprint $table) {
            $table->boolean('group_changed')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: no specific action needed as this was a data cleanup
        // The column structure change could be reverted if needed
    }
};
