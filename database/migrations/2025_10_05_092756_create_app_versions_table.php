<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('android_version', 20);
            $table->integer('android_version_code');
            $table->string('ios_version', 20);
            $table->boolean('force_update')->default(true);
            $table->timestamps();
        });

        // Insert initial version (1.0.20 Android / 1.1.6 iOS)
        DB::table('app_versions')->insert([
            'android_version' => '1.0.20',
            'android_version_code' => 33,
            'ios_version' => '1.1.6',
            'force_update' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
