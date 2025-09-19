<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\JsonResponse)  $next
     * @param  string  $requiredScope
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next, string $requiredScope = null)
    {
        $apiKeyHeader = $request->header('X-Api-Key');

        if (!$apiKeyHeader) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
                'error' => 'Missing X-Api-Key header'
            ], 401);
        }

        $apiKey = ApiKey::validateKey($apiKeyHeader);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
                'error' => 'API key not found or inactive'
            ], 401);
        }

        // Check required scope if specified
        if ($requiredScope && !$apiKey->hasScope($requiredScope)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
                'error' => "API key does not have required scope: {$requiredScope}"
            ], 403);
        }

        // Mark API key as used (update last_used_at)
        $apiKey->markAsUsed();

        // Add API key info to request for use in controllers
        $request->merge([
            'api_key' => $apiKey,
            'authenticated_school_id' => $apiKey->school_id,
        ]);

        return $next($request);
    }
}
