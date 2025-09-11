<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use Illuminate\Database\Eloquent\SoftDeletes;
 use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;
use App\V5\Models\UserSeasonRole;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Pivot;

/**
 * @OA\Schema(
 *      schema="User",
 *      required={"password","type","active"},
 *      @OA\Property(
 *          property="username",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *           property="first_name",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="string",
 *       ),
 *       @OA\Property(
 *           property="last_name",
 *           description="",
 *           readOnly=false,
 *           nullable=false,
 *           type="string",
 *       ),
 *      @OA\Property(
 *          property="email",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="password",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="image",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="",
 *          readOnly=false,
 *          nullable=false,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="active",
 *          description="avoids login",
 *          readOnly=false,
 *          nullable=false,
 *          type="boolean",
 *      ),
 *      @OA\Property(
 *          property="recover_token",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="",
 *          readOnly=true,
 *          nullable=true,
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="logout",
 *          description="",
 *          readOnly=false,
 *          nullable=true,
 *          type="boolean",
 *      )
 * )
 */class User extends Authenticatable
{
     use LogsActivity, SoftDeletes, HasFactory, Notifiable, HasApiTokens, HasRoles;    public $table = 'users';

    public $fillable = [
        'username',
        'email',
        'first_name',
        'last_name',
        'password',
        'image',
        'type',
        'active',
        'recover_token',
        'logout'
    ];

    protected $casts = [
        'username' => 'string',
        'first_name' => 'string',
        'last_name' => 'string',
        'email' => 'string',
        'password' => 'string',
        'image' => 'string',
        'type' => 'string',
        'active' => 'boolean',
        'recover_token' => 'string',
        'logout' => 'boolean'
    ];

    public static array $rules = [
        'username' => 'nullable|string|max:255',
        'first_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'email' => 'nullable|string|max:100',
        'password' => 'nullable|string|max:255',
        'image' => 'nullable|string',
        'type' => 'nullable|string|max:100',
        'active' => 'nullable|boolean',
        'recover_token' => 'nullable|string|max:65535',
        'created_at' => 'nullable',
        'updated_at' => 'nullable',
        'deleted_at' => 'nullable',
        'logout' => 'nullable|boolean'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function bookingLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\BookingLog::class, 'user_id');
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Client::class, 'user_id');
    }

    public function monitors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Monitor::class, 'user_id');
    }

    public function schoolUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\SchoolUser::class, 'user_id');
    }

    public function schools(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\School::class,
            Pivot::USER_SCHOOLS,
            'user_id',
            'school_id'
        )
        ->whereNull(Pivot::USER_SCHOOLS . '.deleted_at')
        ->where('schools.active', 1)
        ->whereNull('schools.deleted_at');
    }

    /**
     * Get the primary school for this user
     */
    public function getCurrentSchool()
    {
        return $this->schools()->first();
    }

    /**
     * Get the primary school ID for this user
     */
    public function getCurrentSchoolId(): ?int
    {
        $school = $this->getCurrentSchool();
        return $school ? $school->id : null;
    }

    public function userSeasonRoles(): HasMany
    {
        return $this->hasMany(UserSeasonRole::class);
    }

    /**
     * Get the school-specific roles for this user
     * This works alongside Spatie's HasRoles trait for school-contextualized permissions
     */
    public function schoolRoles(): HasMany
    {
        return $this->hasMany(UserSchoolRole::class);
    }

    /**
     * Check if user has specific role in a school
     */
    public function hasSchoolRole(string $roleName, int $schoolId): bool
    {
        return $this->schoolRoles()
            ->join('roles', 'user_school_roles.role_id', '=', 'roles.id')
            ->where('roles.name', $roleName)
            ->where('user_school_roles.school_id', $schoolId)
            ->where('user_school_roles.active', true)
            ->where(function($query) {
                $query->whereNull('user_school_roles.expires_at')
                      ->orWhere('user_school_roles.expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Get user's roles for a specific school
     */
    public function getSchoolRoles(int $schoolId): array
    {
        return $this->schoolRoles()
            ->join('roles', 'user_school_roles.role_id', '=', 'roles.id')
            ->where('user_school_roles.school_id', $schoolId)
            ->where('user_school_roles.active', true)
            ->where(function($query) {
                $query->whereNull('user_school_roles.expires_at')
                      ->orWhere('user_school_roles.expires_at', '>', now());
            })
            ->pluck('roles.name')
            ->toArray();
    }

    public function getSeasonRole(int $seasonId): ?string
    {
        return $this->userSeasonRoles()
            ->where('season_id', $seasonId)
            ->value('role');
    }

    public function hasSeasonRole(int $seasonId, string $role): bool
    {
        return $this->userSeasonRoles()
            ->where('season_id', $seasonId)
            ->where('role', $role)
            ->exists();
    }

    /**
     * Scope to safely load schools without column ambiguity
     */
    public function scopeWithSafeSchools($query)
    {
        return $query->with(['schools' => function ($schoolQuery) {
            $schoolQuery->select([
                'schools.id',
                'schools.name',
                'schools.slug', 
                'schools.logo'
            ])->where('schools.active', 1)
              ->whereNull('schools.deleted_at');
        }]);
    }

    public function getActivitylogOptions(): LogOptions
    {
         return LogOptions::defaults();
    }

    public function setInitialPermissionsByRole(): void
    {
        // Crear permisos para cada tabla
        $adminTables = ['clients', 'bookings', 'monitors', 'courses', 'degrees', 'evaluations', 'schools', 'stations',
            'services', 'tasks', 'seasons', 'vouchers'];
        $clientTables = ['clients', 'bookings'];
        $monitorTables = ['clients', 'bookings', 'monitors', 'courses', 'degrees', 'evaluations', 'tasks', 'vouchers'];

        // Selecciona el conjunto de tablas basado en el rol
        switch ($this->type) {
            case 1:
            case 'admin':
                $tables = $adminTables;
                break;
            case 2:
            case 'client':
                $tables = $clientTables;
                break;
            case 3:
            case 'monitor':
                $tables = $monitorTables;
                break;
            default:
                $tables = []; // o manejar como un error
        }

        foreach ($tables as $table) {
            $permissions = ["view $table", "create $table", "update $table", "delete $table"];
            $this->givePermissionTo($permissions);
        }
    }

    /**
     * Check if user has specific permission
     * Compatible with V5 multi-school/season architecture
     * 
     * @param string $permission Permission name (e.g., 'seasons.edit', 'view seasons')
     * @param int|null $schoolId School context (auto-detected from headers if null)
     * @param int|null $seasonId Season context (auto-detected from headers if null)
     * @return bool
     */
    public function hasPermission(string $permission, $schoolId = null, $seasonId = null): bool
    {
        // Obtener contexto de headers HTTP si no se proporciona
        $schoolId = $schoolId ?? request()->header('X-School-ID');
        $seasonId = $seasonId ?? request()->header('X-Season-ID');

        // Super admin (type == 1 or 'admin') tiene todos los permisos
        if ($this->type == 1 || $this->type === 'admin') {
            return true;
        }

        // Verificar acceso a la escuela primero
        if ($schoolId && !$this->hasAccessToSchool($schoolId)) {
            return false;
        }

        // Usar Spatie Permission si el permiso existe en el sistema
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        // Mapeo de permisos específicos para seasons (V5 compatible)
        $seasonPermissions = [
            'seasons.view' => $this->canViewSeasons($schoolId),
            'seasons.create' => $this->canCreateSeasons($schoolId),
            'seasons.edit' => $this->canEditSeasons($schoolId, $seasonId),
            'seasons.update' => $this->canEditSeasons($schoolId, $seasonId),
            'seasons.delete' => $this->canDeleteSeasons($schoolId),
            'seasons.activate' => $this->canActivateSeasons($schoolId),
            'seasons.close' => $this->canCloseSeasons($schoolId),
            'seasons.manage' => $this->canManageSeasons($schoolId),
            // Compatibilidad con formato Spatie
            'view seasons' => $this->canViewSeasons($schoolId),
            'create seasons' => $this->canCreateSeasons($schoolId),
            'update seasons' => $this->canEditSeasons($schoolId, $seasonId),
            'delete seasons' => $this->canDeleteSeasons($schoolId),
        ];

        // Si el permiso está mapeado, usar la lógica específica
        if (array_key_exists($permission, $seasonPermissions)) {
            return $seasonPermissions[$permission];
        }

        // Para otros permisos, usar lógica básica por rol/tipo
        return $this->hasBasicPermission($permission, $schoolId, $seasonId);
    }

    /**
     * Verificar acceso a escuela específica
     */
    protected function hasAccessToSchool($schoolId): bool
    {
        if ($this->type == 1 || $this->type === 'admin') {
            return true;
        }

        // Verificar mediante la relación schools (tabla school_users)
        return $this->schools()->where('schools.id', $schoolId)->exists();
    }

    /**
     * Permisos específicos para seasons
     */
    protected function canViewSeasons($schoolId): bool
    {
        return $this->hasAccessToSchool($schoolId);
    }

    protected function canCreateSeasons($schoolId): bool
    {
        return $this->hasAccessToSchool($schoolId) && 
               in_array($this->type, [1, 'admin', 3, 'monitor']);
    }

    protected function canEditSeasons($schoolId, $seasonId = null): bool
    {
        return $this->hasAccessToSchool($schoolId) && 
               in_array($this->type, [1, 'admin', 3, 'monitor']);
    }

    protected function canDeleteSeasons($schoolId): bool
    {
        return $this->hasAccessToSchool($schoolId) && 
               ($this->type == 1 || $this->type === 'admin');
    }

    protected function canActivateSeasons($schoolId): bool
    {
        return $this->hasAccessToSchool($schoolId) && 
               in_array($this->type, [1, 'admin', 3, 'monitor']);
    }

    protected function canCloseSeasons($schoolId): bool
    {
        return $this->hasAccessToSchool($schoolId) && 
               ($this->type == 1 || $this->type === 'admin');
    }

    protected function canManageSeasons($schoolId): bool
    {
        return $this->canEditSeasons($schoolId);
    }

    /**
     * Lógica básica de permisos para otros casos
     */
    protected function hasBasicPermission($permission, $schoolId, $seasonId): bool
    {
        // Admin tiene todo
        if ($this->type == 1 || $this->type === 'admin') {
            return true;
        }

        // Monitor tiene permisos limitados
        if ($this->type == 3 || $this->type === 'monitor') {
            return $this->hasAccessToSchool($schoolId);
        }

        // Cliente tiene permisos muy limitados
        return false;
    }
}
