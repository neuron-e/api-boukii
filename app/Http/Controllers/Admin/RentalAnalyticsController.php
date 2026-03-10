<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rental analytics endpoints for the admin analytics section (Phase 5).
 */
class RentalAnalyticsController extends AppBaseController
{
    /**
     * GET /admin/rentals/analytics
     *
     * Returns aggregated metrics for rental reservations within a date range.
     * Query params: school_id, date_from, date_to
     */
    public function summary(Request $request): JsonResponse
    {
        if (!Schema::hasTable('rental_reservations')) {
            return $this->sendResponse($this->emptyMetrics(), 'Rental table not available');
        }

        $schoolId = $this->getSchool($request)->id;
        $dateFrom = $request->input('date_from', $request->input('start_date'));
        $dateTo   = $request->input('date_to', $request->input('end_date'));

        $base = DB::table('rental_reservations as rr')
            ->where('rr.school_id', $schoolId)
            ->whereNull('rr.deleted_at');

        if ($dateFrom) {
            $base->whereDate('rr.start_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $base->whereDate('rr.start_date', '<=', $dateTo);
        }

        // --- KPIs ---
        $totals = (clone $base)
            ->selectRaw("
                COUNT(*) as total_reservations,
                SUM(CASE WHEN status NOT IN ('cancelled') THEN 1 ELSE 0 END) as active_reservations,
                SUM(CASE WHEN status IN ('completed','returned') THEN 1 ELSE 0 END) as completed_reservations,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_reservations,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_reservations,
                SUM(CASE WHEN status IN ('completed','returned') THEN COALESCE(rr.total,0) ELSE 0 END) as revenue_completed,
                SUM(CASE WHEN status NOT IN ('cancelled') THEN COALESCE(rr.total,0) ELSE 0 END) as revenue_expected,
                SUM(COALESCE(rr.damage_total,0)) as total_damage_cost
            ")
            ->first();

        // --- Revenue by month ---
        $revenueByMonth = (clone $base)
            ->whereNotIn('rr.status', ['cancelled'])
            ->selectRaw("
                DATE_FORMAT(rr.start_date, '%Y-%m') as month,
                COUNT(*) as reservations,
                SUM(COALESCE(rr.total, 0)) as revenue
            ")
            ->groupByRaw("DATE_FORMAT(rr.start_date, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(rr.start_date, '%Y-%m')")
            ->get();

        // --- Top items ---
        $topItems = [];
        if (Schema::hasTable('rental_reservation_lines') && Schema::hasTable('rental_items')) {
            $reservationIds = (clone $base)->pluck('rr.id');
            if ($reservationIds->isNotEmpty()) {
                $topItems = DB::table('rental_reservation_lines as rrl')
                    ->join('rental_items as ri', 'ri.id', '=', 'rrl.item_id')
                    ->whereIn('rrl.rental_reservation_id', $reservationIds)
                    ->selectRaw('ri.id as item_id, ri.name as item_name, SUM(rrl.quantity) as total_qty, SUM(rrl.line_total) as total_revenue')
                    ->groupBy('ri.id', 'ri.name')
                    ->orderByDesc('total_qty')
                    ->limit(10)
                    ->get();
            }
        }

        // --- Top clients ---
        $topClients = [];
        if (Schema::hasTable('clients')) {
            $topClients = (clone $base)
                ->join('clients as c', 'c.id', '=', 'rr.client_id')
                ->whereNotIn('rr.status', ['cancelled'])
                ->selectRaw("c.id, CONCAT(c.first_name, ' ', c.last_name) as client_name, COUNT(*) as reservations, SUM(COALESCE(rr.total,0)) as total_spent")
                ->groupBy('c.id', 'c.first_name', 'c.last_name')
                ->orderByDesc('reservations')
                ->limit(10)
                ->get();
        }

        // --- Status breakdown ---
        $statusBreakdown = (clone $base)
            ->selectRaw('status, COUNT(*) as count, SUM(COALESCE(total, 0)) as revenue')
            ->groupBy('status')
            ->get();

        return $this->sendResponse([
            'period' => ['start' => $dateFrom, 'end' => $dateTo],
            'kpis' => [
                'total_reservations'     => (int) ($totals->total_reservations ?? 0),
                'active_reservations'    => (int) ($totals->active_reservations ?? 0),
                'completed_reservations' => (int) ($totals->completed_reservations ?? 0),
                'overdue_reservations'   => (int) ($totals->overdue_reservations ?? 0),
                'cancelled_reservations' => (int) ($totals->cancelled_reservations ?? 0),
                'revenue_completed'      => round((float) ($totals->revenue_completed ?? 0), 2),
                'revenue_expected'       => round((float) ($totals->revenue_expected ?? 0), 2),
                'total_damage_cost'      => round((float) ($totals->total_damage_cost ?? 0), 2),
            ],
            'revenue_by_month' => $revenueByMonth,
            'top_items'        => $topItems,
            'top_clients'      => $topClients,
            'status_breakdown' => $statusBreakdown,
        ], 'Rental analytics retrieved');
    }

    /**
     * GET /admin/rentals/analytics/export
     *
     * Returns a CSV file of all rental reservations within the date range.
     */
    public function exportCsv(Request $request): Response
    {
        $schoolId  = $this->getSchool($request)->id;
        $dateFrom = $request->input('date_from', $request->input('start_date'));
        $dateTo   = $request->input('date_to', $request->input('end_date'));

        $rows = [];
        if (Schema::hasTable('rental_reservations')) {
            $query = DB::table('rental_reservations as rr')
                ->where('rr.school_id', $schoolId)
                ->whereNull('rr.deleted_at')
                ->orderByDesc('rr.id');

            if ($dateFrom) {
                $query->whereDate('rr.start_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('rr.start_date', '<=', $dateTo);
            }

            if (Schema::hasTable('clients')) {
                $query->leftJoin('clients as c', 'c.id', '=', 'rr.client_id')
                      ->addSelect(DB::raw("CONCAT(c.first_name, ' ', c.last_name) as client_name"), DB::raw('c.email as client_email'));
            }
            if (Schema::hasTable('rental_pickup_points') && Schema::hasColumn('rental_reservations', 'rental_pickup_point_id')) {
                $query->leftJoin('rental_pickup_points as pp', 'pp.id', '=', 'rr.rental_pickup_point_id')
                      ->addSelect(DB::raw('pp.name as pickup_point_name'));
            }

            $query->select('rr.id', 'rr.start_date', 'rr.end_date', 'rr.status',
                           'rr.total', 'rr.currency', 'rr.damage_total', 'rr.created_at');

            $rows = $query->get();
        }

        $headers = ['ID', 'Client', 'Email', 'Start Date', 'End Date', 'Status', 'Total', 'Currency', 'Damage Cost', 'Pickup Point', 'Created At'];
        $csv = implode(',', $headers) . "\n";

        foreach ($rows as $row) {
            $csv .= implode(',', [
                $row->id ?? '',
                '"' . str_replace('"', '""', $row->client_name ?? '') . '"',
                '"' . str_replace('"', '""', $row->client_email ?? '') . '"',
                $row->start_date ?? '',
                $row->end_date ?? '',
                $row->status ?? '',
                number_format((float) ($row->total ?? 0), 2, '.', ''),
                $row->currency ?? 'CHF',
                number_format((float) ($row->damage_total ?? 0), 2, '.', ''),
                '"' . str_replace('"', '""', $row->pickup_point_name ?? '') . '"',
                $row->created_at ?? '',
            ]) . "\n";
        }

        $filename = 'rental_analytics_' . ($dateFrom ?: 'all') . '_' . ($dateTo ?: 'all') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function emptyMetrics(): array
    {
        return [
            'kpis' => [
                'total_reservations' => 0, 'active_reservations' => 0, 'completed_reservations' => 0,
                'overdue_reservations' => 0, 'cancelled_reservations' => 0,
                'revenue_completed' => 0, 'revenue_expected' => 0, 'total_damage_cost' => 0,
            ],
            'revenue_by_month' => [], 'top_items' => [], 'top_clients' => [], 'status_breakdown' => [],
        ];
    }
}
