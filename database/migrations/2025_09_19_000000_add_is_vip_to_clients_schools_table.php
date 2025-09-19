<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients_schools', 'is_vip')) {
            Schema::table('clients_schools', function (Blueprint $table) {
                $table->boolean('is_vip')->default(false)->after('accepts_newsletter');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients_schools', 'is_vip')) {
            Schema::table('clients_schools', function (Blueprint $table) {
                $table->dropColumn('is_vip');
            });
        }
    }
};

