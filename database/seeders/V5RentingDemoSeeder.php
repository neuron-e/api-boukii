<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\School;
use App\V5\Modules\Renting\Models\RentingCategory;
use App\V5\Modules\Renting\Models\RentingItem;

class V5RentingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->where('active', 1)->first();
        if (! $school) {
            $this->command->warn('No active school found; skipping V5RentingDemoSeeder');
            return;
        }

        $schoolId = $school->id;

        $root = RentingCategory::firstOrCreate([
            'school_id' => $schoolId,
            'name' => 'Esquí',
        ], [
            'slug' => Str::slug('Esquí'),
            'position' => 1,
            'active' => true,
        ]);

        $sub1 = RentingCategory::firstOrCreate([
            'school_id' => $schoolId,
            'parent_id' => $root->id,
            'name' => 'Esquís',
        ], [
            'slug' => Str::slug('Esquís'),
            'position' => 1,
            'active' => true,
        ]);

        $sub2 = RentingCategory::firstOrCreate([
            'school_id' => $schoolId,
            'parent_id' => $root->id,
            'name' => 'Botas',
        ], [
            'slug' => Str::slug('Botas'),
            'position' => 2,
            'active' => true,
        ]);

        RentingItem::firstOrCreate([
            'school_id' => $schoolId,
            'category_id' => $sub1->id,
            'name' => 'Esquís Junior',
        ], [
            'sku' => 'SKI-JR-120',
            'base_daily_rate' => 20,
            'deposit' => 50,
            'currency' => 'EUR',
            'inventory_count' => 25,
            'active' => true,
        ]);

        RentingItem::firstOrCreate([
            'school_id' => $schoolId,
            'category_id' => $sub2->id,
            'name' => 'Botas Adulto',
        ], [
            'sku' => 'BOOT-AD-42',
            'base_daily_rate' => 10,
            'deposit' => 30,
            'currency' => 'EUR',
            'inventory_count' => 40,
            'active' => true,
        ]);

        $this->command->info('V5 Renting demo data seeded.');
    }
}

