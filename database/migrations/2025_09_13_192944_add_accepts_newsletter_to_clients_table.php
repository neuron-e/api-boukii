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
        if (!Schema::hasColumn('clients', 'accepts_newsletter')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->boolean('accepts_newsletter')->default(false)->after('image');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clients', 'accepts_newsletter')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('accepts_newsletter');
            });
        }
    }
};
