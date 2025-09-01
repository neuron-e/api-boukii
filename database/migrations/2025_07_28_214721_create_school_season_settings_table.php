<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_season_settings')) {
            return;
        }
        Schema::create('school_season_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('season_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'season_id', 'key'], 'uniq_school_season_key');
            $table->index('school_id');
            $table->index('season_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_season_settings');
    }
};
