<?php

namespace App\Http\Controllers\Api\V5;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V5\LoginV5Request;
use App\Http\Requests\API\V5\InitialLoginV5Request;
use App\Http\Requests\API\V5\CheckUserV5Request;
use App\Http\Requests\API\V5\SelectSchoolV5Request;
use App\Http\Requests\API\V5\SelectSeasonV5Request;
use App\Models\User;
use App\Models\School;
use App\Models\Season;
use App\V5\Modules\Auth\Services\AuthV5Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="V5 Authentication",
 *     description="Unified authentication endpoints for Boukii V5 system"
 * )
 */
class AuthController extends Controller
{
    protected AuthV5Service $authService;

    public function __construct(AuthV5Service $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @OA\Post(
     *     path="/api/v5/auth/check-user",
     *     summary="Check user credentials and return available schools",
     *     tags={"V5 Authentication"}
     * )
     */
    public function checkUser(CheckUserV5Request $request): JsonResponse
    {
        try {
            $email = $request->validated()['email'];
            $password = $request->validated()['password'];

            $user = User::where('email', $email)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Credenciales incorrectas.']
                ]);
            }

            if (!$user->active) {
                return $this->errorResponse('Cuenta inactiva. Contacte al administrador.', 403);
            }

            // Get user's available schools
            $schoolsQuery = $user->hasRole('superadmin')
                ? School::query()
                    ->select(['id', 'name', 'slug', 'logo'])
                    ->where('active', 1)
                    ->whereNull('deleted_at')
                : $user->schools()->select(['schools.id', 'schools.name', 'schools.slug', 'schools.logo']);

            $schools = $schoolsQuery
                ->get()
                ->map(function ($school) use ($user) {
                    return [
                        'id' => $school->id,
                        'name' => $school->name,
                        'slug' => $school->slug,
                        'logo' => $school->logo,
                        'user_role' => $this->getUserRoleInSchool($user, $school),
                        'can_administer' => $this->userCanAdministerSchool($user, $school)
                    ];
                });

            if ($schools->isEmpty()) {
                return $this->errorResponse('Usuario sin escuelas asignadas.', 403);
            }

            // Determinar si requiere selección de escuela
            $requiresSchoolSelection = $schools->count() > 1;
            
            // Crear token temporal para TODOS los usuarios (single y multi-school)
            // porque selectSchool necesita autenticación
            $tokenName = 'temp-login-' . $user->id . '-' . now()->format('YmdHis');
            $token = $user->createToken($tokenName, ['temp-access']);
            $tempToken = $token->plainTextToken;
            
            Log::info('Temporary token created for user', [
                'user_id' => $user->id,
                'token_id' => $token->accessToken->id,
                'schools_count' => $schools->count(),
                'requires_school_selection' => $requiresSchoolSelection
            ]);

            Log::info('User credentials verified', [
                'user_id' => $user->id,
                'email' => $email,
                'schools_count' => $schools->count(),
                'requires_school_selection' => $requiresSchoolSelection
            ]);

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'role' => $this->getUserPrimaryRole($user)
                ],
                'schools' => $schools->values()->all(),
                'requires_school_selection' => $requiresSchoolSelection,
                'temp_token' => $tempToken // Always include temp_token
            ], 'Credenciales verificadas correctamente');

        } catch (ValidationException $e) {
            return $this->errorResponse('Credenciales incorrectas', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Check user failed', [
                'email' => $request->validated()['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error interno del servidor', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v5/auth/initial-login",
     *     summary="Initial login without season selection",
     *     tags={"V5 Authentication"}
     * )
     */
    public function initialLogin(InitialLoginV5Request $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = User::where('email', $validated['email'])->first();
            $school = School::find($validated['school_id']);

            if (!$this->userHasAccessToSchool($user, $school)) {
                return $this->errorResponse('Acceso denegado a esta escuela', 403);
            }

            // Create token with school context but without season
            $tokenName = 'boukii-v5-' . $school->slug . '-' . now()->format('YmdHis');
            $contextData = [
                'school_id' => $school->id,
                'school_slug' => $school->slug,
                'login_at' => now()->toISOString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ];

            $token = $user->createToken($tokenName, ['*'], null, [
                'context_data' => $contextData
            ]);

            Log::info('Initial login successful', [
                'user_id' => $user->id,
                'school_id' => $school->id,
                'token_id' => $token->accessToken->id
            ]);

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $this->getUserPrimaryRole($user)
                ],
                'school' => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                    'logo' => $school->logo
                ],
                'token' => $token->plainTextToken,
                'requires_season_selection' => true
            ], 'Login inicial completado');

        } catch (\Exception $e) {
            Log::error('Initial login failed', [
                'email' => $validated['email'] ?? null,
                'school_id' => $validated['school_id'] ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error en login inicial', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v5/auth/select-school",
     *     summary="Select school after user verification",
     *     tags={"V5 Authentication"}
     * )
     */
    public function selectSchool(SelectSchoolV5Request $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user(); // Get user from authenticated token
            $school = School::find($validated['school_id']);

            Log::info('selectSchool called', [
                'user_id' => $user?->id,
                'school_id' => $validated['school_id'],
                'school_found' => !!$school
            ]);

            if (!$user) {
                return $this->errorResponse('Usuario no autenticado', 401);
            }

            if (!$this->userHasAccessToSchool($user, $school)) {
                return $this->errorResponse('Acceso denegado a esta escuela', 403);
            }

            // Revoke the temporary token
            $request->user()->currentAccessToken()->delete();

            // Get available seasons for this school
            $seasons = Season::where('school_id', $school->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->get(['id', 'name', 'start_date', 'end_date', 'is_active']); // ✅ FIX: Include is_active field

            $currentSeason = $seasons->first(); // Use most recent active season

            // Create full access token with school and season context
            $tokenName = 'boukii-v5-' . $school->slug . '-' . now()->format('YmdHis');
            $contextData = [
                'school_id' => $school->id,
                'school_slug' => $school->slug,
                'season_id' => $currentSeason?->id,
                'login_at' => now()->toISOString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ];

            $token = $user->createToken($tokenName, ['*']);
            
            // Store context data in the token
            $token->accessToken->update([
                'context_data' => json_encode($contextData)
            ]);

            Log::info('School selection successful - full access token created', [
                'user_id' => $user->id,
                'school_id' => $school->id,
                'season_id' => $currentSeason?->id,
                'token_id' => $token->accessToken->id
            ]);

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at
                ],
                'school' => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                    'logo' => $school->logo
                ],
                'season' => $currentSeason ? [
                    'id' => $currentSeason->id,
                    'name' => $currentSeason->name,
                    'start_date' => $currentSeason->start_date,
                    'end_date' => $currentSeason->end_date,
                    'is_active' => $currentSeason->is_active
                ] : null,
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => null, // Sanctum tokens don't expire by default
                'has_multiple_seasons' => $seasons->count() > 1,
                'available_seasons' => $seasons->count() > 1 ? $seasons : []
            ], 'Login completado exitosamente');

        } catch (\Exception $e) {
            Log::error('Select school failed', [
                'user_id' => $request->user()?->id,
                'school_id' => $validated['school_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Error al seleccionar escuela', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v5/auth/select-season",
     *     summary="Select or create season to complete login",
     *     tags={"V5 Authentication"}
     * )
     */
    public function selectSeason(SelectSeasonV5Request $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user(); // Get user from authenticated token
            
            if (!$user) {
                return $this->errorResponse('Usuario no autenticado', 401);
            }
            $school = School::find($validated['school_id']);

            if (!$this->userHasAccessToSchool($user, $school)) {
                return $this->errorResponse('Acceso denegado a esta escuela', 403);
            }

            // Handle season selection or creation
            if (isset($validated['season_id'])) {
                $season = Season::where('id', $validated['season_id'])
                    ->where('school_id', $school->id)
                    ->first();
                    
                if (!$season) {
                    return $this->errorResponse('Temporada no encontrada', 404);
                }
            } elseif (isset($validated['create_season'])) {
                // Create new season
                $season = new Season([
                    'name' => $validated['create_season']['name'],
                    'school_id' => $school->id,
                    'start_date' => $validated['create_season']['start_date'] ?? now()->startOfYear(),
                    'end_date' => $validated['create_season']['end_date'] ?? now()->endOfYear(),
                    'is_active' => true
                ]);
                $season->save();

                Log::info('New season created', [
                    'season_id' => $season->id,
                    'school_id' => $school->id,
                    'user_id' => $user->id
                ]);
            } else {
                return $this->errorResponse('Debe seleccionar una temporada o crear una nueva', 422);
            }

            return $this->completeLoginWithSeason($user, $school, $season, 
                $validated['remember_me'] ?? false, $request);

        } catch (\Exception $e) {
            Log::error('Select season failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error al seleccionar temporada', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v5/auth/login",
     *     summary="Complete login with school and season",
     *     tags={"V5 Authentication"}
     * )
     */
    public function login(LoginV5Request $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $user = User::where('email', $validated['email'])->first();
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Credenciales incorrectas.']
                ]);
            }

            $school = School::find($validated['school_id']);
            $season = Season::find($validated['season_id']);

            if (!$school || !$season) {
                return $this->errorResponse('Escuela o temporada no encontrada', 404);
            }

            if (!$this->userHasAccessToSchool($user, $school)) {
                return $this->errorResponse('Acceso denegado a esta escuela', 403);
            }

            if ($season->school_id !== $school->id) {
                return $this->errorResponse('La temporada no pertenece a la escuela seleccionada', 422);
            }

            return $this->completeLoginWithSeason($user, $school, $season, 
                $validated['remember_me'] ?? false, $request);

        } catch (ValidationException $e) {
            return $this->errorResponse('Credenciales incorrectas', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Login failed', [
                'email' => $validated['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error en el login', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v5/auth/me",
     *     summary="Get authenticated user information",
     *     tags={"V5 Authentication"}
     * )
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api_v5')->user();
            if (!$user) {
                return $this->errorResponse('Usuario no autenticado', 401);
            }

            $token = $user->currentAccessToken();
            $contextData = $token ? $token->context_data : [];

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'role' => $this->getUserPrimaryRole($user),
                'context' => $contextData,
                'permissions' => $this->getUserPermissions($user, $contextData['school_id'] ?? null),
                'token_expires_at' => $token ? $token->expires_at : null
            ];

            return $this->successResponse($userData, 'Información de usuario obtenida');

        } catch (\Exception $e) {
            Log::error('Get user info failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error al obtener información del usuario', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v5/auth/logout",
     *     summary="Logout and revoke token",
     *     tags={"V5 Authentication"}
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api_v5')->user();
            if (!$user) {
                return $this->errorResponse('Usuario no autenticado', 401);
            }

            // Revoke current token
            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
                Log::info('User logged out', [
                    'user_id' => $user->id,
                    'token_id' => $token->id
                ]);
            }

            return $this->successResponse(null, 'Sesión cerrada correctamente');

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error al cerrar sesión', 500);
        }
    }

    /**
     * Check user permissions for a specific season
     */
    public function permissions(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id ?? 0;
            $seasonId = (int) $request->get('season_id');
            
            $permissions = $this->authService->checkSeasonPermissions($userId, $seasonId);

            return $this->successResponse([
                'permissions' => $permissions,
                'season_id' => $seasonId,
                'user_id' => $userId
            ], 'Permisos obtenidos correctamente');

        } catch (\Exception $e) {
            Log::error('Get permissions failed', [
                'user_id' => $userId ?? null,
                'season_id' => $seasonId ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error al obtener permisos', 500);
        }
    }

    /**
     * Switch season context
     */
    public function switchSeason(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api_v5')->user();
            if (!$user) {
                return $this->errorResponse('Usuario no autenticado', 401);
            }

            $seasonId = (int) $request->get('season_id');
            $season = Season::find($seasonId);

            if (!$season) {
                return $this->errorResponse('Temporada no encontrada', 404);
            }

            // Verify user has access to this season's school
            $token = $user->currentAccessToken();
            $contextData = $token ? $token->context_data : [];
            
            if (($contextData['school_id'] ?? null) != $season->school_id) {
                return $this->errorResponse('No tiene acceso a esta temporada', 403);
            }

            // Update service with new season role
            $this->authService->assignSeasonRole($user->id, $seasonId, 'active');

            return $this->successResponse([
                'switched' => true,
                'season_id' => $seasonId,
                'season_name' => $season->name
            ], 'Contexto de temporada cambiado correctamente');

        } catch (\Exception $e) {
            Log::error('Switch season failed', [
                'user_id' => $user->id ?? null,
                'season_id' => $seasonId ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Error al cambiar temporada', 500);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Complete login process with season context
     */
    private function completeLoginWithSeason(User $user, School $school, Season $season, bool $rememberMe, Request $request): JsonResponse
    {
        // Create token with full context
        $tokenName = 'boukii-v5-' . $school->slug . '-' . $season->id . '-' . now()->format('YmdHis');
        $contextData = [
            'school_id' => $school->id,
            'school_slug' => $school->slug,
            'season_id' => $season->id,
            'login_at' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];

        $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(8);
        $token = $user->createToken($tokenName, ['*'], $expiresAt, [
            'context_data' => $contextData
        ]);

        // Assign season role via service (use 'client' as default role)
        $this->authService->assignSeasonRole($user->id, $season->id, 'client');

        Log::info('Complete login successful', [
            'user_id' => $user->id,
            'school_id' => $school->id,
            'season_id' => $season->id,
            'token_id' => $token->accessToken->id,
            'remember_me' => $rememberMe
        ]);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->getUserPrimaryRole($user),
                'permissions' => $this->getUserPermissions($user, $school->id)
            ],
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'logo' => $school->logo
            ],
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'start_date' => $season->start_date,
                'end_date' => $season->end_date,
                'is_active' => $season->is_active
            ],
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'context' => $contextData
        ], 'Login completado correctamente');
    }

    /**
     * Check if user has access to school
     */
    private function userHasAccessToSchool(User $user, School $school): bool
    {
        if (!$user || !$school || !$school->active) {
            return false;
        }

        // Check if user is owner of the school
        if ($school->owner_id === $user->id) {
            return true;
        }

        // Check if user has direct relationship with school
        return $user->schools()->where('schools.id', $school->id)->exists();
    }

    /**
     * Check if user can administer school
     */
    private function userCanAdministerSchool(User $user, School $school): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (!$this->userHasAccessToSchool($user, $school)) {
            return false;
        }

        // Owner can always administer
        if ($school->owner_id === $user->id) {
            return true;
        }

        // Check admin role in school
        return $user->schools()
            ->where('schools.id', $school->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Get user's role in specific school
     */
    private function getUserRoleInSchool(User $user, School $school): string
    {
        if ($user->hasRole('superadmin')) {
            return 'superadmin';
        }

        if ($school->owner_id === $user->id) {
            return 'owner';
        }

        $schoolUser = $user->schools()
            ->where('schools.id', $school->id)
            ->first();

        return $schoolUser?->pivot?->role ?? 'member';
    }

    /**
     * Get user's primary role
     */
    private function getUserPrimaryRole(User $user): string
    {
        // This could be enhanced with proper role system
        if ($user->hasRole('superadmin')) {
            return 'superadmin';
        }

        return 'admin'; // Default for now
    }

    /**
     * Get user permissions for school
     */
    private function getUserPermissions(User $user, ?int $schoolId): array
    {
        if (!$schoolId) {
            return [];
        }

        // This would integrate with your permission system
        return [
            'school.view',
            'school.manage',
            'season.view',
            'season.manage',
            'booking.view',
            'booking.manage',
            'client.view',
            'client.manage'
        ];
    }

    /**
     * Success response helper
     */
    private function successResponse($data, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Error response helper
     */
    private function errorResponse(string $message, int $status = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}