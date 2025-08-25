<?php

namespace App\Services\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class LoginService
{
    /**
     * Autenticar usuario segun tipos permitidos.
     */
    public function authenticate(Request $request, array $allowedTypes, ?School $school = null): ?array
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
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

        foreach ($users as $user) {
            if (!Hash::check($credentials['password'], $user->password)) {
                continue;
            }

            switch ($user->type) {
                case 'superadmin':
                case '4':
                    $schools = School::where('active', 1)->get();
                    $user->setRelation('schools', $schools);
                    $token = $user->createToken('Boukii', ['permissions:all'])->plainTextToken;
                    break;
                case 'admin':
                case '1':
                    $user->load('schools');
                    $token = $user->createToken('Boukii', ['admin:all'])->plainTextToken;
                    break;
                case 'monitor':
                case '3':
                    $user->load('monitors');
                    $token = $user->createToken('Boukii', ['teach:all'])->plainTextToken;
                    break;
                case 'client':
                case '2':
                    if (!$school) {
                        continue 2;
                    }
                    foreach ($user->clients as $client) {
                        if ($client->schools->contains('id', $school->id)) {
                            if (\Illuminate\Support\Facades\Schema::hasTable('clients_utilizers')) {
                                $user->load('clients.utilizers.sports', 'clients.sports');
                            }
                            $token = $user->createToken('Boukii', ['client:all'])->plainTextToken;
                            break 2;
                        }
                    }
                    continue 2;
                default:
                    continue 2;
            }

            return [
                'token' => $token,
                'user' => $user,
            ];
        }

        return null;
    }
}
