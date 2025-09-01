<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                // Context data used by V5 auth flows
                $table->json('context_data')->nullable();
                $table->timestamps();
            });
        } else {
            // Ensure required columns exist when table is present but incomplete
            if (! Schema::hasColumn('personal_access_tokens', 'context_data')) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->json('context_data')->nullable()->after('expires_at');
                });
            }
            if (! Schema::hasColumn('personal_access_tokens', 'expires_at')) {
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->timestamp('expires_at')->nullable()->after('last_used_at');
                });
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: do not drop tokens table automatically
        // (keep tokens in lower environments). If needed, manage manually.
    }
};

