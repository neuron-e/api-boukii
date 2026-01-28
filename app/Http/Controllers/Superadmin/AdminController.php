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
        $query = User::with('schools')
            ->where('type', 1)
            ->orderBy('created_at', 'desc');

        if ($request->filled('school_id')) {
            $query->whereHas('schoolUsers', function ($q) use ($request) {
                $q->where('school_id', $request->school_id);
            });
        }

        return $this->sendResponse($query->get(), 'Admins retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'school_id' => ['required', 'integer'],
            'role' => ['nullable', 'string'],
        ]);

        $school = School::find($data['school_id']);
        if (!$school) {
            return $this->sendError('School not found', 404);
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
            'type' => 1,
            'active' => 1,
        ]);

        SchoolUser::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
        ]);

        if (!empty($data['role'])) {
            $role = Role::where('name', $data['role'])->first();
            if ($role) {
                $user->assignRole($role);
            }
        }

        return $this->sendResponse($user->load('schools'), 'Admin created successfully');
    }
}
