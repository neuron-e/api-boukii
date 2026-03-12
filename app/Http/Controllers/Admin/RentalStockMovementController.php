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
        $query = DB::table('rental_stock_movements as rsm')
            ->select([
                'rsm.*',
                DB::raw('u.name as actor_name'),
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

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if (!empty($dateFrom)) {
            $query->whereDate('rsm.occurred_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('rsm.occurred_at', '<=', $dateTo);
        }

        $query->orderByDesc('rsm.occurred_at')->orderByDesc('rsm.id');
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
}

