<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('monitor_id');
            // Token can be large (Firebase/APNs tokens can be up to 2048 chars)
            // Using text instead of string to avoid index length issues
            $table->text('token');
            $table->string('platform', 32)->nullable();
            $table->string('device_id', 128)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('app', 32)->default('teach');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('monitor_id')->references('id')->on('monitors')->onDelete('cascade');
            $table->index(['monitor_id', 'app']);
        });

        // Create unique index on token using first 191 characters
        // This is sufficient for uniqueness while staying under MySQL index limits (3072 bytes)
        \DB::statement('CREATE UNIQUE INDEX monitor_push_tokens_token_unique ON monitor_push_tokens (token(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_push_tokens');
    }
};
