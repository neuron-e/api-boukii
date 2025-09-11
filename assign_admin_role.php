<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "🔧 ASSIGNING ADMIN ROLE TO USER\n";
echo "===============================\n\n";

// Find user
$user = User::where('email', 'admin.single@boukii-v5.com')->first();
if (!$user) {
    echo "❌ User admin.single@boukii-v5.com not found\n";
    exit;
}

echo "👤 User: {$user->email} (ID: {$user->id})\n";

// Assign Spatie role if not assigned
if (!$user->hasRole('admin')) {
    $user->assignRole('admin');
    echo "✅ Assigned global Spatie 'admin' role\n";
} else {
    echo "✅ User already has global Spatie 'admin' role\n";
}

// Check school assignment
$schoolId = 1;
$adminRoleId = 2; // admin role from Spatie

$existing = DB::table('user_school_roles')
    ->where('user_id', $user->id)
    ->where('school_id', $schoolId)
    ->where('role_id', $adminRoleId)
    ->first();

if ($existing) {
    echo "✅ School-specific admin role already assigned\n";
} else {
    // Create new assignment
    $assignmentId = DB::table('user_school_roles')->insertGetId([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'role_id' => $adminRoleId,
        'active' => true,
        'assigned_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✅ School-specific admin role assigned (ID: $assignmentId)\n";
}

echo "\n📋 Verification:\n";
echo "  - Can access school 1 as admin: " . ($user->hasSchoolRole('admin', 1) ? '✅ YES' : '❌ NO') . "\n";

// Check seasons
echo "\n🏫 Active seasons in school 1:\n";
$seasons = DB::table('seasons')->where('school_id', 1)->get();
foreach ($seasons as $season) {
    $status = $season->is_active ? '✅ Active' : '⚠️ Inactive';
    echo "  - Season {$season->id}: {$season->name} | $status\n";
}