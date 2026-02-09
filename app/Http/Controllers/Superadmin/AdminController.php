<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\School;
use App\Models\SchoolUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['schools', 'roles'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $type = (string) $request->get('type');
            if (in_array($type, ['superadmin', '4'], true)) {
                $query->whereIn('type', ['superadmin', 4]);
            } elseif (in_array($type, ['admin', '1'], true)) {
                $query->whereIn('type', ['admin', 1]);
            }
        } else {
            $query->whereIn('type', ['admin', 1]);
        }

        if ($request->filled('school_id')) {
            $query->whereHas('schoolUsers', function ($q) use ($request) {
                $q->where('school_id', $request->school_id);
            });
        }

        $admins = $query->get()->map(function (User $user) {
            $user->role_names = $user->roles->pluck('name')->implode(', ');
            return $user;
        });

        return $this->sendResponse($admins, 'Admins retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'school_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);

        $type = $data['type'] ?? 'admin';
        $isSuperadmin = in_array((string) $type, ['superadmin', '4'], true);

        $school = null;
        if (!$isSuperadmin) {
            if (empty($data['school_id'])) {
                return $this->sendError('School is required', 422);
            }
            $school = School::find($data['school_id']);
            if (!$school) {
                return $this->sendError('School not found', 404);
            }
        }

        $existing = User::where('email', $data['email'])->first();
        if ($existing) {
            return $this->sendError('User already registered', 422);
        }

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'username' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => $isSuperadmin ? 'superadmin' : 1,
            'active' => 1,
        ]);

        if ($school) {
            SchoolUser::create([
                'user_id' => $user->id,
                'school_id' => $school->id,
            ]);
        }

        if (!empty($data['role'])) {
            $role = Role::where('name', $data['role'])->first();
            if ($role) {
                $user->assignRole($role);
            }
        }

        return $this->sendResponse($user->load('schools', 'roles'), 'Admin created successfully');
    }

    public function update($id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'role' => ['nullable', 'string'],
            'school_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string'],
        ]);

        /** @var User|null $user */
        $user = $this->resolveAdminUser($id, $request);
        if (!$user) {
            return $this->sendError('Admin not found', 404);
        }

        if (!empty($data['email']) && $data['email'] !== $user->email) {
            $exists = User::where('email', $data['email'])->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return $this->sendError('Email already in use', 422);
            }
        }

        $user->fill(collect($data)->only(['first_name', 'last_name', 'email', 'active'])->toArray());
        $user->save();

        if (!empty($data['school_id'])) {
            $school = School::find($data['school_id']);
            if (!$school) {
                return $this->sendError('School not found', 404);
            }
            SchoolUser::firstOrCreate([
                'user_id' => $user->id,
                'school_id' => $school->id,
            ]);
        }

        if (array_key_exists('role', $data)) {
            if (!empty($data['role'])) {
                $role = Role::where('name', $data['role'])->first();
                if ($role) {
                    $user->syncRoles([$role]);
                }
            } else {
                $user->syncRoles([]);
            }
        }

        return $this->sendResponse($user->load('schools'), 'Admin updated successfully');
    }

    public function resetPassword($id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
            'type' => ['nullable', 'string'],
        ]);

        /** @var User|null $user */
        $user = $this->resolveAdminUser($id, $request);
        if (!$user) {
            return $this->sendError('Admin not found', 404);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return $this->sendSuccess('Password updated successfully');
    }

    public function destroy($id, Request $request): JsonResponse
    {
        $schoolId = $request->get('school_id');

        /** @var User|null $user */
        $user = $this->resolveAdminUser($id, $request);
        if (!$user) {
            return $this->sendError('Admin not found', 404);
        }

        if ($schoolId) {
            SchoolUser::where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->delete();
            return $this->sendSuccess('Admin removed from school');
        }

        $user->delete();
        return $this->sendSuccess('Admin deleted');
    }

    private function resolveAdminUser($id, Request $request): ?User
    {
        $type = (string) $request->get('type');
        $adminTypes = ['admin', '1', 1];
        $superTypes = ['superadmin', '4', 4];

        if (in_array($type, $adminTypes, true)) {
            return User::whereIn('type', ['admin', 1])->find($id);
        }

        if (in_array($type, $superTypes, true)) {
            return User::whereIn('type', ['superadmin', 4])->find($id);
        }

        return User::whereIn('type', ['admin', 1, 'superadmin', 4])->find($id);
    }
}
