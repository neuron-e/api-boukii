<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPLETE ROLLBACK OF SCHOOL 15 DATA ===\n";
echo "This will remove ALL data associated with school 15\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$prodConnection = DB::connection('boukii_pro');
$targetSchoolId = 15;
$totalRemoved = 0;

// Order matters - remove dependent data first
$cleanupOrder = [
    // Remove course related data first
    'course_subgroups' => function() use ($prodConnection, $targetSchoolId) {
        $courseIds = $prodConnection->table('courses')->where('school_id', $targetSchoolId)->pluck('id');
        if ($courseIds->count() > 0) {
            $courseGroupIds = $prodConnection->table('course_groups')->whereIn('course_id', $courseIds)->pluck('id');
            if ($courseGroupIds->count() > 0) {
                return $prodConnection->table('course_subgroups')->whereIn('course_group_id', $courseGroupIds)->delete();
            }
        }
        return 0;
    },
    
    'course_groups' => function() use ($prodConnection, $targetSchoolId) {
        $courseIds = $prodConnection->table('courses')->where('school_id', $targetSchoolId)->pluck('id');
        if ($courseIds->count() > 0) {
            return $prodConnection->table('course_groups')->whereIn('course_id', $courseIds)->delete();
        }
        return 0;
    },
    
    'course_dates' => function() use ($prodConnection, $targetSchoolId) {
        $courseIds = $prodConnection->table('courses')->where('school_id', $targetSchoolId)->pluck('id');
        if ($courseIds->count() > 0) {
            return $prodConnection->table('course_dates')->whereIn('course_id', $courseIds)->delete();
        }
        return 0;
    },
    
    // Remove booking related data
    'booking_user_extras' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('booking_user_extras')->where('school_id', $targetSchoolId)->delete();
    },
    
    'booking_users' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('booking_users')->where('school_id', $targetSchoolId)->delete();
    },
    
    'payments' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('payments')->where('school_id', $targetSchoolId)->delete();
    },
    
    'bookings' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('bookings')->where('school_id', $targetSchoolId)->delete();
    },
    
    // Remove monitor related data
    'monitor_sport_authorized_degrees' => function() use ($prodConnection, $targetSchoolId) {
        $monitorSportIds = $prodConnection->table('monitor_sports_degrees')->where('school_id', $targetSchoolId)->pluck('id');
        if ($monitorSportIds->count() > 0) {
            return $prodConnection->table('monitor_sport_authorized_degrees')->whereIn('monitor_sport_id', $monitorSportIds)->delete();
        }
        return 0;
    },
    
    'monitor_sports_degrees' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('monitor_sports_degrees')->where('school_id', $targetSchoolId)->delete();
    },
    
    'monitors_schools' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('monitors_schools')->where('school_id', $targetSchoolId)->delete();
    },
    
    'monitor_nwd' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('monitor_nwd')->where('school_id', $targetSchoolId)->delete();
    },
    
    // Remove client related data
    'clients_sports' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('clients_sports')->where('school_id', $targetSchoolId)->delete();
    },
    
    'clients_schools' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('clients_schools')->where('school_id', $targetSchoolId)->delete();
    },
    
    'client_observations' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('client_observations')->where('school_id', $targetSchoolId)->delete();
    },
    
    // Remove school configuration data
    'courses' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('courses')->where('school_id', $targetSchoolId)->delete();
    },
    
    'degrees' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('degrees')->where('school_id', $targetSchoolId)->delete();
    },
    
    'school_users' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('school_users')->where('school_id', $targetSchoolId)->delete();
    },
    
    'school_colors' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('school_colors')->where('school_id', $targetSchoolId)->delete();
    },
    
    'school_salary_levels' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('school_salary_levels')->where('school_id', $targetSchoolId)->delete();
    },
    
    'school_sports' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('school_sports')->where('school_id', $targetSchoolId)->delete();
    },
    
    'stations_schools' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('stations_schools')->where('school_id', $targetSchoolId)->delete();
    },
    
    'seasons' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('seasons')->where('school_id', $targetSchoolId)->delete();
    },
    
    // Finally remove the school itself
    'schools' => function() use ($prodConnection, $targetSchoolId) {
        return $prodConnection->table('schools')->where('id', $targetSchoolId)->delete();
    }
];

echo "Starting cleanup in dependency order...\n";
echo str_repeat("-", 50) . "\n";

foreach ($cleanupOrder as $table => $deleteFunction) {
    try {
        $removed = $deleteFunction();
        if ($removed > 0) {
            echo "  ✓ Removed {$removed} records from {$table}\n";
            $totalRemoved += $removed;
        }
    } catch (Exception $e) {
        echo "  ❌ Error cleaning {$table}: " . substr($e->getMessage(), 0, 60) . "...\n";
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "ROLLBACK COMPLETE\n";
echo str_repeat("=", 80) . "\n";
echo "Total records removed: {$totalRemoved}\n";
echo "End time: " . date('Y-m-d H:i:s') . "\n";

// Verification
echo "\nVerifying cleanup...\n";
$remainingData = [
    'schools' => $prodConnection->table('schools')->where('id', $targetSchoolId)->count(),
    'degrees' => $prodConnection->table('degrees')->where('school_id', $targetSchoolId)->count(),
    'monitors_schools' => $prodConnection->table('monitors_schools')->where('school_id', $targetSchoolId)->count(),
    'clients_schools' => $prodConnection->table('clients_schools')->where('school_id', $targetSchoolId)->count(),
    'courses' => $prodConnection->table('courses')->where('school_id', $targetSchoolId)->count(),
    'bookings' => $prodConnection->table('bookings')->where('school_id', $targetSchoolId)->count(),
];

$allClean = true;
foreach ($remainingData as $table => $count) {
    if ($count > 0) {
        echo "  ⚠️ {$table}: {$count} records still remain\n";
        $allClean = false;
    }
}

if ($allClean) {
    echo "✅ Complete cleanup successful - School 15 data completely removed\n";
    echo "\nNow you can re-run the original migration script with users included!\n";
} else {
    echo "❌ Some data still remains - may need manual cleanup\n";
}

echo "\n=== ROLLBACK COMPLETE ===\n";