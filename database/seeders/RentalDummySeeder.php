<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RentalDummySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('rental_categories')) {
            $this->command?->warn('Rental tables do not exist. Run migrations first.');
            return;
        }

        $targetSchoolId = (int) env('RENTAL_DUMMY_SCHOOL_ID', 15);
        if ($targetSchoolId > 0 && Schema::hasTable('schools')) {
            $schoolId = (int) (DB::table('schools')->where('id', $targetSchoolId)->value('id') ?? 0);
        } else {
            $schoolId = 0;
        }

        if ($schoolId <= 0 && Schema::hasTable('schools')) {
            $schoolId = (int) (DB::table('schools')
                ->where(function ($q) {
                    $q->whereRaw('LOWER(name) like ?', ['%testing%'])
                        ->orWhereRaw('LOWER(slug) like ?', ['%testing%']);
                })
                ->value('id') ?? 0);
        }
        if ($schoolId <= 0) {
            $this->command?->warn('No testing school found. Set RENTAL_DUMMY_SCHOOL_ID to the testing school id.');
            return;
        }

        $clientIds = [];
        if (Schema::hasTable('clients_schools')) {
            $clientIds = DB::table('clients_schools')
                ->where('school_id', $schoolId)
                ->whereNull('deleted_at')
                ->orderBy('client_id')
                ->limit(20)
                ->pluck('client_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } elseif (Schema::hasTable('clients')) {
            $clientIds = DB::table('clients')
                ->orderBy('id')
                ->limit(20)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $this->truncateForSchool($schoolId);

        $warehouseId = DB::table('rental_warehouses')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address' => 'Base station',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pickupMainId = DB::table('rental_pickup_points')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Front Desk',
            'code' => 'FD',
            'address' => 'Main building',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pickupSlopeId = DB::table('rental_pickup_points')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Slope Pickup',
            'code' => 'SP',
            'address' => 'Lift entrance',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalog = [
            [
                'name' => 'Skis',
                'icon' => 'downhill_skiing',
                'subcategories' => ['All Mountain', 'Beginner', 'Race/Carving'],
                'items' => [
                    ['name' => 'All Mountain Ski', 'brand' => 'Rossignol', 'model' => 'Experience 80', 'sizes' => ['156cm', '165cm', '172cm', '180cm'], 'stock' => 25, 'price_day' => 25, 'price_week' => 120],
                    ['name' => 'Beginner Ski', 'brand' => 'Salomon', 'model' => 'QST Access', 'sizes' => ['145cm', '156cm', '165cm'], 'stock' => 20, 'price_day' => 20, 'price_week' => 95],
                    ['name' => 'Race Carving Ski', 'brand' => 'Atomic', 'model' => 'Redster S9', 'sizes' => ['158cm', '165cm', '172cm'], 'stock' => 10, 'price_day' => 40, 'price_week' => 220],
                    ['name' => 'Ski Boot', 'brand' => 'Nordica', 'model' => 'Sportmachine 80', 'sizes' => ['25.5', '26.5', '27.5', '28.5'], 'stock' => 12, 'price_day' => 8, 'price_week' => 50],
                ],
            ],
            [
                'name' => 'Snowboards',
                'icon' => 'snowboarding',
                'subcategories' => ['All Mountain', 'Freestyle', 'Kids'],
                'items' => [
                    ['name' => 'All Mountain Board', 'brand' => 'Burton', 'model' => 'Custom', 'sizes' => ['146cm', '152cm', '158cm'], 'stock' => 15, 'price_day' => 30, 'price_week' => 140],
                    ['name' => 'Freestyle Board', 'brand' => 'K2', 'model' => 'Raygun', 'sizes' => ['146cm', '152cm', '156cm'], 'stock' => 8, 'price_day' => 32, 'price_week' => 150],
                    ['name' => 'Snowboard Boots', 'brand' => 'ThirtyTwo', 'model' => 'TM-2', 'sizes' => ['39', '41', '43', '45'], 'stock' => 10, 'price_day' => 10, 'price_week' => 60],
                ],
            ],
            [
                'name' => 'Accessories',
                'icon' => 'hiking',
                'subcategories' => ['Helmets', 'Poles'],
                'items' => [
                    ['name' => 'Ski Helmet', 'brand' => 'Smith', 'model' => 'Vantage', 'sizes' => ['S', 'M', 'L'], 'stock' => 30, 'price_day' => 8, 'price_week' => 35],
                    ['name' => 'Ski Poles', 'brand' => 'Leki', 'model' => 'Airfoil', 'sizes' => ['110', '115', '120', '125'], 'stock' => 18, 'price_day' => 5, 'price_week' => 25],
                ],
            ],
            [
                'name' => 'Clothing',
                'icon' => 'checkroom',
                'subcategories' => ['Jackets', 'Pants'],
                'items' => [
                    ['name' => 'Ski Jacket', 'brand' => 'The North Face', 'model' => 'Apex Flex', 'sizes' => ['S', 'M', 'L', 'XL'], 'stock' => 15, 'price_day' => 10, 'price_week' => 70],
                ],
            ],
        ];

        $allVariantIds = [];
        $variantMap = [];
        foreach ($catalog as $categoryIndex => $categoryData) {
            $categoryId = DB::table('rental_categories')->insertGetId([
                'school_id' => $schoolId,
                'name' => $categoryData['name'],
                'slug' => Str::slug($categoryData['name']),
                'icon' => $categoryData['icon'],
                'active' => 1,
                'sort_order' => $categoryIndex + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $subCategoryIds = [];
            foreach ($categoryData['subcategories'] as $subIndex => $subName) {
                $subCategoryIds[$subName] = DB::table('rental_subcategories')->insertGetId([
                    'school_id' => $schoolId,
                    'category_id' => $categoryId,
                    'name' => $subName,
                    'slug' => Str::slug($subName),
                    'active' => 1,
                    'sort_order' => $subIndex + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($categoryData['items'] as $itemIndex => $itemData) {
                $itemId = DB::table('rental_items')->insertGetId([
                    'school_id' => $schoolId,
                    'category_id' => $categoryId,
                    'name' => $itemData['name'],
                    'brand' => $itemData['brand'],
                    'model' => $itemData['model'],
                    'description' => $itemData['name'] . ' ' . $itemData['model'],
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $subCategoryId = array_values($subCategoryIds)[$itemIndex % max(1, count($subCategoryIds))];

                foreach ($itemData['sizes'] as $sizeIndex => $size) {
                    $variantName = $itemData['name'] . ' ' . $size;
                    $variantId = DB::table('rental_variants')->insertGetId([
                        'school_id' => $schoolId,
                        'item_id' => $itemId,
                        'subcategory_id' => $subCategoryId,
                        'name' => $variantName,
                        'size_group' => DB::table('rental_subcategories')->where('id', $subCategoryId)->value('name'),
                        'size_label' => $size,
                        'sku' => strtoupper(Str::slug($itemData['brand'] . '-' . $itemData['model'] . '-' . $size, '')),
                        'barcode' => 'BC' . str_pad((string) mt_rand(1000000, 9999999), 8, '0', STR_PAD_LEFT),
                        'active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $allVariantIds[] = $variantId;
                    $variantMap[$variantId] = [
                        'item_id' => $itemId,
                        'stock' => max(1, (int) floor($itemData['stock'] / max(1, count($itemData['sizes'])))),
                        'price_day' => $itemData['price_day'],
                        'price_week' => $itemData['price_week'],
                    ];

                    DB::table('rental_pricing_rules')->insert([
                        [
                            'school_id' => $schoolId,
                            'item_id' => $itemId,
                            'variant_id' => $variantId,
                            'period_type' => 'full_day',
                            'price' => $itemData['price_day'],
                            'currency' => 'CHF',
                            'active' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'school_id' => $schoolId,
                            'item_id' => $itemId,
                            'variant_id' => $variantId,
                            'period_type' => 'week',
                            'price' => $itemData['price_week'],
                            'currency' => 'CHF',
                            'active' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ]);
                }
            }
        }

        $unitIdsByVariant = [];
        foreach ($variantMap as $variantId => $variantData) {
            $unitIdsByVariant[$variantId] = [];
            for ($i = 1; $i <= $variantData['stock']; $i++) {
                $unitId = DB::table('rental_units')->insertGetId([
                    'school_id' => $schoolId,
                    'variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'serial' => sprintf('U-%d-%03d', $variantId, $i),
                    'status' => 'available',
                    'condition' => $i % 7 === 0 ? 'good' : 'excellent',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $unitIdsByVariant[$variantId][] = $unitId;
            }
        }

        DB::table('rental_policies')->insert([
            'school_id' => $schoolId,
            'default_deposit_mode' => 'percentage',
            'default_deposit_value' => 20,
            'auto_assign_on_create' => 1,
            'allow_overbooking' => 0,
            'grace_minutes' => 30,
            'terms' => 'Equipment must be returned clean and before the agreed return time.',
            'settings' => json_encode([
                'late_fee_per_hour' => 10,
                'damage_fee_mode' => 'manual',
                'barcode_required' => false,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservationStatuses = ['pending', 'active', 'active', 'active', 'completed', 'completed', 'overdue', 'pending'];
        $reservationCount = 0;
        foreach ($reservationStatuses as $index => $status) {
            $reservationCount++;
            $startDate = now()->addDays($index - 3)->toDateString();
            $endDate = now()->addDays($index - 2)->toDateString();
            $clientId = $clientIds[$index % max(1, count($clientIds))] ?? null;

            $reservationId = DB::table('rental_reservations')->insertGetId([
                'school_id' => $schoolId,
                'booking_id' => null,
                'client_id' => $clientId,
                'pickup_point_id' => $pickupMainId,
                'return_point_id' => $pickupSlopeId,
                'warehouse_id' => $warehouseId,
                'reference' => sprintf('RR-%04d', $reservationCount),
                'status' => $status,
                'currency' => 'CHF',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 0,
                'notes' => 'Dummy rental reservation',
                'meta' => json_encode(['source' => 'dummy-seeder']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lineCount = 2;
            $selectedVariantIds = collect($allVariantIds)->shuffle()->take($lineCount)->values()->all();
            $subtotal = 0;
            foreach ($selectedVariantIds as $lineIndex => $variantId) {
                $qty = $lineIndex + 1;
                $price = (float) ($variantMap[$variantId]['price_day'] ?? 0);
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                $lineId = DB::table('rental_reservation_lines')->insertGetId([
                    'school_id' => $schoolId,
                    'rental_reservation_id' => $reservationId,
                    'item_id' => $variantMap[$variantId]['item_id'],
                    'variant_id' => $variantId,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                    'meta' => json_encode(['size' => $variantId]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (in_array($status, ['active', 'completed', 'overdue'], true)) {
                    $assignedUnits = array_splice($unitIdsByVariant[$variantId], 0, $qty);
                    foreach ($assignedUnits as $assignedUnitId) {
                        DB::table('rental_reservation_unit_assignments')->insert([
                            'school_id' => $schoolId,
                            'rental_reservation_id' => $reservationId,
                            'rental_reservation_line_id' => $lineId,
                            'rental_unit_id' => $assignedUnitId,
                            'assignment_type' => 'assigned',
                            'assigned_at' => now(),
                            'returned_at' => in_array($status, ['completed'], true) ? now() : null,
                            'condition_out' => $status === 'overdue' ? 'good' : 'excellent',
                            'notes' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('rental_units')
                            ->where('id', $assignedUnitId)
                            ->update([
                                'status' => in_array($status, ['completed'], true) ? 'available' : 'assigned',
                                'updated_at' => now(),
                            ]);
                    }
                }
            }

            DB::table('rental_reservations')
                ->where('id', $reservationId)
                ->update([
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                ]);
        }

        $this->command?->info('Rental dummy data created for school_id=' . $schoolId);
    }

    private function truncateForSchool(int $schoolId): void
    {
        DB::table('rental_reservation_unit_assignments')->where('school_id', $schoolId)->delete();
        DB::table('rental_reservation_lines')->where('school_id', $schoolId)->delete();
        DB::table('rental_reservations')->where('school_id', $schoolId)->delete();
        DB::table('rental_policies')->where('school_id', $schoolId)->delete();
        DB::table('rental_pricing_rules')->where('school_id', $schoolId)->delete();
        DB::table('rental_units')->where('school_id', $schoolId)->delete();
        DB::table('rental_variants')->where('school_id', $schoolId)->delete();
        DB::table('rental_items')->where('school_id', $schoolId)->delete();
        DB::table('rental_subcategories')->where('school_id', $schoolId)->delete();
        DB::table('rental_categories')->where('school_id', $schoolId)->delete();
        DB::table('rental_pickup_points')->where('school_id', $schoolId)->delete();
        DB::table('rental_warehouses')->where('school_id', $schoolId)->delete();
    }
}
