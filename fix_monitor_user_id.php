<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FIXING MONITOR USER_ID MAPPINGS ===\n";
echo "Analyzing and fixing monitor user_id relationships\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('boukii_pro');

$sourceSchoolId = 13;
$targetSchoolId = 15;

echo "1. ANALYZING MONITOR USER_ID IN DEV\n";
echo str_repeat("-", 50) . "\n";

// Get all monitors from school 13 in DEV
$devMonitors = $devConnection->table('monitors')
    ->whereIn('id', $devConnection->table('monitors_schools')
        ->where('school_id', $sourceSchoolId)
        ->pluck('monitor_id'))
    ->get();

$monitorsWithUserId = $devMonitors->whereNotNull('user_id');
$monitorsWithoutUserId = $devMonitors->whereNull('user_id');

echo "Total monitors in DEV school 13: " . $devMonitors->count() . "\n";
echo "Monitors with user_id: " . $monitorsWithUserId->count() . "\n";  
echo "Monitors without user_id: " . $monitorsWithoutUserId->count() . "\n";

echo "\nSample monitors with user_id in DEV:\n";
foreach ($monitorsWithUserId->take(5) as $monitor) {
    echo "  - {$monitor->first_name} {$monitor->last_name} (monitor_id: {$monitor->id}, user_id: {$monitor->user_id})\n";
}

echo "\n2. ANALYZING MONITOR USER_ID IN PROD\n";
echo str_repeat("-", 50) . "\n";

// Get corresponding monitors in PROD
$prodMonitors = $prodConnection->table('monitors')
    ->whereIn('id', $prodConnection->table('monitors_schools')
        ->where('school_id', $targetSchoolId)
        ->pluck('monitor_id'))
    ->get();

$prodMonitorsWithUserId = $prodMonitors->whereNotNull('user_id');
$prodMonitorsWithoutUserId = $prodMonitors->whereNull('user_id');

echo "Total monitors in PROD school 15: " . $prodMonitors->count() . "\n";
echo "Monitors with user_id: " . $prodMonitorsWithUserId->count() . "\n";
echo "Monitors without user_id: " . $prodMonitorsWithoutUserId->count() . "\n";

echo "\n3. CREATING USER MAPPING\n";  
echo str_repeat("-", 50) . "\n";

// Create user mapping based on email
$userMapping = [];
$prodUsers = $prodConnection->table('users')->get();

foreach ($prodUsers as $prodUser) {
    $devUser = $devConnection->table('users')->where('email', $prodUser->email)->first();
    if ($devUser) {
        $userMapping[$devUser->id] = $prodUser->id;
    }
}

echo "Created user mapping for " . count($userMapping) . " users\n";

echo "\n4. IDENTIFYING MONITORS THAT NEED USER_ID FIXES\n";
echo str_repeat("-", 50) . "\n";

$monitorsToFix = [];

foreach ($monitorsWithUserId as $devMonitor) {
    // Find corresponding monitor in PROD by email
    $prodMonitor = $prodMonitors->firstWhere('email', $devMonitor->email);
    
    if ($prodMonitor) {
        // Check if user_id is missing or incorrect
        $expectedUserId = isset($userMapping[$devMonitor->user_id]) ? $userMapping[$devMonitor->user_id] : null;
        
        if ($prodMonitor->user_id != $expectedUserId) {
            $monitorsToFix[] = [
                'prod_monitor_id' => $prodMonitor->id,
                'monitor_name' => $devMonitor->first_name . ' ' . $devMonitor->last_name,
                'monitor_email' => $devMonitor->email,
                'current_user_id' => $prodMonitor->user_id,
                'expected_user_id' => $expectedUserId,
                'dev_user_id' => $devMonitor->user_id
            ];
        }
    }
}

echo "Found " . count($monitorsToFix) . " monitors that need user_id fixes\n";

if (count($monitorsToFix) > 0) {
    echo "\nMonitors that need fixes:\n";
    foreach ($monitorsToFix as $fix) {
        echo "  - {$fix['monitor_name']} ({$fix['monitor_email']})\n";
        echo "    PROD Monitor ID: {$fix['prod_monitor_id']}\n";
        echo "    Current user_id: " . ($fix['current_user_id'] ?: 'NULL') . "\n";
        echo "    Should be user_id: " . ($fix['expected_user_id'] ?: 'NULL') . " (DEV user_id: {$fix['dev_user_id']})\n";
        echo "    ---\n";
    }
}

echo "\n5. FIXING MONITOR USER_ID VALUES\n";
echo str_repeat("-", 50) . "\n";

$fixedCount = 0;

foreach ($monitorsToFix as $fix) {
    try {
        $prodConnection->table('monitors')
            ->where('id', $fix['prod_monitor_id'])
            ->update(['user_id' => $fix['expected_user_id']]);
        
        echo "  ✓ Fixed {$fix['monitor_name']} - set user_id to " . ($fix['expected_user_id'] ?: 'NULL') . "\n";
        $fixedCount++;
        
    } catch (Exception $e) {
        echo "  ❌ Error fixing {$fix['monitor_name']}: " . $e->getMessage() . "\n";
    }
}

echo "\n6. FINAL VERIFICATION\n";
echo str_repeat("-", 50) . "\n";

// Re-check monitors in PROD
$finalProdMonitors = $prodConnection->table('monitors')
    ->whereIn('id', $prodConnection->table('monitors_schools')
        ->where('school_id', $targetSchoolId)
        ->pluck('monitor_id'))
    ->get();

$finalWithUserId = $finalProdMonitors->whereNotNull('user_id');
$finalWithoutUserId = $finalProdMonitors->whereNull('user_id');

echo "FINAL RESULTS:\n";
echo "DEV monitors with user_id: " . $monitorsWithUserId->count() . "\n";
echo "PROD monitors with user_id: " . $finalWithUserId->count() . "\n";
echo "PROD monitors without user_id: " . $finalWithoutUserId->count() . "\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "MONITOR USER_ID FIX COMPLETE\n";
echo str_repeat("=", 80) . "\n";
echo "Monitors fixed: {$fixedCount}\n";
echo "End time: " . date('Y-m-d H:i:s') . "\n";

if ($finalWithUserId->count() >= $monitorsWithUserId->count()) {
    echo "\n✅ SUCCESS: Monitor user_id mappings are now correct!\n";
} else {
    echo "\n⚠️ Some monitors may still be missing user_id mappings\n";
    
    echo "\nRemaining monitors without user_id in PROD:\n";
    foreach ($finalWithoutUserId->take(10) as $monitor) {
        echo "  - {$monitor->first_name} {$monitor->last_name} ({$monitor->email})\n";
    }
}

echo "\n=== USER_ID FIX COMPLETE ===\n";