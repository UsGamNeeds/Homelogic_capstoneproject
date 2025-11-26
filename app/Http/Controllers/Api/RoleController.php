<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions');
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }
        $roles = $query->orderBy('name')->paginate($request->get('per_page', 20));
        return response()->json($roles);
    }

    public function permissions(): JsonResponse
    {
        return response()->json(Permission::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
        ]);
        $role = Role::create(['name' => $validated['name']]);
        if (!empty($validated['permissions'])) {
            $role->permissions()->sync(Permission::whereIn('name', $validated['permissions'])->pluck('id'));
        }
        return response()->json($role->load('permissions'), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
        ]);
        if (isset($validated['name'])) {
            $role->name = $validated['name'];
            $role->save();
        }
        if (isset($validated['permissions'])) {
            $role->permissions()->sync(Permission::whereIn('name', $validated['permissions'])->pluck('id'));
        }
        return response()->json($role->load('permissions'));
    }

    public function destroy($id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $role->permissions()->detach();
        $role->delete();
        return response()->json(['message' => 'Role deleted']);
    }

    /**
     * Ensure required roles (administrator, admin, caregiver) exist
     */
    public function ensureRolesExist(): JsonResponse
    {
        try {
            // Create administrator role if it doesn't exist
            $administratorRole = Role::firstOrCreate(
                ['name' => 'administrator'],
                ['guard_name' => 'web']
            );

            // Create admin role (alias) if it doesn't exist
            $adminRole = Role::firstOrCreate(
                ['name' => 'admin'],
                ['guard_name' => 'web']
            );

            // Create caregiver role if it doesn't exist
            $caregiverRole = Role::firstOrCreate(
                ['name' => 'caregiver'],
                ['guard_name' => 'web']
            );

            // Get all permissions
            $permissions = Permission::all();

            // Sync all permissions to administrator role
            if ($permissions->count() > 0) {
                $administratorRole->permissions()->sync($permissions->pluck('id'));
                $adminRole->permissions()->sync($permissions->pluck('id'));
            }

            // Sync specific permissions to caregiver role
            $caregiverPermissions = Permission::whereIn('name', [
                'view_admin_panel',
                'view_dashboard',
                'view_own_profile',
                'edit_own_profile',
                'view_residents',
                'view_medications',
                'view_appointments',
                'view_assessments',
                'view_vital_signs',
                'create_vital_signs',
                'view_assignments',
                'create_leave_requests',
                'view_leave_requests',
                'view_incidents',
                'create_incidents',
                'view_behaviors',
                'create_behaviors',
                'view_sleep_records',
                'create_sleep_records',
            ])->pluck('id');

            if ($caregiverPermissions->count() > 0) {
                $caregiverRole->permissions()->sync($caregiverPermissions);
            }

            return $this->success([
                'message' => 'Required roles ensured successfully',
                'roles' => [
                    'administrator' => $administratorRole->wasRecentlyCreated,
                    'admin' => $adminRole->wasRecentlyCreated,
                    'caregiver' => $caregiverRole->wasRecentlyCreated,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error ensuring roles exist: ' . $e->getMessage());
            return $this->error('Failed to ensure roles exist: ' . $e->getMessage(), 500);
        }
    }
}


