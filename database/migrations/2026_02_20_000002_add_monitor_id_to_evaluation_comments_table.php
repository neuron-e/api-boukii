<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('monitor_id')->nullable()->after('user_id');
            $table->index(['monitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_comments', function (Blueprint $table) {
            $table->dropIndex(['monitor_id', 'created_at']);
            $table->dropColumn('monitor_id');
        });
    }
};
