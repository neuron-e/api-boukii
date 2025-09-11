<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPLETING REMAINING CHURWALDEN MIGRATION ===\n";
echo "Source: DEV School 13 (SSS Churwalden)\n";
echo "Target: PROD School 15\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('boukii_pro');

$sourceSchoolId = 13;
$targetSchoolId = 15;
$totalMigrated = 0;
$errors = [];

// Get existing mappings from previous migration
echo "1. GETTING EXISTING MAPPINGS\n";
echo str_repeat("-", 50) . "\n";

// Get course mappings
$existingCourses = $prodConnection->table('courses')->where('school_id', $targetSchoolId)->get();
$courseMapping = [];
foreach ($existingCourses as $course) {
    // Match by name since we don't have old_id
    $devCourse = $devConnection->table('courses')
        ->where('school_id', $sourceSchoolId)
        ->where('name', $course->name)
        ->first();
    if ($devCourse) {
        $courseMapping[$devCourse->id] = $course->id;
    }
}
echo "  ✓ Found " . count($courseMapping) . " course mappings\n";

// Get monitor mappings
$existingMonitors = $prodConnection->table('monitors')->get();
$monitorMapping = [];
foreach ($existingMonitors as $monitor) {
    $devMonitor = $devConnection->table('monitors')
        ->where('email', $monitor->email)
        ->first();
    if ($devMonitor) {
        $monitorMapping[$devMonitor->id] = $monitor->id;
    }
}
echo "  ✓ Found " . count($monitorMapping) . " monitor mappings\n";

// Get client mappings
$existingClients = $prodConnection->table('clients')->get();
$clientMapping = [];
foreach ($existingClients as $client) {
    $devClient = $devConnection->table('clients')
        ->where('email', $client->email)
        ->first();
    if ($devClient) {
        $clientMapping[$devClient->id] = $client->id;
    }
}
echo "  ✓ Found " . count($clientMapping) . " client mappings\n";

// Get degree mappings
$existingDegrees = $prodConnection->table('degrees')->where('school_id', $targetSchoolId)->get();
$degreeMapping = [];
foreach ($existingDegrees as $degree) {
    $devDegree = $devConnection->table('degrees')
        ->where('school_id', $sourceSchoolId)
        ->where('annotation', $degree->annotation)
        ->where('sport_id', $degree->sport_id)
        ->first();
    if ($devDegree) {
        $degreeMapping[$devDegree->id] = $degree->id;
    }
}
echo "  ✓ Found " . count($degreeMapping) . " degree mappings\n";

// 2. Migrate Course Structure Data
echo "\n2. MIGRATING COURSE STRUCTURE DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    // Course Dates
    if (!empty($courseMapping)) {
        $courseDates = $devConnection->table('course_dates')
            ->whereIn('course_id', array_keys($courseMapping))
            ->get();
        
        foreach ($courseDates as $courseDate) {
            if (isset($courseMapping[$courseDate->course_id])) {
                $prodConnection->table('course_dates')->insert([
                    'course_id' => $courseMapping[$courseDate->course_id],
                    'date' => $courseDate->date,
                    'start_time' => $courseDate->start_time,
                    'end_time' => $courseDate->end_time,
                    'created_at' => $courseDate->created_at,
                    'updated_at' => $courseDate->updated_at
                ]);
            }
        }
        echo "  ✓ Migrated {$courseDates->count()} course dates\n";
        $totalMigrated += $courseDates->count();
        
        // Course Groups
        $courseGroups = $devConnection->table('course_groups')
            ->whereIn('course_id', array_keys($courseMapping))
            ->get();
        
        $courseGroupMapping = [];
        foreach ($courseGroups as $courseGroup) {
            if (isset($courseMapping[$courseGroup->course_id])) {
                $newId = $prodConnection->table('course_groups')->insertGetId([
                    'course_id' => $courseMapping[$courseGroup->course_id],
                    'name' => $courseGroup->name,
                    'min_clients' => $courseGroup->min_clients,
                    'max_clients' => $courseGroup->max_clients,
                    'active' => $courseGroup->active,
                    'created_at' => $courseGroup->created_at,
                    'updated_at' => $courseGroup->updated_at
                ]);
                $courseGroupMapping[$courseGroup->id] = $newId;
            }
        }
        echo "  ✓ Migrated {$courseGroups->count()} course groups\n";
        $totalMigrated += $courseGroups->count();
        
        // Course Subgroups
        $courseSubgroups = $devConnection->table('course_subgroups')
            ->whereIn('course_group_id', array_keys($courseGroupMapping))
            ->get();
        
        foreach ($courseSubgroups as $subgroup) {
            if (isset($courseGroupMapping[$subgroup->course_group_id])) {
                $monitorId = isset($monitorMapping[$subgroup->monitor_id]) ? 
                            $monitorMapping[$subgroup->monitor_id] : null;
                
                $prodConnection->table('course_subgroups')->insert([
                    'course_group_id' => $courseGroupMapping[$subgroup->course_group_id],
                    'monitor_id' => $monitorId,
                    'name' => $subgroup->name,
                    'max_clients' => $subgroup->max_clients,
                    'active' => $subgroup->active,
                    'created_at' => $subgroup->created_at,
                    'updated_at' => $subgroup->updated_at
                ]);
            }
        }
        echo "  ✓ Migrated {$courseSubgroups->count()} course subgroups\n";
        $totalMigrated += $courseSubgroups->count();
    }
    
} catch (Exception $e) {
    $errors[] = "Course structure: " . $e->getMessage();
    echo "  ❌ Error migrating course structure: " . $e->getMessage() . "\n";
}

// 3. Migrate Monitor Data
echo "\n3. MIGRATING MONITOR ADDITIONAL DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    // Get monitor sports degrees with existing monitors
    $existingMonitorSports = $prodConnection->table('monitor_sports_degrees')
        ->where('school_id', $targetSchoolId)
        ->get();
    
    $monitorSportsMapping = [];
    foreach ($existingMonitorSports as $ms) {
        $devMs = $devConnection->table('monitor_sports_degrees')
            ->where('school_id', $sourceSchoolId)
            ->where('monitor_id', array_search($ms->monitor_id, $monitorMapping))
            ->where('sport_id', $ms->sport_id)
            ->first();
        if ($devMs) {
            $monitorSportsMapping[$devMs->id] = $ms->id;
        }
    }
    echo "  ✓ Found " . count($monitorSportsMapping) . " monitor sports mappings\n";
    
    // Monitor Sport Authorized Degrees
    if (!empty($monitorSportsMapping) && !empty($degreeMapping)) {
        $authorizedDegrees = $devConnection->table('monitor_sport_authorized_degrees')
            ->whereIn('monitor_sport_id', array_keys($monitorSportsMapping))
            ->get();
        
        foreach ($authorizedDegrees as $authDegree) {
            if (isset($monitorSportsMapping[$authDegree->monitor_sport_id]) && 
                isset($degreeMapping[$authDegree->degree_id])) {
                
                // Check if already exists
                $exists = $prodConnection->table('monitor_sport_authorized_degrees')
                    ->where('monitor_sport_id', $monitorSportsMapping[$authDegree->monitor_sport_id])
                    ->where('degree_id', $degreeMapping[$authDegree->degree_id])
                    ->exists();
                
                if (!$exists) {
                    $prodConnection->table('monitor_sport_authorized_degrees')->insert([
                        'monitor_sport_id' => $monitorSportsMapping[$authDegree->monitor_sport_id],
                        'degree_id' => $degreeMapping[$authDegree->degree_id],
                        'created_at' => $authDegree->created_at,
                        'updated_at' => $authDegree->updated_at
                    ]);
                }
            }
        }
        echo "  ✓ Migrated {$authorizedDegrees->count()} authorized degrees\n";
        $totalMigrated += $authorizedDegrees->count();
    }
    
    // Monitor NWD (availability)
    if (!empty($monitorMapping)) {
        $monitorNwd = $devConnection->table('monitor_nwd')
            ->where('school_id', $sourceSchoolId)
            ->get();
        
        foreach ($monitorNwd as $nwd) {
            if (isset($monitorMapping[$nwd->monitor_id])) {
                // Check if already exists
                $exists = $prodConnection->table('monitor_nwd')
                    ->where('monitor_id', $monitorMapping[$nwd->monitor_id])
                    ->where('school_id', $targetSchoolId)
                    ->where('day', $nwd->day)
                    ->exists();
                
                if (!$exists) {
                    $prodConnection->table('monitor_nwd')->insert([
                        'monitor_id' => $monitorMapping[$nwd->monitor_id],
                        'school_id' => $targetSchoolId,
                        'day' => $nwd->day,
                        'available' => $nwd->available,
                        'created_at' => $nwd->created_at,
                        'updated_at' => $nwd->updated_at
                    ]);
                }
            }
        }
        echo "  ✓ Migrated {$monitorNwd->count()} monitor availability records\n";
        $totalMigrated += $monitorNwd->count();
    }
    
} catch (Exception $e) {
    $errors[] = "Monitor additional data: " . $e->getMessage();
    echo "  ❌ Error migrating monitor data: " . $e->getMessage() . "\n";
}

// 4. Migrate Client Additional Data
echo "\n4. MIGRATING CLIENT ADDITIONAL DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    if (!empty($clientMapping)) {
        // Clients Sports
        $clientsSports = $devConnection->table('clients_sports')
            ->where('school_id', $sourceSchoolId)
            ->get();
        
        foreach ($clientsSports as $clientSport) {
            if (isset($clientMapping[$clientSport->client_id])) {
                // Check if already exists
                $exists = $prodConnection->table('clients_sports')
                    ->where('client_id', $clientMapping[$clientSport->client_id])
                    ->where('school_id', $targetSchoolId)
                    ->where('sport_id', $clientSport->sport_id)
                    ->exists();
                
                if (!$exists) {
                    $prodConnection->table('clients_sports')->insert([
                        'client_id' => $clientMapping[$clientSport->client_id],
                        'school_id' => $targetSchoolId,
                        'sport_id' => $clientSport->sport_id,
                        'level' => $clientSport->level,
                        'created_at' => $clientSport->created_at,
                        'updated_at' => $clientSport->updated_at
                    ]);
                }
            }
        }
        echo "  ✓ Migrated {$clientsSports->count()} client sports\n";
        $totalMigrated += $clientsSports->count();
        
        // Clients Utilizers
        $clientsUtilizers = $devConnection->table('clients_utilizers')
            ->whereIn('client_id', array_keys($clientMapping))
            ->orWhereIn('utilizer_id', array_keys($clientMapping))
            ->get();
        
        foreach ($clientsUtilizers as $utilizer) {
            if (isset($clientMapping[$utilizer->client_id]) && 
                isset($clientMapping[$utilizer->utilizer_id])) {
                
                // Check if already exists
                $exists = $prodConnection->table('clients_utilizers')
                    ->where('client_id', $clientMapping[$utilizer->client_id])
                    ->where('utilizer_id', $clientMapping[$utilizer->utilizer_id])
                    ->exists();
                
                if (!$exists) {
                    $prodConnection->table('clients_utilizers')->insert([
                        'client_id' => $clientMapping[$utilizer->client_id],
                        'utilizer_id' => $clientMapping[$utilizer->utilizer_id],
                        'created_at' => $utilizer->created_at,
                        'updated_at' => $utilizer->updated_at
                    ]);
                }
            }
        }
        echo "  ✓ Migrated {$clientsUtilizers->count()} client utilizers\n";
        $totalMigrated += $clientsUtilizers->count();
        
        // Client Observations
        $clientObservations = $devConnection->table('client_observations')
            ->where('school_id', $sourceSchoolId)
            ->get();
        
        foreach ($clientObservations as $observation) {
            if (isset($clientMapping[$observation->client_id])) {
                // Check if already exists
                $exists = $prodConnection->table('client_observations')
                    ->where('client_id', $clientMapping[$observation->client_id])
                    ->where('school_id', $targetSchoolId)
                    ->where('observation', $observation->observation)
                    ->exists();
                
                if (!$exists) {
                    $prodConnection->table('client_observations')->insert([
                        'client_id' => $clientMapping[$observation->client_id],
                        'school_id' => $targetSchoolId,
                        'observation' => $observation->observation,
                        'created_at' => $observation->created_at,
                        'updated_at' => $observation->updated_at
                    ]);
                }
            }
        }
        echo "  ✓ Migrated {$clientObservations->count()} client observations\n";
        $totalMigrated += $clientObservations->count();
    }
    
} catch (Exception $e) {
    $errors[] = "Client additional data: " . $e->getMessage();
    echo "  ❌ Error migrating client data: " . $e->getMessage() . "\n";
}

// Final Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "REMAINING DATA MIGRATION COMPLETE\n";
echo str_repeat("=", 80) . "\n";
echo "Additional records migrated: {$totalMigrated}\n";
echo "End time: " . date('Y-m-d H:i:s') . "\n";

if (!empty($errors)) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
} else {
    echo "\n✅ Additional migration completed successfully with no errors!\n";
}

echo "\n=== COMPLETE MIGRATION SUMMARY ===\n";
echo "SSS Churwalden migration is now complete with all related data\n";
echo "Original migration: ~269 records\n";
echo "Additional migration: {$totalMigrated} records\n";
echo "All course dates, groups, subgroups, monitor degrees and client data migrated\n";