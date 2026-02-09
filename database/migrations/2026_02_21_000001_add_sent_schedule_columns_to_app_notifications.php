<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            return;
        }

        Schema::table('app_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('app_notifications', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('event_date');
                $table->index(['scheduled_at']);
            }

            if (!Schema::hasColumn('app_notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('scheduled_at');
                $table->index(['sent_at']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            return;
        }

        Schema::table('app_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('app_notifications', 'scheduled_at')) {
                $table->dropIndex(['scheduled_at']);
                $table->dropColumn('scheduled_at');
            }

            if (Schema::hasColumn('app_notifications', 'sent_at')) {
                $table->dropIndex(['sent_at']);
                $table->dropColumn('sent_at');
            }
        });
    }
};
