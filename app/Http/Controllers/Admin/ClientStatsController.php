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
            $user = $request->user();
            $user->load('schools');
            $school = $user->schools->first();

            // Total clients (scoped to current school)
            $totalClients = DB::table('clients_schools')
                ->where('school_id', $school->id)
                ->distinct('client_id')
                ->count('client_id');

            // Active clients (those with active bookings in the last 6 months)
            $activeClients = Client::whereHas('bookingUsersActive')->count();

            // VIP clients (per current school)
            $vipClients = DB::table('clients_schools')
                ->where('school_id', $school->id)
                ->where('is_vip', true)
                ->distinct('client_id')
                ->count('client_id');

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
            $user = $request->user();
            $user->load('schools');
            $school = $user->schools->first();

            $query = Client::query()
                ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
                ->where('clients_schools.school_id', $school->id);

            switch ($type) {
                case 'active':
                    $query->whereHas('bookingUsersActive');
                    break;
                case 'inactive':
                    $query->whereDoesntHave('bookingUsersActive');
                    break;
                case 'vip':
                    $query->where('clients_schools.is_vip', true);
                    break;
                case 'all':
                default:
                    // No additional filters
                    break;
            }

            $clients = $query->select('clients.id', 'clients.first_name', 'clients.last_name', 'clients.email')
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
