<?php

// Quick Production Database Sync Script
// Run this via: php artisan tinker --execute="require 'sync-production-db.php';"

echo "🔄 Syncing Production Database...\n";

// 1. Ensure admin user exists with proper role
$adminUser = App\Models\User::firstOrCreate(
    ['email' => 'admin@edmondserenity.com'],
    [
        'name' => 'Admin User',
        'email' => 'admin@edmondserenity.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_active' => true,
    ]
);

echo "✅ Admin user ensured: {$adminUser->email}\n";

// 2. Ensure administrator role exists
$adminRole = App\Models\Role::firstOrCreate([
    'name' => 'administrator',
    'guard_name' => 'web',
]);

echo "✅ Administrator role ensured\n";

// 3. Assign admin user to administrator role
$adminUser->assignRole('administrator');
echo "✅ Admin user assigned to administrator role\n";

// 4. Ensure all permissions exist and are assigned to administrator role
$permissions = [
    'view_admin_panel', 'view_dashboard', 'view_users', 'create_users', 'edit_users', 'delete_users',
    'view_own_profile', 'edit_own_profile', 'view_residents', 'create_residents', 'edit_residents', 'delete_residents',
    'view_medications', 'create_medications', 'edit_medications', 'delete_medications',
    'view_appointments', 'create_appointments', 'edit_appointments', 'delete_appointments',
    'view_vital_signs', 'create_vital_signs', 'edit_vital_signs', 'delete_vital_signs',
    'view_reports', 'export_reports'
];

foreach ($permissions as $permissionName) {
    $permission = App\Models\Permission::firstOrCreate([
        'name' => $permissionName,
        'guard_name' => 'web',
    ]);
}

// Assign all permissions to administrator role
$allPermissions = App\Models\Permission::all();
$adminRole->permissions()->sync($allPermissions->pluck('id'));

echo "✅ All permissions assigned to administrator role\n";

// 5. Verify the setup
echo "\n🔍 Verification:\n";
echo "Admin user roles: " . $adminUser->roles->pluck('name')->toJson() . "\n";
echo "Admin user has view_users permission: " . ($adminUser->hasPermission('view_users') ? 'YES' : 'NO') . "\n";
echo "Total permissions: " . App\Models\Permission::count() . "\n";

echo "\n🎉 Production database sync completed!\n";
echo "Your admin user should now have full access to all features.\n";
