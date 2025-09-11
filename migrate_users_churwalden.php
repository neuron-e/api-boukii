<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== MIGRATING USERS FOR CHURWALDEN ===\n";
echo "Source: DEV School 13 (SSS Churwalden)\n";
echo "Target: PROD School 15\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('boukii_pro');

$sourceSchoolId = 13;
$targetSchoolId = 15;
$totalMigrated = 0;

echo "1. GETTING SCHOOL USERS\n";
echo str_repeat("-", 50) . "\n";

// Get user IDs associated with school 13
$schoolUserIds = $devConnection->table('school_users')
    ->where('school_id', $sourceSchoolId)
    ->pluck('user_id')
    ->toArray();

echo "Found " . count($schoolUserIds) . " users associated with school 13\n";
print_r($schoolUserIds);

// Get user details
$users = $devConnection->table('users')
    ->whereIn('id', $schoolUserIds)
    ->get();

echo "\n2. USER DETAILS FROM DEV:\n";
echo str_repeat("-", 50) . "\n";

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Email: {$user->email}\n";
    echo "Name: {$user->first_name} {$user->last_name}\n";
    echo "Active: {$user->active}\n";
    echo "Created: {$user->created_at}\n";
    echo "---\n";
}

echo "\n3. MIGRATING USERS TO PROD\n";
echo str_repeat("-", 50) . "\n";

$userMapping = [];

foreach ($users as $user) {
    // Check if user already exists in PROD by email
    $existingUser = $prodConnection->table('users')
        ->where('email', $user->email)
        ->first();
    
    if ($existingUser) {
        $userMapping[$user->id] = $existingUser->id;
        echo "  ✓ User {$user->first_name} {$user->last_name} already exists (ID: {$existingUser->id})\n";
    } else {
        try {
            $newId = $prodConnection->table('users')->insertGetId([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'birth_date' => $user->birth_date ?? '1990-01-01',
                'phone' => $user->phone,
                'address' => $user->address,
                'cp' => $user->cp,
                'city' => $user->city,
                'country' => $user->country,
                'province' => $user->province,
                'language1_id' => $user->language1_id,
                'active' => $user->active,
                'deleted_at' => $user->deleted_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]);
            $userMapping[$user->id] = $newId;
            echo "  ✓ Migrated user {$user->first_name} {$user->last_name} (new ID: {$newId})\n";
            $totalMigrated++;
        } catch (Exception $e) {
            echo "  ❌ Error migrating user {$user->first_name} {$user->last_name}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n4. UPDATING SCHOOL_USERS RELATIONSHIPS\n";
echo str_repeat("-", 50) . "\n";

// Update or create school_users relationships
foreach ($schoolUserIds as $oldUserId) {
    if (isset($userMapping[$oldUserId])) {
        $newUserId = $userMapping[$oldUserId];
        
        // Check if relationship already exists
        $exists = $prodConnection->table('school_users')
            ->where('user_id', $newUserId)
            ->where('school_id', $targetSchoolId)
            ->exists();
        
        if (!$exists) {
            try {
                $prodConnection->table('school_users')->insert([
                    'user_id' => $newUserId,
                    'school_id' => $targetSchoolId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                echo "  ✓ Created school-user relationship for user ID {$newUserId}\n";
                $totalMigrated++;
            } catch (Exception $e) {
                echo "  ❌ Error creating school-user relationship: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  ✓ School-user relationship already exists for user ID {$newUserId}\n";
        }
    }
}

echo "\n5. VERIFICATION\n";
echo str_repeat("-", 50) . "\n";

$finalUserCount = $prodConnection->table('school_users')
    ->where('school_id', $targetSchoolId)
    ->count();

echo "School 15 now has {$finalUserCount} associated users\n";

// Show user details
$finalUsers = $prodConnection->table('users')
    ->join('school_users', 'users.id', '=', 'school_users.user_id')
    ->where('school_users.school_id', $targetSchoolId)
    ->select('users.id', 'users.email', 'users.first_name', 'users.last_name')
    ->get();

echo "\nUsers associated with School 15:\n";
foreach ($finalUsers as $user) {
    echo "  - {$user->first_name} {$user->last_name} ({$user->email}) [ID: {$user->id}]\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "USER MIGRATION COMPLETE\n";
echo str_repeat("=", 80) . "\n";
echo "Users migrated: {$totalMigrated}\n";
echo "End time: " . date('Y-m-d H:i:s') . "\n";
echo "\n✅ All users for SSS Churwalden have been migrated!\n";