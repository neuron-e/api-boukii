<?php

namespace App\Services\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class LoginService
{
    /**
     * Autenticar usuario segun tipos permitidos.
     */
    public function authenticate(Request $request, array $allowedTypes, ?School $school = null): ?array
    {
        // LOG: Inicio de autenticación
        Log::info('LOGIN_ATTEMPT_START', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'allowed_types' => $allowedTypes,
            'has_school' => $school ? true : false,
            'timestamp' => now()->toDateTimeString(),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // LOG: Email intentando autenticar
        Log::info('LOGIN_EMAIL_ATTEMPT', [
            'email' => $credentials['email'],
            'allowed_types' => $allowedTypes,
        ]);

        $usersQuery = User::query()
            ->where('email', $credentials['email'])
            ->where(function ($query) use ($allowedTypes) {
                foreach ($allowedTypes as $type) {
                    $query->orWhere('type', $type);
                }
            });

        if ($school) {
            $relations = ['schools', 'clients.schools'];
            if (Schema::hasTable('clients_utilizers')) {
                $relations[] = 'clients.utilizers';
            }
            $usersQuery->with($relations);
        }

        $users = $usersQuery->get();

        // LOG: Usuarios encontrados
        Log::info('LOGIN_USERS_FOUND', [
            'email' => $credentials['email'],
            'users_count' => $users->count(),
            'user_types' => $users->pluck('type')->toArray(),
        ]);

        if ($users->isEmpty()) {
            Log::warning('LOGIN_FAILED_NO_USER', [
                'email' => $credentials['email'],
                'allowed_types' => $allowedTypes,
            ]);
            return null;
        }

        foreach ($users as $user) {
            // LOG: Verificando contraseña
            Log::info('LOGIN_PASSWORD_CHECK', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'user_type' => $user->type,
            ]);

            if (!Hash::check($credentials['password'], $user->password)) {
                Log::warning('LOGIN_FAILED_WRONG_PASSWORD', [
                    'email' => $credentials['email'],
                    'user_id' => $user->id,
                    'user_type' => $user->type,
                ]);
                continue;
            }

            Log::info('LOGIN_PASSWORD_CORRECT', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'user_type' => $user->type,
            ]);

            switch ($user->type) {
                case 'superadmin':
                case '4':
                    Log::info('LOGIN_TOKEN_CREATING_SUPERADMIN', ['user_id' => $user->id]);
                    $token = $user->createToken('Boukii', ['permissions:all'])->plainTextToken;
                    break;
                case 'admin':
                case '1':
                    Log::info('LOGIN_TOKEN_CREATING_ADMIN', ['user_id' => $user->id]);
                    $user->load('schools');
                    $token = $user->createToken('Boukii', ['admin:all'])->plainTextToken;
                    break;
                case 'monitor':
                case '3':
                    Log::info('LOGIN_TOKEN_CREATING_MONITOR', ['user_id' => $user->id]);
                    $user->load('monitors');
                    $token = $user->createToken('Boukii', ['teach:all'])->plainTextToken;
                    break;
                case 'client':
                case '2':
                    if (!$school) {
                        Log::warning('LOGIN_FAILED_CLIENT_NO_SCHOOL', ['user_id' => $user->id]);
                        continue 2;
                    }
                    foreach ($user->clients as $client) {
                        if ($client->schools->contains('id', $school->id)) {
                            if (\Illuminate\Support\Facades\Schema::hasTable('clients_utilizers')) {
                                $user->load('clients.utilizers.sports', 'clients.sports');
                            }
                            Log::info('LOGIN_TOKEN_CREATING_CLIENT', ['user_id' => $user->id, 'school_id' => $school->id]);
                            $token = $user->createToken('Boukii', ['client:all'])->plainTextToken;
                            break 2;
                        }
                    }
                    Log::warning('LOGIN_FAILED_CLIENT_NOT_IN_SCHOOL', ['user_id' => $user->id, 'school_id' => $school ? $school->id : null]);
                    continue 2;
                default:
                    Log::warning('LOGIN_FAILED_UNKNOWN_USER_TYPE', ['user_id' => $user->id, 'user_type' => $user->type]);
                    continue 2;
            }

            Log::info('LOGIN_SUCCESS', [
                'email' => $credentials['email'],
                'user_id' => $user->id,
                'user_type' => $user->type,
                'token_prefix' => substr($token, 0, 10) . '...',
            ]);

            return [
                'token' => $token,
                'user' => $user,
            ];
        }

        Log::warning('LOGIN_FAILED_FINAL', [
            'email' => $credentials['email'],
            'users_checked' => $users->count(),
        ]);

        return null;
    }
}
