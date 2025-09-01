<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!env('V5_ADD_FKS', false)) {
            return; // Skip FK creation unless explicitly enabled
        }
        Schema::table('v5_renting_categories', function (Blueprint $table) {
            if (! app()->environment('testing')) {
                // Add FKs if not already present
                // Suppress errors if table not found in some environments
                try { DB::statement('ALTER TABLE v5_renting_categories ADD CONSTRAINT fk_rent_cat_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE'); } catch (\Throwable $e) {}
                try { DB::statement('ALTER TABLE v5_renting_categories ADD CONSTRAINT fk_rent_cat_parent FOREIGN KEY (parent_id) REFERENCES v5_renting_categories(id) ON DELETE CASCADE'); } catch (\Throwable $e) {}
            }
        });

        Schema::table('v5_renting_items', function (Blueprint $table) {
            if (! app()->environment('testing')) {
                try { DB::statement('ALTER TABLE v5_renting_items ADD CONSTRAINT fk_rent_item_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE'); } catch (\Throwable $e) {}
                try { DB::statement('ALTER TABLE v5_renting_items ADD CONSTRAINT fk_rent_item_category FOREIGN KEY (category_id) REFERENCES v5_renting_categories(id) ON DELETE CASCADE'); } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('v5_renting_items', function (Blueprint $table) {
            try { $table->dropForeign(['category_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['school_id']); } catch (\Throwable $e) {}
        });
        Schema::table('v5_renting_categories', function (Blueprint $table) {
            try { $table->dropForeign(['parent_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['school_id']); } catch (\Throwable $e) {}
        });
    }
};
