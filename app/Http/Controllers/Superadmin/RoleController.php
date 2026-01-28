<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends AppBaseController
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        return $this->sendResponse($roles, 'Roles retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        /** @var Role $role */
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web'
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $this->sendResponse($role->load('permissions'), 'Role created successfully');
    }

    public function update($id, Request $request): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return $this->sendError('Role not found', 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        if (isset($data['name'])) {
            $role->name = $data['name'];
        }
        $role->save();

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return $this->sendResponse($role->load('permissions'), 'Role updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->sendError('Role not found', 404);
        }

        $role->delete();

        return $this->sendSuccess('Role deleted successfully');
    }
}
