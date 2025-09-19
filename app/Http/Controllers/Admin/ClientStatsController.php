<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientStatsController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Total clients
            $totalClients = Client::count();

            // Active clients (those with active bookings in the last 6 months)
            $activeClients = Client::whereHas('bookingUsersActive')->count();

            // VIP clients
            $vipClients = Client::where('is_vip', true)->count();

            // Inactive clients (total - active)
            $inactiveClients = $totalClients - $activeClients;

            return response()->json([
                'success' => true,
                'data' => [
                    'active' => $activeClients,
                    'inactive' => $inactiveClients,
                    'vip' => $vipClients,
                    'total' => $totalClients
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching client statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClientsByType(Request $request, $type)
    {
        try {
            $query = Client::query();

            switch ($type) {
                case 'active':
                    $query->whereHas('bookingUsersActive');
                    break;
                case 'inactive':
                    $query->whereDoesntHave('bookingUsersActive');
                    break;
                case 'vip':
                    $query->where('is_vip', true);
                    break;
                case 'all':
                default:
                    // No additional filters
                    break;
            }

            $clients = $query->select('id', 'first_name', 'last_name', 'email', 'is_vip')
                           ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $clients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
