<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\Admin\StatisticsController as AdminStatisticsController;
use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends AppBaseController
{
    public function getMonitorDailyBookings(Request $request): JsonResponse
    {
        $monitor = $this->getMonitor($request);
        if (!$monitor) {
            return $this->sendError('Monitor not found for user', [], 404);
        }

        $request->merge([
            'monitor_id' => $monitor->id,
        ]);

        $adminController = app(AdminStatisticsController::class);
        return $adminController->getMonitorDailyBookings($request, $monitor->id);
    }
}
