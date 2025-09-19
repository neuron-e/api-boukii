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
        if (!Schema::hasColumn('clients_schools', 'accepts_newsletter')) {
            Schema::table('clients_schools', function (Blueprint $table) {
                $table->boolean('accepts_newsletter')->default(false)->after('accepted_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clients_schools', 'accepts_newsletter')) {
            Schema::table('clients_schools', function (Blueprint $table) {
                $table->dropColumn('accepts_newsletter');
            });
        }
    }
};
