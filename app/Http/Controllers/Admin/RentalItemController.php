<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalItemController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_items', [
            'category_id' => $request->input('category_id'),
        ]);
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_items', $id);
    }

    public function detail(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_items')) {
            return $this->tableMissingResponse('rental_items');
        }

        $schoolId = $this->getSchoolId($request);
        $selectedVariantId = (int) $request->input('variant_id', 0);

        $itemQuery = DB::table('rental_items as i')
            ->leftJoin('rental_categories as c', 'c.id', '=', 'i.category_id')
            ->select('i.*', 'c.name as category_name', 'c.icon as category_icon')
            ->where('i.id', $id);

        if ($schoolId && Schema::hasColumn('rental_items', 'school_id')) {
            $itemQuery->where('i.school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_items', 'deleted_at')) {
            $itemQuery->whereNull('i.deleted_at');
        }

        $item = $itemQuery->first();
        if (!$item) {
            return $this->sendError('Not found', [], 404);
        }

        $variants = collect();
        if (Schema::hasTable('rental_variants')) {
            $variants = DB::table('rental_variants as v')
                ->leftJoin('rental_subcategories as s', 's.id', '=', 'v.subcategory_id')
                ->select('v.*', 's.name as subcategory_name')
                ->where('v.item_id', $item->id)
                ->when($schoolId && Schema::hasColumn('rental_variants', 'school_id'), function ($q) use ($schoolId) {
                    $q->where('v.school_id', $schoolId);
                })
                ->when(Schema::hasColumn('rental_variants', 'deleted_at'), function ($q) {
                    $q->whereNull('v.deleted_at');
                })
                ->orderBy('v.id')
                ->get();
        }

        $variantIds = $variants->pluck('id')->all();
        $units = collect();
        $pricingRules = collect();
        $history = collect();
        $services = collect();

        if (!empty($variantIds) && Schema::hasTable('rental_units')) {
            $units = DB::table('rental_units')
                ->whereIn('variant_id', $variantIds)
                ->when($schoolId && Schema::hasColumn('rental_units', 'school_id'), function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->when(Schema::hasColumn('rental_units', 'deleted_at'), function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->get();
        }

        if (!empty($variantIds) && Schema::hasTable('rental_pricing_rules')) {
            $pricingRules = DB::table('rental_pricing_rules')
                ->whereIn('variant_id', $variantIds)
                ->when($schoolId && Schema::hasColumn('rental_pricing_rules', 'school_id'), function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->when(Schema::hasColumn('rental_pricing_rules', 'deleted_at'), function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->orderBy('id')
                ->get();
        }

        if (!empty($variantIds) && Schema::hasTable('rental_variant_services')) {
            $services = DB::table('rental_variant_services')
                ->whereIn('variant_id', $variantIds)
                ->when($schoolId && Schema::hasColumn('rental_variant_services', 'school_id'), function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->when(Schema::hasColumn('rental_variant_services', 'deleted_at'), function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if (!empty($variantIds) && Schema::hasTable('rental_reservation_lines') && Schema::hasTable('rental_reservations')) {
            $history = DB::table('rental_reservation_lines as line')
                ->leftJoin('rental_reservations as r', 'r.id', '=', 'line.rental_reservation_id')
                ->leftJoin('clients as cl', 'cl.id', '=', 'r.client_id')
                ->select(
                    'line.id',
                    'line.variant_id',
                    'line.quantity',
                    'line.line_total',
                    'r.id as reservation_id',
                    'r.reference',
                    'r.status',
                    'r.start_date',
                    'r.end_date',
                    'cl.first_name',
                    'cl.last_name'
                )
                ->whereIn('line.variant_id', $variantIds)
                ->when($schoolId && Schema::hasColumn('rental_reservation_lines', 'school_id'), function ($q) use ($schoolId) {
                    $q->where('line.school_id', $schoolId);
                })
                ->orderByDesc('line.id')
                ->limit(200)
                ->get();
        }

        $unitsByVariant = $units->groupBy('variant_id');
        $pricingByVariant = $pricingRules->groupBy('variant_id');
        $historyByVariant = $history->groupBy('variant_id');
        $servicesByVariant = $services->groupBy('variant_id');

        $variants = $variants->map(function ($row) use ($unitsByVariant, $pricingByVariant, $historyByVariant, $servicesByVariant) {
            $variantUnits = $unitsByVariant->get($row->id, collect());
            $variantPrices = $pricingByVariant->get($row->id, collect());
            $variantHistory = $historyByVariant->get($row->id, collect());

            $row->subcategory = $row->subcategory_name ? (object)['name' => $row->subcategory_name] : null;
            unset($row->subcategory_name);

            $row->inventory = [
                'total' => $variantUnits->count(),
                'available' => $variantUnits->where('status', 'available')->count(),
                'reserved' => $variantUnits->where('status', 'assigned')->count(),
                'maintenance' => $variantUnits->where('status', 'maintenance')->count(),
            ];
            $row->pricing_rules = $variantPrices->values();
            $row->history = $variantHistory->values();
            $row->services = $servicesByVariant->get($row->id, collect())->values();

            return $row;
        })->values();

        $selectedVariant = null;
        if ($selectedVariantId > 0) {
            $selectedVariant = $variants->first(function ($v) use ($selectedVariantId) {
                return (int) $v->id === $selectedVariantId;
            });
        }
        if (!$selectedVariant) {
            $selectedVariant = $variants->first();
        }

        $payload = [
            'item' => $item,
            'variants' => $variants,
            'selected_variant' => $selectedVariant,
            'units' => $units->values(),
            'pricing_rules' => $pricingRules->values(),
            'history' => $history->values(),
            'services' => $services->values(),
            'analytics' => [
                'total_units' => $units->count(),
                'available_units' => $units->where('status', 'available')->count(),
                'reserved_units' => $units->where('status', 'assigned')->count(),
                'maintenance_units' => $units->where('status', 'maintenance')->count(),
                'history_count' => $history->count(),
                'total_revenue' => (float) $history->sum('line_total'),
            ]
        ];

        return $this->sendResponse($payload, 'Data retrieved successfully');
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_items', [
            'school_id', 'category_id', 'name', 'brand', 'model', 'description', 'active',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_items', $id, [
            'category_id', 'name', 'brand', 'model', 'description', 'active',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_items', $id);
    }
}
