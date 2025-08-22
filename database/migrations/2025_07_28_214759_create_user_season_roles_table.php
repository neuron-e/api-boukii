<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_season_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('season_id');
            $table->string('role');
            $table->timestamps();
            $table->softDeletes();

            $table->primary(['user_id', 'season_id', 'role']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');

            $table->index('user_id');
            $table->index('season_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_season_roles');
    }
};
