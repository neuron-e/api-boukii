<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('event_date');
            $table->timestamp('sent_at')->nullable()->after('scheduled_at');

            $table->index(['scheduled_at']);
            $table->index(['sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropIndex(['sent_at']);
            $table->dropColumn(['scheduled_at', 'sent_at']);
        });
    }
};
