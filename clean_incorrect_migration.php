<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CLEANING INCORRECT MIGRATION (School 15) ===" . PHP_EOL;

$prodConnection = DB::connection('mysql');

// Get all data that was incorrectly migrated to school 15
echo "\n1. ANALYZING CURRENT SCHOOL 15 DATA TO REMOVE" . PHP_EOL;
echo str_repeat("-", 50) . PHP_EOL;

$dataToRemove = [
    'bookings' => $prodConnection->table('bookings')->where('school_id', 15)->count(),
    'booking_users' => $prodConnection->table('booking_users')->where('school_id', 15)->count(),
    'payments' => $prodConnection->table('payments')->where('school_id', 15)->count(),
    'degrees' => $prodConnection->table('degrees')->where('school_id', 15)->count(),
    'courses' => $prodConnection->table('courses')->where('school_id', 15)->count(),
    'monitors_schools' => $prodConnection->table('monitors_schools')->where('school_id', 15)->count(),
    'monitor_sports_degrees' => $prodConnection->table('monitor_sports_degrees')->where('school_id', 15)->count(),
    'clients_schools' => $prodConnection->table('clients_schools')->where('school_id', 15)->count(),
    'clients_sports' => $prodConnection->table('clients_sports')->where('school_id', 15)->count(),
    'client_observations' => $prodConnection->table('client_observations')->where('school_id', 15)->count(),
    'monitor_nwd' => $prodConnection->table('monitor_nwd')->where('school_id', 15)->count(),
    'school_colors' => $prodConnection->table('school_colors')->where('school_id', 15)->count(),
    'school_salary_levels' => $prodConnection->table('school_salary_levels')->where('school_id', 15)->count(),
    'school_sports' => $prodConnection->table('school_sports')->where('school_id', 15)->count(),
    'vouchers' => $prodConnection->table('vouchers')->where('school_id', 15)->count(),
];

foreach ($dataToRemove as $table => $count) {
    if ($count > 0) {
        echo "  {$table}: {$count} records to remove" . PHP_EOL;
    }
}

// Also check for related data that needs to be cleaned
$monitorSportIds = $prodConnection->table('monitor_sports_degrees')
    ->where('school_id', 15)
    ->pluck('id');

$authorizedDegreesToRemove = 0;
if ($monitorSportIds->count() > 0) {
    $authorizedDegreesToRemove = $prodConnection->table('monitor_sport_authorized_degrees')
        ->whereIn('monitor_sport_id', $monitorSportIds)
        ->count();
}

$courseIds = $prodConnection->table('courses')->where('school_id', 15)->pluck('id');
$courseDatesToRemove = 0;
$courseGroupsToRemove = 0;
$courseSubgroupsToRemove = 0;

if ($courseIds->count() > 0) {
    $courseDatesToRemove = $prodConnection->table('course_dates')
        ->whereIn('course_id', $courseIds)
        ->count();
    
    $courseGroupsToRemove = $prodConnection->table('course_groups')
        ->whereIn('course_id', $courseIds)
        ->count();
        
    $courseSubgroupsToRemove = $prodConnection->table('course_subgroups')
        ->whereIn('course_id', $courseIds)
        ->count();
}

echo "\nRelated data to remove:" . PHP_EOL;
echo "  monitor_sport_authorized_degrees: {$authorizedDegreesToRemove}" . PHP_EOL;
echo "  course_dates: {$courseDatesToRemove}" . PHP_EOL;
echo "  course_groups: {$courseGroupsToRemove}" . PHP_EOL;
echo "  course_subgroups: {$courseSubgroupsToRemove}" . PHP_EOL;

// Start cleanup process
echo "\n2. EXECUTING CLEANUP (in dependency order)" . PHP_EOL;
echo str_repeat("-", 50) . PHP_EOL;

$cleanupOrder = [
    // Remove dependent data first
    'monitor_sport_authorized_degrees' => function() use ($prodConnection, $monitorSportIds) {
        if ($monitorSportIds->count() > 0) {
            return $prodConnection->table('monitor_sport_authorized_degrees')
                ->whereIn('monitor_sport_id', $monitorSportIds)
                ->delete();
        }
        return 0;
    },
    
    'course_subgroups' => function() use ($prodConnection, $courseIds) {
        if ($courseIds->count() > 0) {
            return $prodConnection->table('course_subgroups')
                ->whereIn('course_id', $courseIds)
                ->delete();
        }
        return 0;
    },
    
    'course_groups' => function() use ($prodConnection, $courseIds) {
        if ($courseIds->count() > 0) {
            return $prodConnection->table('course_groups')
                ->whereIn('course_id', $courseIds)
                ->delete();
        }
        return 0;
    },
    
    'course_dates' => function() use ($prodConnection, $courseIds) {
        if ($courseIds->count() > 0) {
            return $prodConnection->table('course_dates')
                ->whereIn('course_id', $courseIds)
                ->delete();
        }
        return 0;
    },
    
    // Remove main school-related data
    'booking_users' => function() use ($prodConnection) {
        return $prodConnection->table('booking_users')
            ->where('school_id', 15)
            ->delete();
    },
    
    'bookings' => function() use ($prodConnection) {
        return $prodConnection->table('bookings')
            ->where('school_id', 15)
            ->delete();
    },
    
    'payments' => function() use ($prodConnection) {
        return $prodConnection->table('payments')
            ->where('school_id', 15)
            ->delete();
    },
    
    'monitor_sports_degrees' => function() use ($prodConnection) {
        return $prodConnection->table('monitor_sports_degrees')
            ->where('school_id', 15)
            ->delete();
    },
    
    'monitors_schools' => function() use ($prodConnection) {
        return $prodConnection->table('monitors_schools')
            ->where('school_id', 15)
            ->delete();
    },
    
    'clients_sports' => function() use ($prodConnection) {
        return $prodConnection->table('clients_sports')
            ->where('school_id', 15)
            ->delete();
    },
    
    'clients_schools' => function() use ($prodConnection) {
        return $prodConnection->table('clients_schools')
            ->where('school_id', 15)
            ->delete();
    },
    
    'client_observations' => function() use ($prodConnection) {
        return $prodConnection->table('client_observations')
            ->where('school_id', 15)
            ->delete();
    },
    
    'vouchers' => function() use ($prodConnection) {
        return $prodConnection->table('vouchers')
            ->where('school_id', 15)
            ->delete();
    },
    
    'courses' => function() use ($prodConnection) {
        return $prodConnection->table('courses')
            ->where('school_id', 15)
            ->delete();
    },
    
    'degrees' => function() use ($prodConnection) {
        return $prodConnection->table('degrees')
            ->where('school_id', 15)
            ->delete();
    },
    
    'monitor_nwd' => function() use ($prodConnection) {
        return $prodConnection->table('monitor_nwd')
            ->where('school_id', 15)
            ->delete();
    },
    
    'school_colors' => function() use ($prodConnection) {
        return $prodConnection->table('school_colors')
            ->where('school_id', 15)
            ->delete();
    },
    
    'school_salary_levels' => function() use ($prodConnection) {
        return $prodConnection->table('school_salary_levels')
            ->where('school_id', 15)
            ->delete();
    },
    
    'school_sports' => function() use ($prodConnection) {
        return $prodConnection->table('school_sports')
            ->where('school_id', 15)
            ->delete();
    },
    
    // Finally remove the school itself
    'schools' => function() use ($prodConnection) {
        return $prodConnection->table('schools')
            ->where('id', 15)
            ->delete();
    }
];

$totalRemoved = 0;

foreach ($cleanupOrder as $item => $deleteFunction) {
    try {
        $removed = $deleteFunction();
        if ($removed > 0) {
            echo "  ✓ Removed {$removed} records from {$item}" . PHP_EOL;
            $totalRemoved += $removed;
        }
    } catch (Exception $e) {
        echo "  ❌ Error cleaning {$item}: " . substr($e->getMessage(), 0, 60) . "..." . PHP_EOL;
    }
}

echo "\n3. CLEANUP VERIFICATION" . PHP_EOL;
echo str_repeat("-", 50) . PHP_EOL;

// Verify cleanup
$remainingData = [];
foreach (array_keys($dataToRemove) as $table) {
    $count = $prodConnection->table($table)->where('school_id', 15)->count();
    if ($count > 0) {
        $remainingData[$table] = $count;
    }
}

if (empty($remainingData)) {
    echo "✓ Cleanup successful - no data remains for school 15" . PHP_EOL;
} else {
    echo "⚠ Some data still remains:" . PHP_EOL;
    foreach ($remainingData as $table => $count) {
        echo "  {$table}: {$count} records" . PHP_EOL;
    }
}

echo "\nTotal records removed: {$totalRemoved}" . PHP_EOL;
echo "\n=== CLEANUP COMPLETE ===" . PHP_EOL;