<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "🔍 CHECKING SEASON ACCESS ISSUE\n";
echo "==============================\n\n";

// Check user
$user = User::where('email', 'admin.single@boukii-v5.com')->first();
if (!$user) {
    echo "❌ User admin.single@boukii-v5.com not found\n";
    exit;
}

echo "👤 User: {$user->email} (ID: {$user->id})\n";
echo "🎭 User type: {$user->type}\n\n";

// Check seasons for school 1
echo "🏫 Seasons for School 1:\n";
$seasons = DB::table('seasons')->where('school_id', 1)->get();
foreach ($seasons as $season) {
    echo "  - Season {$season->id}: {$season->name} | Status: {$season->status} | Active: " . ($season->active ? 'YES' : 'NO') . "\n";
}

echo "\n🔐 User's school roles:\n";
$schoolRoles = $user->getSchoolRoles(1);
if (empty($schoolRoles)) {
    echo "  - ❌ No school-specific roles assigned\n";
    
    // Check if user has Spatie roles
    $spatieRoles = $user->getRoleNames();
    echo "  - Spatie roles: " . implode(', ', $spatieRoles->toArray()) . "\n";
} else {
    foreach ($schoolRoles as $role) {
        echo "  - ✅ $role\n";
    }
}

echo "\n📋 Permission checks:\n";
echo "  - Can access school 1 as admin: " . ($user->hasSchoolRole('admin', 1) ? '✅ YES' : '❌ NO') . "\n";
echo "  - Can access school 1 as superadmin: " . ($user->hasSchoolRole('superadmin', 1) ? '✅ YES' : '❌ NO') . "\n";

// Check what the middleware logic would do
$hasAdminAccess = $user->hasSchoolRole('superadmin', 1) || $user->hasSchoolRole('admin', 1);
echo "  - Middleware would allow closed season access: " . ($hasAdminAccess ? '✅ YES' : '❌ NO') . "\n";