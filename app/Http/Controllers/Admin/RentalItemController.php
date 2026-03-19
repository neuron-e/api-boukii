<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RentalItemController extends RentalBaseController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_items')) {
            return $this->tableMissingResponse('rental_items');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_items as i');
        $hasNormalizedBrandModel = Schema::hasTable('rental_brands')
            && Schema::hasTable('rental_models')
            && Schema::hasColumn('rental_items', 'brand_id')
            && Schema::hasColumn('rental_items', 'model_id');

        if ($hasNormalizedBrandModel) {
            $query
                ->leftJoin('rental_brands as rb', 'rb.id', '=', 'i.brand_id')
                ->leftJoin('rental_models as rm', 'rm.id', '=', 'i.model_id');
        }

        if ($schoolId && Schema::hasColumn('rental_items', 'school_id')) {
            $query->where('i.school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_items', 'deleted_at')) {
            $query->whereNull('i.deleted_at');
        }

        if ($request->filled('category_id') && Schema::hasColumn('rental_items', 'category_id')) {
            $query->where('i.category_id', (int) $request->input('category_id'));
        }

        if ((Schema::hasTable('rental_item_tags') && Schema::hasTable('rental_tags')) && ($request->filled('tag_id') || $request->filled('tag'))) {
            $tagId = (int) $request->input('tag_id', 0);
            $tagName = trim((string) $request->input('tag', ''));

            $query->join('rental_item_tags as rit', 'rit.item_id', '=', 'i.id')
                ->join('rental_tags as tag', 'tag.id', '=', 'rit.tag_id');

            if ($tagId > 0) {
                $query->where('tag.id', $tagId);
            } elseif ($tagName !== '') {
                $query->whereRaw('LOWER(tag.name) = ?', [mb_strtolower($tagName)]);
            }

            if (Schema::hasColumn('rental_item_tags', 'deleted_at')) {
                $query->whereNull('rit.deleted_at');
            }
            if (Schema::hasColumn('rental_tags', 'deleted_at')) {
                $query->whereNull('tag.deleted_at');
            }
            $query->select('i.*')->distinct();
        } else {
            $query->select('i.*');
        }

        if ($hasNormalizedBrandModel) {
            $query->addSelect([
                DB::raw("COALESCE(NULLIF(rb.name, ''), NULLIF(i.brand, '')) as normalized_brand"),
                DB::raw("COALESCE(NULLIF(rm.name, ''), NULLIF(i.model, '')) as normalized_model"),
            ]);
        }

        $query->orderByDesc('i.id');
        $perPage = (int) $request->input('per_page', 100);
        $rows = $query->paginate(max(1, min(1000, $perPage)));

        $rows->setCollection($this->decorateBrandModelFields($this->attachTagsToCollection($rows->getCollection(), $schoolId)));
        return $this->sendResponse($rows, 'Data retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_items')) {
            return $this->tableMissingResponse('rental_items');
        }

        $schoolId = $this->getSchoolId($request);
        $query = DB::table('rental_items as i')->where('i.id', $id);
        $hasNormalizedBrandModel = Schema::hasTable('rental_brands')
            && Schema::hasTable('rental_models')
            && Schema::hasColumn('rental_items', 'brand_id')
            && Schema::hasColumn('rental_items', 'model_id');
        if ($hasNormalizedBrandModel) {
            $query
                ->leftJoin('rental_brands as rb', 'rb.id', '=', 'i.brand_id')
                ->leftJoin('rental_models as rm', 'rm.id', '=', 'i.model_id');
        }
        $query->select('i.*');
        if ($hasNormalizedBrandModel) {
            $query->addSelect([
                DB::raw("COALESCE(NULLIF(rb.name, ''), NULLIF(i.brand, '')) as normalized_brand"),
                DB::raw("COALESCE(NULLIF(rm.name, ''), NULLIF(i.model, '')) as normalized_model"),
            ]);
        }
        if ($schoolId && Schema::hasColumn('rental_items', 'school_id')) {
            $query->where('i.school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_items', 'deleted_at')) {
            $query->whereNull('i.deleted_at');
        }

        $item = $query->first();
        if (!$item) {
            return $this->sendError('Not found', [], 404);
        }

        $item = $this->decorateBrandModelRow($item);
        $item->tags = $this->itemTags((int) $item->id, $schoolId);
        return $this->sendResponse($item, 'Data retrieved successfully');
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

        $hasNormalizedBrandModel = Schema::hasTable('rental_brands')
            && Schema::hasTable('rental_models')
            && Schema::hasColumn('rental_items', 'brand_id')
            && Schema::hasColumn('rental_items', 'model_id');
        if ($hasNormalizedBrandModel) {
            $itemQuery
                ->leftJoin('rental_brands as rb', 'rb.id', '=', 'i.brand_id')
                ->leftJoin('rental_models as rm', 'rm.id', '=', 'i.model_id')
                ->addSelect([
                    DB::raw("COALESCE(NULLIF(rb.name, ''), NULLIF(i.brand, '')) as normalized_brand"),
                    DB::raw("COALESCE(NULLIF(rm.name, ''), NULLIF(i.model, '')) as normalized_model"),
                ]);
        }

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
        $item = $this->decorateBrandModelRow($item);
        $item->tags = $this->itemTags((int) $item->id, $schoolId);

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
                    DB::raw("COALESCE(NULLIF(r.currency,''), 'CHF') as currency"),
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
            'images' => $this->itemImages((int) $item->id, $schoolId),
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
        if (!Schema::hasTable('rental_items')) {
            return $this->tableMissingResponse('rental_items');
        }

        $payload = $request->only(['school_id', 'category_id', 'name', 'brand', 'model', 'brand_id', 'model_id', 'description', 'image', 'active']);
        $schoolId = $this->getSchoolId($request);
        if ($schoolId && Schema::hasColumn('rental_items', 'school_id') && !isset($payload['school_id'])) {
            $payload['school_id'] = $schoolId;
        }

        $payload = $this->normalizeBrandModelPayload($payload, $schoolId);

        if (Schema::hasColumn('rental_items', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_items', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $id = DB::table('rental_items')->insertGetId($payload);
        $this->syncItemTags($id, $schoolId, $this->normalizeTags($request->input('tags')));

        $row = DB::table('rental_items')->where('id', $id)->first();
        $row = $this->decorateBrandModelRow($row);
        $row->tags = $this->itemTags($id, $schoolId);
        return $this->sendResponse($row, 'Created successfully');
    }

    public function update(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_items')) {
            return $this->tableMissingResponse('rental_items');
        }

        $schoolId = $this->getSchoolId($request);
        $payload = $request->only(['category_id', 'name', 'brand', 'model', 'brand_id', 'model_id', 'description', 'image', 'active']);
        $payload = $this->normalizeBrandModelPayload($payload, $schoolId);
        if (Schema::hasColumn('rental_items', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $query = DB::table('rental_items')->where('id', $id);
        if ($schoolId && Schema::hasColumn('rental_items', 'school_id')) {
            $query->where('school_id', $schoolId);
        }
        if (Schema::hasColumn('rental_items', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (!empty($payload)) {
            $updated = $query->update($payload);
            if (!$updated) {
                return $this->sendError('Not found', [], 404);
            }
        }

        if ($request->has('tags')) {
            $this->syncItemTags($id, $schoolId, $this->normalizeTags($request->input('tags')));
        }

        $row = DB::table('rental_items')->where('id', $id)->first();
        if (!$row) {
            return $this->sendError('Not found', [], 404);
        }

        $row = $this->decorateBrandModelRow($row);
        $row->tags = $this->itemTags($id, $schoolId);
        return $this->sendResponse($row, 'Updated successfully');
    }

    public function updateDetail(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_items') || !Schema::hasTable('rental_variants')) {
            return $this->sendError('Rental detail tables are not available', [], 422);
        }

        $schoolId = $this->getSchoolId($request);
        $variantId = (int) $request->input('variant_id', 0);
        if ($variantId <= 0) {
            return $this->sendError('variant_id is required', [], 422);
        }

        $item = DB::table('rental_items')
            ->where('id', $id)
            ->when($schoolId && Schema::hasColumn('rental_items', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_items', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->first();
        if (!$item) {
            return $this->sendError('Not found', [], 404);
        }

        $variant = DB::table('rental_variants')
            ->where('id', $variantId)
            ->where('item_id', $id)
            ->when($schoolId && Schema::hasColumn('rental_variants', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_variants', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->first();
        if (!$variant) {
            return $this->sendError('Variant not found', [], 404);
        }

        DB::transaction(function () use ($request, $id, $variantId, $schoolId) {
            $itemPayload = $request->only([
                'category_id', 'name', 'brand', 'model', 'brand_id', 'model_id', 'description', 'active'
            ]);
            $itemPayload = $this->normalizeBrandModelPayload($itemPayload, $schoolId);
            if (Schema::hasColumn('rental_items', 'updated_at')) {
                $itemPayload['updated_at'] = now();
            }
            if (!empty($itemPayload)) {
                DB::table('rental_items')->where('id', $id)->update($itemPayload);
            }
            if ($request->has('tags')) {
                $this->syncItemTags($id, $schoolId, $this->normalizeTags($request->input('tags')));
            }

            $variantPayload = $request->only([
                'subcategory_id', 'name', 'size_group', 'size_label', 'sku', 'barcode',
                'serial_prefix', 'purchase_date', 'last_maintenance_date', 'notes', 'active'
            ]);
            if (Schema::hasColumn('rental_variants', 'updated_at')) {
                $variantPayload['updated_at'] = now();
            }
            if (!empty($variantPayload)) {
                DB::table('rental_variants')->where('id', $variantId)->update($variantPayload);
            }

            $pricing = $request->input('pricing', []);
            if (!is_array($pricing)) {
                $pricing = [];
            }
            $legacyPriceMap = [
                'half_day' => (float) $request->input('half_day_price', 0),
                'full_day' => (float) $request->input('full_day_price', 0),
                'week' => (float) $request->input('week_price', 0),
            ];
            foreach ($legacyPriceMap as $period => $price) {
                if (!array_key_exists($period, $pricing)) {
                    $pricing[$period] = ['price' => $price];
                }
            }

            if (Schema::hasTable('rental_pricing_rules')) {
                foreach (['half_day', 'full_day', 'week'] as $period) {
                    $line = $pricing[$period] ?? null;
                    $price = is_array($line) ? (float) ($line['price'] ?? 0) : (float) $line;
                    $currency = is_array($line) ? trim((string) ($line['currency'] ?? '')) : '';
                    $existing = DB::table('rental_pricing_rules')
                        ->where('variant_id', $variantId)
                        ->where('period_type', $period)
                        ->when($schoolId && Schema::hasColumn('rental_pricing_rules', 'school_id'), function ($query) use ($schoolId) {
                            $query->where('school_id', $schoolId);
                        })
                        ->when(Schema::hasColumn('rental_pricing_rules', 'deleted_at'), function ($query) {
                            $query->whereNull('deleted_at');
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($existing) {
                        $updatePayload = [
                            'price' => max(0, $price),
                            'active' => $price > 0,
                        ];
                        if ($currency !== '' && Schema::hasColumn('rental_pricing_rules', 'currency')) {
                            $updatePayload['currency'] = $currency;
                        }
                        if (Schema::hasColumn('rental_pricing_rules', 'updated_at')) {
                            $updatePayload['updated_at'] = now();
                        }
                        DB::table('rental_pricing_rules')->where('id', $existing->id)->update($updatePayload);
                        continue;
                    }

                    if ($price <= 0) {
                        continue;
                    }

                    $insertPayload = [
                        'variant_id' => $variantId,
                        'period_type' => $period,
                        'price' => $price,
                        'active' => true,
                    ];
                    if ($schoolId && Schema::hasColumn('rental_pricing_rules', 'school_id')) {
                        $insertPayload['school_id'] = $schoolId;
                    }
                    if (Schema::hasColumn('rental_pricing_rules', 'currency')) {
                        $insertPayload['currency'] = $currency !== '' ? $currency : 'CHF';
                    }
                    if (Schema::hasColumn('rental_pricing_rules', 'created_at')) {
                        $insertPayload['created_at'] = now();
                    }
                    if (Schema::hasColumn('rental_pricing_rules', 'updated_at')) {
                        $insertPayload['updated_at'] = now();
                    }
                    DB::table('rental_pricing_rules')->insert($insertPayload);
                }
            }
        });

        return $this->detail($request, $id);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_items', $id);
    }

    public function syncTags(Request $request, int $id)
    {
        if (!Schema::hasTable('rental_items')) {
            return $this->tableMissingResponse('rental_items');
        }

        $schoolId = $this->getSchoolId($request);
        $item = DB::table('rental_items')
            ->where('id', $id)
            ->when($schoolId && Schema::hasColumn('rental_items', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_items', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->first();

        if (!$item) {
            return $this->sendError('Not found', [], 404);
        }

        $this->syncItemTags((int) $id, $schoolId, $this->normalizeTags($request->input('tags')));
        return $this->sendResponse($this->itemTags((int) $id, $schoolId), 'Updated successfully');
    }

    private function itemImages(int $itemId, ?int $schoolId)
    {
        if (!Schema::hasTable('rental_item_images')) {
            return collect();
        }

        return DB::table('rental_item_images')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_images', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_images', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function attachTagsToCollection(Collection $rows, ?int $schoolId): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }
        if (!Schema::hasTable('rental_tags') || !Schema::hasTable('rental_item_tags')) {
            return $rows->map(function ($row) {
                $row->tags = [];
                return $row;
            });
        }

        $itemIds = $rows->pluck('id')->map(fn($id) => (int) $id)->all();
        $tagRows = DB::table('rental_item_tags as rit')
            ->join('rental_tags as tag', 'tag.id', '=', 'rit.tag_id')
            ->select('rit.item_id', 'tag.id as tag_id', 'tag.name')
            ->whereIn('rit.item_id', $itemIds)
            ->when($schoolId && Schema::hasColumn('rental_item_tags', 'school_id'), function ($query) use ($schoolId) {
                $query->where('rit.school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_tags', 'deleted_at'), function ($query) {
                $query->whereNull('rit.deleted_at');
            })
            ->when(Schema::hasColumn('rental_tags', 'deleted_at'), function ($query) {
                $query->whereNull('tag.deleted_at');
            })
            ->orderBy('tag.name')
            ->get()
            ->groupBy('item_id');

        return $rows->map(function ($row) use ($tagRows) {
            $row->tags = ($tagRows->get((int) $row->id, collect()))
                ->map(fn($tagRow) => ['id' => (int) $tagRow->tag_id, 'name' => (string) $tagRow->name])
                ->values()
                ->all();
            return $row;
        });
    }

    private function decorateBrandModelFields(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            return $this->decorateBrandModelRow($row);
        });
    }

    private function decorateBrandModelRow($row)
    {
        if (!$row) {
            return $row;
        }

        $normalizedBrand = trim((string) ($row->normalized_brand ?? ''));
        $normalizedModel = trim((string) ($row->normalized_model ?? ''));

        if ($normalizedBrand !== '') {
            $row->brand = $normalizedBrand;
        }
        if ($normalizedModel !== '') {
            $row->model = $normalizedModel;
        }

        if (!isset($row->brand_name)) {
            $row->brand_name = trim((string) ($row->brand ?? ''));
        }
        if (!isset($row->model_name)) {
            $row->model_name = trim((string) ($row->model ?? ''));
        }

        unset($row->normalized_brand, $row->normalized_model);
        return $row;
    }

    private function normalizeBrandModelPayload(array $payload, ?int $schoolId): array
    {
        $hasBrandTable = Schema::hasTable('rental_brands');
        $hasModelTable = Schema::hasTable('rental_models');
        $hasBrandIdColumn = Schema::hasColumn('rental_items', 'brand_id');
        $hasModelIdColumn = Schema::hasColumn('rental_items', 'model_id');

        $brandId = isset($payload['brand_id']) ? (int) $payload['brand_id'] : 0;
        $modelId = isset($payload['model_id']) ? (int) $payload['model_id'] : 0;
        $brand = trim((string) ($payload['brand'] ?? ''));
        $model = trim((string) ($payload['model'] ?? ''));

        if ($hasBrandTable && $hasBrandIdColumn) {
            if ($brandId <= 0 && $brand !== '') {
                $brandId = $this->resolveBrandId($schoolId, $brand);
            }
            if ($brandId > 0) {
                $payload['brand_id'] = $brandId;
                if ($brand === '') {
                    $brand = $this->brandNameById($brandId);
                }
            } elseif (array_key_exists('brand_id', $payload)) {
                $payload['brand_id'] = null;
            }
        } else {
            unset($payload['brand_id']);
        }

        if ($hasModelTable && $hasModelIdColumn) {
            if ($modelId <= 0 && $model !== '') {
                $modelId = $this->resolveModelId($schoolId, $brandId > 0 ? $brandId : null, $model);
            }
            if ($modelId > 0) {
                $payload['model_id'] = $modelId;
                if ($model === '') {
                    $model = $this->modelNameById($modelId);
                }
            } elseif (array_key_exists('model_id', $payload)) {
                $payload['model_id'] = null;
            }
        } else {
            unset($payload['model_id']);
        }

        // Keep legacy text fields in sync while old consumers still rely on them.
        if (array_key_exists('brand', $payload) || $brand !== '') {
            $payload['brand'] = $brand;
        }
        if (array_key_exists('model', $payload) || $model !== '') {
            $payload['model'] = $model;
        }

        return $payload;
    }

    private function resolveBrandId(?int $schoolId, string $name): int
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return 0;
        }

        $query = DB::table('rental_brands')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)]);
        if (Schema::hasColumn('rental_brands', 'school_id')) {
            if ($schoolId && $schoolId > 0) {
                $query->where('school_id', $schoolId);
            } else {
                $query->whereNull('school_id');
            }
        }

        $existing = $query->first();
        if ($existing) {
            if (Schema::hasColumn('rental_brands', 'deleted_at') && !empty($existing->deleted_at)) {
                DB::table('rental_brands')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
            }
            return (int) $existing->id;
        }

        $payload = [
            'name' => $normalizedName,
            'slug' => Str::slug($normalizedName) ?: null,
            'active' => true,
        ];
        if (Schema::hasColumn('rental_brands', 'school_id')) {
            $payload['school_id'] = ($schoolId && $schoolId > 0) ? $schoolId : null;
        }
        if (Schema::hasColumn('rental_brands', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_brands', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return (int) DB::table('rental_brands')->insertGetId($payload);
    }

    private function resolveModelId(?int $schoolId, ?int $brandId, string $name): int
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return 0;
        }

        $query = DB::table('rental_models')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)]);
        if (Schema::hasColumn('rental_models', 'brand_id')) {
            if ($brandId && $brandId > 0) {
                $query->where('brand_id', $brandId);
            } else {
                $query->whereNull('brand_id');
            }
        }
        if (Schema::hasColumn('rental_models', 'school_id')) {
            if ($schoolId && $schoolId > 0) {
                $query->where('school_id', $schoolId);
            } else {
                $query->whereNull('school_id');
            }
        }

        $existing = $query->first();
        if ($existing) {
            if (Schema::hasColumn('rental_models', 'deleted_at') && !empty($existing->deleted_at)) {
                DB::table('rental_models')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
            }
            return (int) $existing->id;
        }

        $payload = [
            'brand_id' => ($brandId && $brandId > 0) ? $brandId : null,
            'name' => $normalizedName,
            'slug' => Str::slug($normalizedName) ?: null,
            'active' => true,
        ];
        if (Schema::hasColumn('rental_models', 'school_id')) {
            $payload['school_id'] = ($schoolId && $schoolId > 0) ? $schoolId : null;
        }
        if (Schema::hasColumn('rental_models', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('rental_models', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return (int) DB::table('rental_models')->insertGetId($payload);
    }

    private function brandNameById(int $brandId): string
    {
        if ($brandId <= 0 || !Schema::hasTable('rental_brands')) {
            return '';
        }
        return trim((string) (DB::table('rental_brands')->where('id', $brandId)->value('name') ?? ''));
    }

    private function modelNameById(int $modelId): string
    {
        if ($modelId <= 0 || !Schema::hasTable('rental_models')) {
            return '';
        }
        return trim((string) (DB::table('rental_models')->where('id', $modelId)->value('name') ?? ''));
    }

    private function itemTags(int $itemId, ?int $schoolId): array
    {
        if (!Schema::hasTable('rental_tags') || !Schema::hasTable('rental_item_tags')) {
            return [];
        }

        return DB::table('rental_item_tags as rit')
            ->join('rental_tags as tag', 'tag.id', '=', 'rit.tag_id')
            ->select('tag.id', 'tag.name')
            ->where('rit.item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_tags', 'school_id'), function ($query) use ($schoolId) {
                $query->where('rit.school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_tags', 'deleted_at'), function ($query) {
                $query->whereNull('rit.deleted_at');
            })
            ->when(Schema::hasColumn('rental_tags', 'deleted_at'), function ($query) {
                $query->whereNull('tag.deleted_at');
            })
            ->orderBy('tag.name')
            ->get()
            ->map(fn($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->values()
            ->all();
    }

    private function normalizeTags($raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            $decoded = null;
            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $decoded = null;
            }
            if (is_array($decoded)) {
                $values = $decoded;
            } else {
                $values = explode(',', $trimmed);
            }
        } else {
            return [];
        }

        return collect($values)
            ->map(function ($value) {
                if (is_array($value)) {
                    return trim((string) ($value['name'] ?? ''));
                }
                return trim((string) $value);
            })
            ->filter(fn($value) => $value !== '')
            ->map(fn($value) => mb_substr($value, 0, 64))
            ->unique(fn($value) => mb_strtolower($value))
            ->values()
            ->all();
    }

    private function syncItemTags(int $itemId, ?int $schoolId, array $tagNames): void
    {
        if (!Schema::hasTable('rental_tags') || !Schema::hasTable('rental_item_tags')) {
            return;
        }

        $tagIds = [];
        foreach ($tagNames as $name) {
            $tagIds[] = $this->resolveTagId($schoolId, $name);
        }
        $tagIds = array_values(array_unique(array_filter($tagIds)));

        DB::table('rental_item_tags')
            ->where('item_id', $itemId)
            ->when($schoolId && Schema::hasColumn('rental_item_tags', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->when(Schema::hasColumn('rental_item_tags', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->whereNotIn('tag_id', !empty($tagIds) ? $tagIds : [0])
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        foreach ($tagIds as $tagId) {
            $existing = DB::table('rental_item_tags')
                ->where('item_id', $itemId)
                ->where('tag_id', $tagId)
                ->when($schoolId && Schema::hasColumn('rental_item_tags', 'school_id'), function ($query) use ($schoolId) {
                    $query->where('school_id', $schoolId);
                })
                ->first();

            if ($existing && !empty($existing->deleted_at)) {
                DB::table('rental_item_tags')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
                continue;
            }

            if (!$existing) {
                DB::table('rental_item_tags')->insert([
                    'school_id' => $schoolId,
                    'item_id' => $itemId,
                    'tag_id' => $tagId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function resolveTagId(?int $schoolId, string $name): ?int
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return null;
        }
        $slug = Str::slug($normalizedName);
        if ($slug === '') {
            $slug = Str::slug(Str::ascii($normalizedName));
        }

        $existing = DB::table('rental_tags')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
            ->when($schoolId && Schema::hasColumn('rental_tags', 'school_id'), function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->first();

        if ($existing) {
            if (!empty($existing->deleted_at) && Schema::hasColumn('rental_tags', 'deleted_at')) {
                DB::table('rental_tags')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'active' => true,
                    'updated_at' => now(),
                ]);
            }
            return (int) $existing->id;
        }

        return (int) DB::table('rental_tags')->insertGetId([
            'school_id' => $schoolId,
            'name' => $normalizedName,
            'slug' => $slug ?: null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
