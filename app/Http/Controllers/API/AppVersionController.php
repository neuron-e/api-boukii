<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppVersionController extends Controller
{
    /**
     * Get current minimum required app version
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVersion()
    {
        try {
            $version = DB::table('app_versions')
                ->orderBy('id', 'desc')
                ->first();

            if (!$version) {
                return response()->json([
                    'success' => false,
                    'message' => 'No version found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'android_version' => $version->android_version,
                    'android_version_code' => $version->android_version_code,
                    'ios_version' => $version->ios_version,
                    'force_update' => (bool)$version->force_update
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching version: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update minimum required version (Admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVersion(Request $request)
    {
        $request->validate([
            'android_version' => 'required|string',
            'android_version_code' => 'required|integer',
            'ios_version' => 'required|string',
            'force_update' => 'boolean'
        ]);

        try {
            DB::table('app_versions')->insert([
                'android_version' => $request->android_version,
                'android_version_code' => $request->android_version_code,
                'ios_version' => $request->ios_version,
                'force_update' => $request->force_update ?? true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Version updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating version: ' . $e->getMessage()
            ], 500);
        }
    }
}
