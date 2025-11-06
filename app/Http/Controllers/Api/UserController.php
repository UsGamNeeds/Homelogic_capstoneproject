<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['assignedBranch', 'roles']);

        // Filter by active status
        if ($request->has('active_only') && $request->get('active_only') === 'true') {
            $query->where('is_active', true);
        }

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('assigned_branch_id', $request->get('branch_id'));
        }

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->get('role'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate($request->get('per_page', 20));

        return response()->json($users);
    }

    public function show($id): JsonResponse
    {
        $user = User::with(['assignedBranch', 'roles', 'roles.permissions'])
            ->findOrFail($id);

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:255',
            'middle_names' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:' . now()->subYears(18)->format('Y-m-d'),
            'marital_status' => 'nullable|string|max:50',
            'sex' => 'required|string|in:male,female,other',
            'position' => 'nullable|string|max:255',
            'credentials' => 'nullable|string|max:255',
            'credential_details' => 'nullable|string',
            'date_employed' => 'required|date|before_or_equal:today',
            'hire_date' => 'nullable|date',
            'supervisor_name' => 'nullable|string|max:255',
            'provider_name' => 'nullable|string|max:255',
            'role' => 'required|string|max:255',
            'assigned_branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:5120',
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        // Convert empty string to null for nullable fields
        if (array_key_exists('assigned_branch_id', $validated)) {
            $validated['assigned_branch_id'] = $validated['assigned_branch_id'] ?: null;
        }
        
        // Remove position if column doesn't exist in database or if it's empty
        if (!Schema::hasColumn('users', 'position')) {
            unset($validated['position']);
        } elseif (array_key_exists('position', $validated) && empty($validated['position'])) {
            unset($validated['position']);
        }

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('profile-images', $fileName, 'public');
            $validated['profile_image'] = $filePath;
        }

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        // Extract role_ids if provided
        $roleIds = $validated['role_ids'] ?? null;
        unset($validated['role_ids']);

        $user = User::create($validated);

        // Assign roles if provided
        if ($roleIds) {
            $user->roles()->sync($roleIds);
        }

        return response()->json($user->load(['assignedBranch', 'roles']), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Convert empty strings to null for date fields before validation
        $input = $request->all();
        foreach (['date_of_birth', 'date_employed', 'hire_date'] as $dateField) {
            if (isset($input[$dateField]) && $input[$dateField] === '') {
                $input[$dateField] = null;
            }
        }
        
        // Replace request data with cleaned input for validation
        $request->merge($input);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'first_name' => 'sometimes|required|string|max:255',
            'middle_names' => 'nullable|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone_number' => 'sometimes|required|string|max:50',
            'date_of_birth' => 'nullable|date|before:' . now()->subYears(18)->format('Y-m-d'),
            'marital_status' => 'nullable|string|max:50',
            'sex' => 'sometimes|required|string|in:male,female,other',
            'position' => 'nullable|string|max:255',
            'credentials' => 'nullable|string|max:255',
            'credential_details' => 'nullable|string',
            'date_employed' => 'nullable|date|before_or_equal:today',
            'hire_date' => 'nullable|date',
            'supervisor_name' => 'nullable|string|max:255',
            'provider_name' => 'nullable|string|max:255',
            'role' => 'sometimes|required|string|max:255',
            'assigned_branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,png,gif|max:5120',
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        // Convert empty string to null for nullable fields
        if (array_key_exists('assigned_branch_id', $validated)) {
            $validated['assigned_branch_id'] = $validated['assigned_branch_id'] ?: null;
        }
        
        // Remove position if column doesn't exist in database or if it's empty
        if (!Schema::hasColumn('users', 'position')) {
            unset($validated['position']);
        } elseif (array_key_exists('position', $validated) && empty($validated['position'])) {
            unset($validated['position']);
        }

        // Handle profile image upload if provided
        if ($request->hasFile('profile_image')) {
            // Delete old profile image if exists
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $file = $request->file('profile_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('profile-images', $fileName, 'public');
            $validated['profile_image'] = $filePath;
        }

        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Extract role_ids if provided
        $roleIds = $validated['role_ids'] ?? null;
        if (isset($validated['role_ids'])) {
            unset($validated['role_ids']);
        }

        $user->update($validated);

        // Update roles if provided
        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        return response()->json($user->load(['assignedBranch', 'roles']));
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account.'
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}

