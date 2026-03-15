<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalStockMovementController extends RentalBaseController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('rental_stock_movements')) {
            return $this->tableMissingResponse('rental_stock_movements');
        }

        $schoolId = $this->getSchoolId($request);
        $actorNameSql = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), NULLIF(u.username, ''), NULLIF(u.email, ''), CONCAT('User #', u.id))";
        $query = DB::table('rental_stock_movements as rsm')
            ->select([
                'rsm.*',
                DB::raw("$actorNameSql as actor_name"),
                DB::raw('rv.name as variant_name'),
                DB::raw('rv.size_label as variant_size_label'),
                DB::raw('ri.name as item_name'),
                DB::raw('wf.name as warehouse_from_name'),
                DB::raw('wt.name as warehouse_to_name'),
            ])
            ->leftJoin('users as u', 'u.id', '=', 'rsm.user_id')
            ->leftJoin('rental_variants as rv', 'rv.id', '=', 'rsm.variant_id')
            ->leftJoin('rental_items as ri', 'ri.id', '=', 'rsm.item_id')
            ->leftJoin('rental_warehouses as wf', 'wf.id', '=', 'rsm.warehouse_id_from')
            ->leftJoin('rental_warehouses as wt', 'wt.id', '=', 'rsm.warehouse_id_to');

        if ($schoolId && Schema::hasColumn('rental_stock_movements', 'school_id')) {
            $query->where('rsm.school_id', $schoolId);
        }

        $this->applyEqualityFilter($query, $request, 'movement_type');
        $this->applyEqualityFilter($query, $request, 'user_id');
        $this->applyEqualityFilter($query, $request, 'variant_id');
        $this->applyEqualityFilter($query, $request, 'item_id');
        $this->applyEqualityFilter($query, $request, 'warehouse_id_from');
        $this->applyEqualityFilter($query, $request, 'warehouse_id_to');
        $this->applyEqualityFilter($query, $request, 'rental_reservation_id');
        $this->applyEqualityFilter($query, $request, 'rental_unit_id');
        $this->applyWarehouseFilter($query, $request);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($nested) use ($search) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search) . '%';
                $nested->where('rsm.movement_type', 'like', $like)
                    ->orWhere('rsm.reason', 'like', $like)
                    ->orWhere('rv.name', 'like', $like)
                    ->orWhere('rv.sku', 'like', $like)
                    ->orWhere('ri.name', 'like', $like)
                    ->orWhereRaw("$actorNameSql like ?", [$like])
                    ->orWhere('wf.name', 'like', $like)
                    ->orWhere('wt.name', 'like', $like);
            });
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if (!empty($dateFrom)) {
            $query->whereDate('rsm.occurred_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('rsm.occurred_at', '<=', $dateTo);
        }

        $sortBy = (string) $request->input('sort_by', 'occurred_at');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = [
            'occurred_at' => 'rsm.occurred_at',
            'movement_type' => 'rsm.movement_type',
            'quantity' => 'rsm.quantity',
            'variant_name' => 'rv.name',
            'warehouse_from_name' => 'wf.name',
            'warehouse_to_name' => 'wt.name',
        ];
        $sortColumn = $allowedSort[$sortBy] ?? 'rsm.occurred_at';
        $query->orderBy($sortColumn, $sortDir)->orderByDesc('rsm.id');
        $perPage = (int) $request->input('per_page', 100);
        $data = $query->paginate(max(1, min(1000, $perPage)));

        return $this->sendResponse($data, 'Data retrieved successfully');
    }

    private function applyEqualityFilter($query, Request $request, string $column): void
    {
        $value = $request->input($column);
        if ($value === null || $value === '') {
            return;
        }
        $query->where("rsm.$column", $value);
    }

    private function applyWarehouseFilter($query, Request $request): void
    {
        $warehouseId = (int) $request->input('warehouse_id', 0);
        if ($warehouseId <= 0) {
            return;
        }
        $query->where(function ($nested) use ($warehouseId) {
            $nested->where('rsm.warehouse_id_from', $warehouseId)
                ->orWhere('rsm.warehouse_id_to', $warehouseId);
        });
    }
}
