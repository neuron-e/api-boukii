<?php
/**
 * Create a simple test user for V5 auth testing
 * Works with existing database structure
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "🧪 CREATING SIMPLE TEST USER FOR V5\n";
echo "=====================================\n\n";

try {
    DB::beginTransaction();
    
    // Check if test user already exists
    $existingUser = DB::table('users')->where('email', 'admin.test@boukii.com')->first();
    
    if ($existingUser) {
        echo "👤 Test user already exists (ID: {$existingUser->id})\n";
        echo "📧 Email: {$existingUser->email}\n";
        echo "🔄 Updating password to 'password123'...\n";
        
        DB::table('users')
            ->where('id', $existingUser->id)
            ->update([
                'password' => Hash::make('password123'),
                'active' => 1,
                'type' => '1', // admin type
                'updated_at' => now()
            ]);
        
        $testUserId = $existingUser->id;
        echo "  ✅ Password updated!\n";
    } else {
        echo "👤 Creating new test user...\n";
        
        $testUserId = DB::table('users')->insertGetId([
            'username' => 'admin_test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin.test@boukii.com',
            'password' => Hash::make('password123'),
            'type' => '1', // admin type - string as per existing data
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        echo "  ✅ User created with ID: {$testUserId}\n";
    }
    
    // Get active schools
    $activeSchools = DB::table('schools')->where('active', 1)->limit(3)->get();
    echo "\n🏫 Assigning user to active schools...\n";
    
    foreach ($activeSchools as $school) {
        // Check if user is already assigned to this school
        $existingAssignment = DB::table('school_users')
            ->where('user_id', $testUserId)
            ->where('school_id', $school->id)
            ->first();
        
        if (!$existingAssignment) {
            DB::table('school_users')->insert([
                'user_id' => $testUserId,
                'school_id' => $school->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  ✅ Assigned to school: {$school->name} (ID: {$school->id})\n";
        } else {
            echo "  ✅ Already assigned to school: {$school->name} (ID: {$school->id})\n";
        }
    }
    
    // Get seasons for first school
    if ($activeSchools->count() > 0) {
        $firstSchool = $activeSchools->first();
        $seasons = DB::table('seasons')
            ->where('school_id', $firstSchool->id)
            ->where('active', 1)
            ->get();
        
        echo "\n📅 Available seasons for {$firstSchool->name}:\n";
        foreach ($seasons as $season) {
            echo "  ✅ Season: {$season->name} (ID: {$season->id})\n";
        }
    }
    
    DB::commit();
    
    echo "\n🎉 TEST USER SETUP COMPLETED!\n";
    echo "=====================================\n";
    echo "✅ Email: admin.test@boukii.com\n";
    echo "✅ Password: password123\n";
    echo "✅ Type: Admin (1)\n";
    echo "✅ Active: Yes\n";
    echo "✅ Schools assigned: " . $activeSchools->count() . "\n\n";
    
    echo "🚀 READY FOR V5 AUTH TESTING!\n\n";
    
} catch (Exception $e) {
    DB::rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}