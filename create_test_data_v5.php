<?php
/**
 * Script para crear datos de prueba para V5 Auth System
 * Preserva datos existentes y solo agrega lo necesario
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Module;
use App\Models\UserSchoolRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "🧪 CREATING V5 AUTH TEST DATA\n";
echo "=====================================\n\n";

try {
    DB::beginTransaction();
    
    // 1. Create basic modules
    echo "📦 Creating basic modules...\n";
    $modules = [
        ['slug' => 'auth', 'name' => 'Authentication', 'description' => 'User authentication and authorization', 'mandatory' => true],
        ['slug' => 'dashboard', 'name' => 'Dashboard', 'description' => 'Main dashboard and analytics', 'mandatory' => true],
        ['slug' => 'bookings', 'name' => 'Bookings', 'description' => 'Course bookings and reservations', 'mandatory' => false],
        ['slug' => 'courses', 'name' => 'Courses', 'description' => 'Course management', 'mandatory' => false],
        ['slug' => 'clients', 'name' => 'Clients', 'description' => 'Client management', 'mandatory' => false],
    ];
    
    foreach ($modules as $moduleData) {
        Module::firstOrCreate(['slug' => $moduleData['slug']], $moduleData);
        echo "  ✅ Module: {$moduleData['name']}\n";
    }
    
    // 2. Create basic roles
    echo "\n👥 Creating basic roles...\n";
    $roles = [
        ['slug' => 'admin', 'name' => 'Administrator', 'description' => 'Full system access'],
        ['slug' => 'manager', 'name' => 'Manager', 'description' => 'School management access'],
        ['slug' => 'instructor', 'name' => 'Instructor', 'description' => 'Teaching and course access'],
        ['slug' => 'client', 'name' => 'Client', 'description' => 'Basic client access'],
    ];
    
    foreach ($roles as $roleData) {
        Role::firstOrCreate(['slug' => $roleData['slug']], $roleData);
        echo "  ✅ Role: {$roleData['name']}\n";
    }
    
    // 3. Create basic permissions
    echo "\n🔑 Creating basic permissions...\n";
    $permissions = [
        ['slug' => 'auth.login', 'name' => 'Login', 'description' => 'Can login to system', 'module' => 'auth'],
        ['slug' => 'dashboard.view', 'name' => 'View Dashboard', 'description' => 'Can view dashboard', 'module' => 'dashboard'],
        ['slug' => 'bookings.view', 'name' => 'View Bookings', 'description' => 'Can view bookings', 'module' => 'bookings'],
        ['slug' => 'bookings.create', 'name' => 'Create Bookings', 'description' => 'Can create bookings', 'module' => 'bookings'],
        ['slug' => 'courses.view', 'name' => 'View Courses', 'description' => 'Can view courses', 'module' => 'courses'],
        ['slug' => 'courses.manage', 'name' => 'Manage Courses', 'description' => 'Can manage courses', 'module' => 'courses'],
    ];
    
    foreach ($permissions as $permissionData) {
        Permission::firstOrCreate(['slug' => $permissionData['slug']], $permissionData);
        echo "  ✅ Permission: {$permissionData['name']}\n";
    }
    
    // 4. Create test user
    echo "\n👤 Creating test user...\n";
    $testUser = User::firstOrCreate(
        ['email' => 'admin.test@boukii.com'],
        [
            'username' => 'admin_test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'password' => Hash::make('password123'),
            'type' => 1, // admin type
            'active' => true
        ]
    );
    
    echo "  ✅ Test User: {$testUser->email} (ID: {$testUser->id})\n";
    
    // 5. Get existing schools
    echo "\n🏫 Finding existing schools...\n";
    $schools = DB::table('schools')->where('active', 1)->limit(3)->get();
    
    if ($schools->count() > 0) {
        foreach ($schools as $school) {
            echo "  ✅ Found School: {$school->name} (ID: {$school->id})\n";
            
            // Assign admin role to test user for this school
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                UserSchoolRole::firstOrCreate([
                    'user_id' => $testUser->id,
                    'school_id' => $school->id,
                    'role_id' => $adminRole->id,
                ]);
                echo "    ✅ Assigned admin role to test user for school {$school->name}\n";
            }
        }
    } else {
        echo "  ⚠️ No schools found - you may need to create schools manually\n";
    }
    
    DB::commit();
    
    echo "\n🎉 TEST DATA CREATION COMPLETED!\n";
    echo "=====================================\n";
    echo "✅ Test User Created: admin.test@boukii.com\n";
    echo "✅ Password: password123\n";
    echo "✅ Type: Admin (1)\n";
    echo "✅ Schools Assigned: " . $schools->count() . "\n";
    echo "✅ Roles & Permissions: Created\n";
    echo "✅ Modules: Created\n\n";
    
    echo "🚀 READY FOR TESTING!\n";
    echo "You can now test the V5 auth system with:\n";
    echo "  Email: admin.test@boukii.com\n";
    echo "  Password: password123\n\n";
    
} catch (Exception $e) {
    DB::rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}