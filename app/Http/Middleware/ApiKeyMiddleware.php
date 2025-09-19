<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $scope  Required scope (e.g., "timing:write")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $scope)
    {
        $apiKey = $request->header('X-Api-Key');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'API Key required',
                'message' => 'X-Api-Key header is required'
            ], 401);
        }

        // Buscar la API Key en la base de datos
        $keyRecord = ApiKey::where('key_hash', hash('sha256', $apiKey))
            ->where('is_active', true)
            ->first();

        if (!$keyRecord) {
            return response()->json([
                'error' => 'Invalid API Key',
                'message' => 'API Key not found or inactive'
            ], 401);
        }

        // Verificar scope
        $scopes = $keyRecord->scopes ?? [];
        if (!in_array($scope, $scopes)) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => "Scope '{$scope}' is required"
            ], 403);
        }

        // Verificar rate limiting si está configurado
        if ($keyRecord->rate_limit_per_minute) {
            $cacheKey = "api_key_rate_limit:{$keyRecord->id}:" . now()->format('Y-m-d H:i');
            $currentRequests = cache()->increment($cacheKey, 1, 60);
            
            if ($currentRequests > $keyRecord->rate_limit_per_minute) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => "Maximum {$keyRecord->rate_limit_per_minute} requests per minute"
                ], 429);
            }
        }

        // Actualizar last_used_at
        $keyRecord->update(['last_used_at' => now()]);

        // Agregar información de la API Key al request para uso posterior
        $request->merge([
            'api_key_record' => $keyRecord
        ]);

        // Establecer el usuario autenticado basado en school_id de la API Key
        if ($keyRecord->school_id) {
            $user = \App\Models\User::where('school_id', $keyRecord->school_id)
                ->where('role', 'admin')
                ->first();
            
            if ($user) {
                auth()->login($user);
            }
        }

        return $next($request);
    }
}