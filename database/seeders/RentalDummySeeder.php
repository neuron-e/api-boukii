<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RentalDummySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('rental_categories')) {
            $this->command?->warn('Rental tables do not exist. Run migrations first.');
            return;
        }

        $targetSchoolId = (int) env('RENTAL_DUMMY_SCHOOL_ID', 1);
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

        $userId = (int) (DB::table('users')->orderBy('id')->value('id') ?? 0);
        $bookingIdForLink = Schema::hasTable('bookings')
            ? (int) (DB::table('bookings')->where('school_id', $schoolId)->orderBy('id')->value('id') ?? 0)
            : 0;

        $this->truncateForSchool($schoolId);

        $warehousePayload = [
            'school_id' => $schoolId,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address' => 'Base station',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $warehouseId = DB::table('rental_warehouses')->insertGetId($warehousePayload);

        $pickupMainPayload = [
            'school_id' => $schoolId,
            'name' => 'Front Desk',
            'code' => 'FD',
            'address' => 'Main building',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('rental_pickup_points', 'warehouse_id')) {
            $pickupMainPayload['warehouse_id'] = $warehouseId;
        }
        if (Schema::hasColumn('rental_pickup_points', 'allow_pickup')) {
            $pickupMainPayload['allow_pickup'] = 1;
        }
        if (Schema::hasColumn('rental_pickup_points', 'allow_return')) {
            $pickupMainPayload['allow_return'] = 1;
        }
        $pickupMainId = DB::table('rental_pickup_points')->insertGetId($pickupMainPayload);

        $pickupSlopePayload = [
            'school_id' => $schoolId,
            'name' => 'Slope Pickup',
            'code' => 'SP',
            'address' => 'Lift entrance',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('rental_pickup_points', 'warehouse_id')) {
            $pickupSlopePayload['warehouse_id'] = $warehouseId;
        }
        if (Schema::hasColumn('rental_pickup_points', 'allow_pickup')) {
            $pickupSlopePayload['allow_pickup'] = 1;
        }
        if (Schema::hasColumn('rental_pickup_points', 'allow_return')) {
            $pickupSlopePayload['allow_return'] = 1;
        }
        $pickupSlopeId = DB::table('rental_pickup_points')->insertGetId($pickupSlopePayload);

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
        $brandIds = [];
        $modelIds = [];
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
                $brandKey = mb_strtolower(trim((string) $itemData['brand']));
                if (!isset($brandIds[$brandKey]) && Schema::hasTable('rental_brands')) {
                    $brandIds[$brandKey] = DB::table('rental_brands')->insertGetId([
                        'school_id' => $schoolId,
                        'name' => $itemData['brand'],
                        'slug' => Str::slug($itemData['brand']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $modelKey = $brandKey . '|' . mb_strtolower(trim((string) $itemData['model']));
                if (!isset($modelIds[$modelKey]) && Schema::hasTable('rental_models')) {
                    $modelPayload = [
                        'school_id' => $schoolId,
                        'name' => $itemData['model'],
                        'slug' => Str::slug($itemData['model']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('rental_models', 'brand_id') && !empty($brandIds[$brandKey])) {
                        $modelPayload['brand_id'] = $brandIds[$brandKey];
                    }
                    $modelIds[$modelKey] = DB::table('rental_models')->insertGetId($modelPayload);
                }

                $itemPayload = [
                    'school_id' => $schoolId,
                    'category_id' => $categoryId,
                    'name' => $itemData['name'],
                    'brand' => $itemData['brand'],
                    'model' => $itemData['model'],
                    'description' => $itemData['name'] . ' ' . $itemData['model'],
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('rental_items', 'brand_id')) {
                    $itemPayload['brand_id'] = $brandIds[$brandKey] ?? null;
                }
                if (Schema::hasColumn('rental_items', 'model_id')) {
                    $itemPayload['model_id'] = $modelIds[$modelKey] ?? null;
                }
                $itemId = DB::table('rental_items')->insertGetId($itemPayload);

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
            'enabled' => 1,
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

        $reservationBlueprints = [
            [
                'label' => 'pending_pickup',
                'reference' => 'RR-QA-PENDING',
                'status' => 'pending',
                'start' => Carbon::now()->addDay()->toDateString(),
                'end' => Carbon::now()->addDays(3)->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'deposit_amount' => 120,
                'deposit_status' => 'none',
                'line_count' => 2,
                'assign_mode' => 'none',
                'payment' => null,
                'deposit_payment' => null,
                'notes' => 'Pending pickup scenario for QA',
            ],
            [
                'label' => 'assigned_ready',
                'reference' => 'RR-QA-ASSIGNED',
                'status' => 'assigned',
                'start' => Carbon::now()->toDateString(),
                'end' => Carbon::now()->addDays(2)->toDateString(),
                'start_time' => '08:30:00',
                'end_time' => '17:00:00',
                'deposit_amount' => 150,
                'deposit_status' => 'held',
                'line_count' => 2,
                'assign_mode' => 'assigned',
                'payment' => ['amount_ratio' => 0.45, 'method' => 'card'],
                'deposit_payment' => ['amount' => 150, 'status' => 'paid', 'method' => 'cash'],
                'notes' => 'Assigned and ready for handover',
            ],
            [
                'label' => 'checked_out_active',
                'reference' => 'RR-QA-CHECKEDOUT',
                'status' => 'checked_out',
                'start' => Carbon::now()->subDay()->toDateString(),
                'end' => Carbon::now()->addDay()->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '16:00:00',
                'deposit_amount' => 200,
                'deposit_status' => 'held',
                'line_count' => 2,
                'assign_mode' => 'checked_out',
                'payment' => ['amount_ratio' => 0.75, 'method' => 'cash'],
                'deposit_payment' => ['amount' => 200, 'status' => 'paid', 'method' => 'cash'],
                'notes' => 'Checked out reservation for return QA',
            ],
            [
                'label' => 'partial_return',
                'reference' => 'RR-QA-PARTIAL',
                'status' => 'partial_return',
                'start' => Carbon::now()->subDays(2)->toDateString(),
                'end' => Carbon::now()->addDay()->toDateString(),
                'start_time' => '09:30:00',
                'end_time' => '17:30:00',
                'deposit_amount' => 180,
                'deposit_status' => 'held',
                'damage_total' => 35,
                'line_count' => 2,
                'assign_mode' => 'partial_return',
                'payment' => ['amount_ratio' => 1, 'method' => 'card'],
                'deposit_payment' => ['amount' => 180, 'status' => 'paid', 'method' => 'card'],
                'notes' => 'Partial return with one unit already back',
            ],
            [
                'label' => 'overdue',
                'reference' => 'RR-QA-OVERDUE',
                'status' => 'overdue',
                'start' => Carbon::now()->subDays(4)->toDateString(),
                'end' => Carbon::now()->subDay()->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '15:00:00',
                'deposit_amount' => 90,
                'deposit_status' => 'held',
                'line_count' => 1,
                'assign_mode' => 'checked_out',
                'payment' => ['amount_ratio' => 0.30, 'method' => 'invoice'],
                'deposit_payment' => ['amount' => 90, 'status' => 'paid', 'method' => 'cash'],
                'notes' => 'Overdue reservation for contact/damage flow',
            ],
            [
                'label' => 'completed_linked_booking',
                'reference' => 'RR-QA-COMPLETED',
                'status' => 'completed',
                'start' => Carbon::now()->subDays(6)->toDateString(),
                'end' => Carbon::now()->subDays(3)->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '16:00:00',
                'deposit_amount' => 140,
                'deposit_status' => 'released',
                'line_count' => 2,
                'assign_mode' => 'completed',
                'payment' => ['amount_ratio' => 1, 'method' => 'card'],
                'deposit_payment' => ['amount' => 140, 'status' => 'paid', 'method' => 'card'],
                'link_booking' => $bookingIdForLink > 0,
                'notes' => 'Completed reservation linked to a booking when available',
            ],
        ];

        $createdReservations = [];
        foreach ($reservationBlueprints as $index => $blueprint) {
            $clientId = $clientIds[$index % max(1, count($clientIds))] ?? null;
            $reservationId = DB::table('rental_reservations')->insertGetId(array_filter([
                'school_id' => $schoolId,
                'booking_id' => !empty($blueprint['link_booking']) ? $bookingIdForLink : null,
                'client_id' => $clientId,
                'pickup_point_id' => $pickupMainId,
                'return_point_id' => $pickupSlopeId,
                'warehouse_id' => $warehouseId,
                'reference' => $blueprint['reference'],
                'status' => $blueprint['status'],
                'currency' => 'CHF',
                'start_date' => $blueprint['start'],
                'end_date' => $blueprint['end'],
                'start_time' => $blueprint['start_time'],
                'end_time' => $blueprint['end_time'],
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 0,
                'notes' => $blueprint['notes'] ?? null,
                'meta' => json_encode(['source' => 'dummy-seeder', 'scenario' => $blueprint['label']]),
                'deposit_amount' => $blueprint['deposit_amount'] ?? 0,
                'deposit_status' => $blueprint['deposit_status'] ?? 'none',
                'damage_total' => $blueprint['damage_total'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($value) => $value !== null));

            $selectedVariantIds = collect($allVariantIds)->slice($index * 2, (int) ($blueprint['line_count'] ?? 2))->values()->all();
            if (count($selectedVariantIds) < (int) ($blueprint['line_count'] ?? 2)) {
                $selectedVariantIds = collect($allVariantIds)->shuffle()->take((int) ($blueprint['line_count'] ?? 2))->values()->all();
            }

            $subtotal = 0.0;
            $lineIds = [];
            foreach ($selectedVariantIds as $lineIndex => $variantId) {
                $qty = 1;
                if (($blueprint['label'] ?? '') === 'partial_return' && $lineIndex === 0) {
                    $qty = 2;
                }
                $price = (float) ($variantMap[$variantId]['price_day'] ?? 0);
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                $linePayload = [
                    'school_id' => $schoolId,
                    'rental_reservation_id' => $reservationId,
                    'item_id' => $variantMap[$variantId]['item_id'],
                    'variant_id' => $variantId,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                    'meta' => json_encode(['scenario' => $blueprint['label']]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('rental_reservation_lines', 'returned_quantity')) {
                    $linePayload['returned_quantity'] = (($blueprint['label'] ?? '') === 'partial_return' && $lineIndex === 0) ? 1 : 0;
                }
                if (Schema::hasColumn('rental_reservation_lines', 'damage_notes')) {
                    $linePayload['damage_notes'] = (($blueprint['label'] ?? '') === 'partial_return' && $lineIndex === 0)
                        ? 'Edge scratch registered during return QA'
                        : null;
                }

                $lineId = DB::table('rental_reservation_lines')->insertGetId($linePayload);
                $lineIds[] = $lineId;

                $assignMode = $blueprint['assign_mode'] ?? 'none';
                if ($assignMode !== 'none') {
                    $assignedUnits = array_splice($unitIdsByVariant[$variantId], 0, $qty);
                    foreach ($assignedUnits as $unitOffset => $assignedUnitId) {
                        $returnedAt = null;
                        $assignmentType = 'assigned';
                        $unitStatus = 'assigned';
                        $conditionOut = $assignMode === 'overdue' ? 'good' : 'excellent';
                        $notes = null;

                        if ($assignMode === 'checked_out') {
                            $assignmentType = 'checked_out';
                            $unitStatus = 'assigned';
                            $notes = 'Delivered to customer';
                        } elseif ($assignMode === 'partial_return') {
                            $assignmentType = 'checked_out';
                            $unitStatus = 'assigned';
                            if ($lineIndex === 0 && $unitOffset === 0) {
                                $returnedAt = now()->subHours(3);
                                $assignmentType = 'returned';
                                $unitStatus = 'available';
                                $notes = 'Partially returned';
                            }
                        } elseif ($assignMode === 'completed') {
                            $returnedAt = now()->subDays(2);
                            $assignmentType = 'returned';
                            $unitStatus = 'available';
                            $notes = 'Returned and completed';
                        }

                        DB::table('rental_reservation_unit_assignments')->insert([
                            'school_id' => $schoolId,
                            'rental_reservation_id' => $reservationId,
                            'rental_reservation_line_id' => $lineId,
                            'rental_unit_id' => $assignedUnitId,
                            'assignment_type' => $assignmentType,
                            'assigned_at' => now()->subDays(1),
                            'returned_at' => $returnedAt,
                            'condition_out' => $conditionOut,
                            'notes' => $notes,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $unitUpdate = [
                            'status' => $unitStatus,
                            'updated_at' => now(),
                        ];
                        if ($blueprint['status'] === 'overdue' && Schema::hasColumn('rental_units', 'blocked_until')) {
                            $unitUpdate['blocked_until'] = Carbon::parse($blueprint['end'])->endOfDay();
                        }
                        DB::table('rental_units')->where('id', $assignedUnitId)->update($unitUpdate);

                        if (Schema::hasTable('rental_stock_movements')) {
                            DB::table('rental_stock_movements')->insert([
                                'school_id' => $schoolId,
                                'rental_reservation_id' => $reservationId,
                                'rental_reservation_line_id' => $lineId,
                                'rental_unit_id' => $assignedUnitId,
                                'variant_id' => $variantId,
                                'item_id' => $variantMap[$variantId]['item_id'],
                                'warehouse_id_from' => $warehouseId,
                                'warehouse_id_to' => null,
                                'movement_type' => in_array($assignmentType, ['checked_out', 'assigned'], true) ? 'checkout' : 'return',
                                'quantity' => 1,
                                'reason' => ucfirst(str_replace('_', ' ', (string) $blueprint['label'])),
                                'payload' => json_encode(['scenario' => $blueprint['label'], 'assignment_type' => $assignmentType]),
                                'user_id' => $userId > 0 ? $userId : null,
                                'occurred_at' => now()->subHours(2),
                                'created_at' => now()->subHours(2),
                            ]);
                        }
                    }
                }
            }

            $mainPaymentId = null;
            if (!empty($blueprint['payment']) && Schema::hasTable('payments')) {
                $mainPaymentAmount = round($subtotal * (float) ($blueprint['payment']['amount_ratio'] ?? 1), 2);
                $mainPaymentId = DB::table('payments')->insertGetId([
                    'booking_id' => !empty($blueprint['link_booking']) ? $bookingIdForLink : null,
                    'rental_reservation_id' => $reservationId,
                    'school_id' => $schoolId,
                    'amount' => $mainPaymentAmount,
                    'status' => 'paid',
                    'payment_method' => $blueprint['payment']['method'] ?? 'cash',
                    'payment_type' => 'rental',
                    'notes' => 'QA seeded rental payment',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $depositPaymentId = null;
            if (!empty($blueprint['deposit_payment']) && Schema::hasTable('payments')) {
                $depositPaymentId = DB::table('payments')->insertGetId([
                    'booking_id' => !empty($blueprint['link_booking']) ? $bookingIdForLink : null,
                    'rental_reservation_id' => $reservationId,
                    'school_id' => $schoolId,
                    'amount' => (float) ($blueprint['deposit_payment']['amount'] ?? 0),
                    'status' => $blueprint['deposit_payment']['status'] ?? 'paid',
                    'payment_method' => $blueprint['deposit_payment']['method'] ?? 'cash',
                    'payment_type' => 'deposit',
                    'notes' => 'QA seeded deposit payment',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('rental_reservations')->where('id', $reservationId)->update(array_filter([
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'payment_id' => $mainPaymentId,
                'deposit_payment_id' => $depositPaymentId,
                'updated_at' => now(),
            ], fn ($value) => $value !== null));

            if (Schema::hasTable('rental_events')) {
                $events = [
                    ['reservation_created', now()->subDay(), ['scenario' => $blueprint['label']]],
                ];
                if (in_array($blueprint['assign_mode'], ['assigned', 'checked_out', 'partial_return', 'completed'], true)) {
                    $events[] = ['units_assigned', now()->subHours(12), ['line_ids' => $lineIds]];
                }
                if ($blueprint['status'] === 'checked_out') {
                    $events[] = ['handover_confirmed', now()->subHours(8), ['pickup_point_id' => $pickupMainId]];
                }
                if ($blueprint['status'] === 'partial_return') {
                    $events[] = ['partial_return_registered', now()->subHours(3), ['returned_lines' => [$lineIds[0] ?? null]]];
                    $events[] = ['damage_reported', now()->subHours(2), ['damage_total' => $blueprint['damage_total'] ?? 0]];
                }
                if ($blueprint['status'] === 'completed') {
                    $events[] = ['reservation_completed', now()->subDays(2), ['booking_id' => !empty($blueprint['link_booking']) ? $bookingIdForLink : null]];
                }
                if ($blueprint['status'] === 'overdue') {
                    $events[] = ['marked_overdue', now()->subHours(1), ['expected_return' => $blueprint['end']]];
                }
                foreach ($events as [$type, $createdAt, $payload]) {
                    DB::table('rental_events')->insert([
                        'school_id' => $schoolId,
                        'rental_reservation_id' => $reservationId,
                        'event_type' => $type,
                        'payload' => json_encode($payload),
                        'user_id' => $userId > 0 ? $userId : null,
                        'created_at' => $createdAt,
                    ]);
                }
            }

            $createdReservations[] = [
                'id' => $reservationId,
                'reference' => $blueprint['reference'],
                'status' => $blueprint['status'],
                'booking_id' => !empty($blueprint['link_booking']) ? $bookingIdForLink : null,
            ];
        }

        $this->command?->info('Rental QA dummy data created for school_id=' . $schoolId);
        foreach ($createdReservations as $createdReservation) {
            $this->command?->line(sprintf(
                ' - %s | id=%d | status=%s%s',
                $createdReservation['reference'],
                $createdReservation['id'],
                $createdReservation['status'],
                !empty($createdReservation['booking_id']) ? ' | booking_id=' . $createdReservation['booking_id'] : ''
            ));
        }
    }

    private function truncateForSchool(int $schoolId): void
    {
        if (Schema::hasTable('rental_events')) {
            DB::table('rental_events')->where('school_id', $schoolId)->delete();
        }
        if (Schema::hasTable('rental_stock_movements')) {
            DB::table('rental_stock_movements')->where('school_id', $schoolId)->delete();
        }
        DB::table('rental_reservation_unit_assignments')->where('school_id', $schoolId)->delete();
        DB::table('rental_reservation_lines')->where('school_id', $schoolId)->delete();
        DB::table('rental_reservations')->where('school_id', $schoolId)->delete();
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'rental_reservation_id')) {
            DB::table('payments')->where('rental_reservation_id', '>', 0)->where('school_id', $schoolId)->delete();
        }
        DB::table('rental_policies')->where('school_id', $schoolId)->delete();
        DB::table('rental_pricing_rules')->where('school_id', $schoolId)->delete();
        DB::table('rental_units')->where('school_id', $schoolId)->delete();
        DB::table('rental_variants')->where('school_id', $schoolId)->delete();
        DB::table('rental_items')->where('school_id', $schoolId)->delete();
        if (Schema::hasTable('rental_models') && Schema::hasColumn('rental_models', 'school_id')) {
            DB::table('rental_models')->where('school_id', $schoolId)->delete();
        }
        if (Schema::hasTable('rental_brands') && Schema::hasColumn('rental_brands', 'school_id')) {
            DB::table('rental_brands')->where('school_id', $schoolId)->delete();
        }
        DB::table('rental_subcategories')->where('school_id', $schoolId)->delete();
        DB::table('rental_categories')->where('school_id', $schoolId)->delete();
        DB::table('rental_pickup_points')->where('school_id', $schoolId)->delete();
        DB::table('rental_warehouses')->where('school_id', $schoolId)->delete();
    }
}
