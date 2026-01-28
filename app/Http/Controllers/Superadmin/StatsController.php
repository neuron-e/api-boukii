<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\Booking;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends AppBaseController
{
    public function overview(Request $request): JsonResponse
    {
        $schoolCount = School::count();
        $activeSchoolCount = School::where('active', 1)->count();
        $totalBookings = Booking::count();
        $totalRevenue = (float)Booking::sum('price_total');
        $adminCount = User::where('type', 1)->count();

        $monthlyBookings = Booking::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->take(12)
            ->get()
            ->map(fn($row) => [
                'month' => $row->month,
                'total' => (int)$row->total
            ]);

        return $this->sendResponse([
            'school_count' => $schoolCount,
            'active_school_count' => $activeSchoolCount,
            'admin_count' => $adminCount,
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'monthly_bookings' => $monthlyBookings
        ], 'Superadmin stats retrieved');
    }
}
