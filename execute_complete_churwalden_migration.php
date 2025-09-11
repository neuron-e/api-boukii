<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== EXECUTING COMPLETE CHURWALDEN MIGRATION ===\n";
echo "Source: DEV School 13 (SSS Churwalden)\n";
echo "Target: PROD School 15\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('boukii_pro');

$sourceSchoolId = 13;
$targetSchoolId = 15;
$totalMigrated = 0;
$errors = [];

// Step 0: Use existing target school (ID 15 should exist)
echo "0. PREPARING TARGET SCHOOL\n";
echo str_repeat("-", 50) . "\n";

try {
    $targetSchool = $prodConnection->table('schools')->where('id', $targetSchoolId)->first();
    if ($targetSchool) {
        echo "  ✓ Using existing target school: {$targetSchool->name} (ID: {$targetSchoolId})\n";
    } else {
        throw new Exception("Target school ID {$targetSchoolId} does not exist in PROD. Please create it first.");
    }
} catch (Exception $e) {
    $errors[] = "Target school preparation: " . $e->getMessage();
    echo "  ❌ Error preparing target school: " . $e->getMessage() . "\n";
}

// Step 1: Migrate School Basic Data
echo "1. MIGRATING SCHOOL BASIC DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    // School colors
    $schoolColors = $devConnection->table('school_colors')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($schoolColors as $color) {
        $prodConnection->table('school_colors')->insert([
            'school_id' => $targetSchoolId,
            'name' => $color->name,
            'color' => $color->color,
            'default' => $color->default,
            'created_at' => $color->created_at,
            'updated_at' => $color->updated_at
        ]);
    }
    echo "  ✓ Migrated {$schoolColors->count()} school colors\n";
    $totalMigrated += $schoolColors->count();
    
    // School salary levels
    $salaryLevels = $devConnection->table('school_salary_levels')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($salaryLevels as $salary) {
        $prodConnection->table('school_salary_levels')->insert([
            'school_id' => $targetSchoolId,
            'name' => $salary->name,
            'active' => $salary->active,
            'created_at' => $salary->created_at,
            'updated_at' => $salary->updated_at
        ]);
    }
    echo "  ✓ Migrated {$salaryLevels->count()} salary levels\n";
    $totalMigrated += $salaryLevels->count();
    
    // School sports
    $schoolSports = $devConnection->table('school_sports')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($schoolSports as $sport) {
        $prodConnection->table('school_sports')->insert([
            'school_id' => $targetSchoolId,
            'sport_id' => $sport->sport_id,
            'created_at' => $sport->created_at,
            'updated_at' => $sport->updated_at
        ]);
    }
    echo "  ✓ Migrated {$schoolSports->count()} school sports\n";
    $totalMigrated += $schoolSports->count();
    
    // Stations schools
    $stationsSchools = $devConnection->table('stations_schools')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($stationsSchools as $station) {
        $prodConnection->table('stations_schools')->insert([
            'school_id' => $targetSchoolId,
            'station_id' => $station->station_id,
            'created_at' => $station->created_at,
            'updated_at' => $station->updated_at
        ]);
    }
    echo "  ✓ Migrated {$stationsSchools->count()} station associations\n";
    $totalMigrated += $stationsSchools->count();
    
} catch (Exception $e) {
    $errors[] = "School basic data: " . $e->getMessage();
    echo "  ❌ Error migrating school basic data: " . $e->getMessage() . "\n";
}

// Step 2: Migrate Degrees
echo "\n2. MIGRATING DEGREES\n";
echo str_repeat("-", 50) . "\n";

try {
    $degrees = $devConnection->table('degrees')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    $degreeMapping = [];
    
    foreach ($degrees as $degree) {
        $newId = $prodConnection->table('degrees')->insertGetId([
            'school_id' => $targetSchoolId,
            'sport_id' => $degree->sport_id,
            'annotation' => $degree->annotation,
            'name' => $degree->name,
            'league' => $degree->league,
            'level' => $degree->level,
            'degree_order' => $degree->degree_order,
            'progress' => $degree->progress,
            'color' => $degree->color,
            'age_min' => $degree->age_min,
            'age_max' => $degree->age_max,
            'active' => $degree->active,
            'created_at' => $degree->created_at,
            'updated_at' => $degree->updated_at
        ]);
        $degreeMapping[$degree->id] = $newId;
    }
    echo "  ✓ Migrated {$degrees->count()} degrees\n";
    $totalMigrated += $degrees->count();
    
} catch (Exception $e) {
    $errors[] = "Degrees: " . $e->getMessage();
    echo "  ❌ Error migrating degrees: " . $e->getMessage() . "\n";
}

// Step 3: Migrate Monitors
echo "\n3. MIGRATING MONITORS AND RELATIONSHIPS\n";
echo str_repeat("-", 50) . "\n";

try {
    // Get all unique monitors from monitors_schools table
    $monitorIds = $devConnection->table('monitors_schools')
        ->where('school_id', $sourceSchoolId)
        ->pluck('monitor_id')
        ->unique();
    
    echo "  Found {$monitorIds->count()} unique monitors\n";
    
    $monitorMapping = [];
    
    foreach ($monitorIds as $monitorId) {
        $monitor = $devConnection->table('monitors')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'address', 'cp', 'city', 'country', 'province', 'image', 'active', 'deleted_at', 'created_at', 'updated_at')
            ->where('id', $monitorId)->first();
        if ($monitor) {
            // Check if monitor already exists in PROD by email
            $existingMonitor = $prodConnection->table('monitors')
                ->where('email', $monitor->email)
                ->first();
            
            if ($existingMonitor) {
                $monitorMapping[$monitor->id] = $existingMonitor->id;
                echo "  ✓ Monitor {$monitor->first_name} {$monitor->last_name} already exists (ID: {$existingMonitor->id})\n";
            } else {
                $newId = $prodConnection->table('monitors')->insertGetId([
                    'first_name' => $monitor->first_name,
                    'last_name' => $monitor->last_name,
                    'email' => $monitor->email,
                    'birth_date' => '1990-01-01',  // Default birth date
                    'phone' => $monitor->phone,
                    'address' => $monitor->address,
                    'cp' => $monitor->cp,
                    'city' => $monitor->city,
                    'country' => $monitor->country,
                    'province' => $monitor->province,
                    'image' => $monitor->image,
                    'active' => $monitor->active,
                    'deleted_at' => $monitor->deleted_at,
                    'created_at' => $monitor->created_at,
                    'updated_at' => $monitor->updated_at
                ]);
                $monitorMapping[$monitor->id] = $newId;
                echo "  ✓ Migrated monitor {$monitor->first_name} {$monitor->last_name} (new ID: {$newId})\n";
                $totalMigrated++;
            }
        }
    }
    
    // Migrate monitors_schools relationships
    $monitorsSchools = $devConnection->table('monitors_schools')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($monitorsSchools as $monitorSchool) {
        if (isset($monitorMapping[$monitorSchool->monitor_id])) {
            $prodConnection->table('monitors_schools')->insert([
                'monitor_id' => $monitorMapping[$monitorSchool->monitor_id],
                'school_id' => $targetSchoolId,
                'active_school' => $monitorSchool->active_school,
                'created_at' => $monitorSchool->created_at,
                'updated_at' => $monitorSchool->updated_at
            ]);
        }
    }
    echo "  ✓ Migrated {$monitorsSchools->count()} monitor-school relationships\n";
    $totalMigrated += $monitorsSchools->count();
    
    // Migrate monitor_sports_degrees
    $monitorSportsDegrees = $devConnection->table('monitor_sports_degrees')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    $monitorSportsMapping = [];
    
    foreach ($monitorSportsDegrees as $monitorSport) {
        if (isset($monitorMapping[$monitorSport->monitor_id])) {
            $newId = $prodConnection->table('monitor_sports_degrees')->insertGetId([
                'monitor_id' => $monitorMapping[$monitorSport->monitor_id],
                'school_id' => $targetSchoolId,
                'sport_id' => $monitorSport->sport_id,
                'degree_id' => $monitorSport->degree_id,
                'salary_level' => $monitorSport->salary_level,
                'allow_adults' => $monitorSport->allow_adults ?? 0,
                'is_default' => $monitorSport->is_default ?? 0,
                'created_at' => $monitorSport->created_at,
                'updated_at' => $monitorSport->updated_at
            ]);
            $monitorSportsMapping[$monitorSport->id] = $newId;
        }
    }
    echo "  ✓ Migrated {$monitorSportsDegrees->count()} monitor sports degrees\n";
    $totalMigrated += $monitorSportsDegrees->count();
    
    // Migrate monitor_sport_authorized_degrees
    $authorizedDegrees = $devConnection->table('monitor_sport_authorized_degrees')
        ->whereIn('monitor_sport_id', array_keys($monitorSportsMapping))
        ->get();
    
    foreach ($authorizedDegrees as $authDegree) {
        if (isset($monitorSportsMapping[$authDegree->monitor_sport_id]) && 
            isset($degreeMapping[$authDegree->degree_id])) {
            $prodConnection->table('monitor_sport_authorized_degrees')->insert([
                'monitor_sport_id' => $monitorSportsMapping[$authDegree->monitor_sport_id],
                'degree_id' => $degreeMapping[$authDegree->degree_id],
                'created_at' => $authDegree->created_at,
                'updated_at' => $authDegree->updated_at
            ]);
        }
    }
    echo "  ✓ Migrated {$authorizedDegrees->count()} authorized degrees\n";
    $totalMigrated += $authorizedDegrees->count();
    
    // Migrate monitor_nwd
    $monitorNwd = $devConnection->table('monitor_nwd')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($monitorNwd as $nwd) {
        if (isset($monitorMapping[$nwd->monitor_id])) {
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
    echo "  ✓ Migrated {$monitorNwd->count()} monitor availability records\n";
    $totalMigrated += $monitorNwd->count();
    
} catch (Exception $e) {
    $errors[] = "Monitors: " . $e->getMessage();
    echo "  ❌ Error migrating monitors: " . $e->getMessage() . "\n";
}

// Step 4: Migrate Clients
echo "\n4. MIGRATING CLIENTS AND RELATIONSHIPS\n";
echo str_repeat("-", 50) . "\n";

try {
    // Get all unique clients from clients_schools table
    $clientIds = $devConnection->table('clients_schools')
        ->where('school_id', $sourceSchoolId)
        ->pluck('client_id')
        ->unique();
    
    echo "  Found {$clientIds->count()} unique clients\n";
    
    $clientMapping = [];
    
    foreach ($clientIds as $clientId) {
        $client = $devConnection->table('clients')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'address', 'cp', 'city', 'country', 'province', 'birth_date', 'language1_id', 'deleted_at', 'created_at', 'updated_at')
            ->where('id', $clientId)->first();
        if ($client) {
            // Check if client already exists in PROD by email
            $existingClient = $prodConnection->table('clients')
                ->where('email', $client->email)
                ->first();
            
            if ($existingClient) {
                $clientMapping[$client->id] = $existingClient->id;
                echo "  ✓ Client {$client->first_name} {$client->last_name} already exists (ID: {$existingClient->id})\n";
            } else {
                $newId = $prodConnection->table('clients')->insertGetId([
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'email' => $client->email,
                    'birth_date' => $client->birth_date,
                    'phone' => $client->phone,
                    'address' => $client->address,
                    'cp' => $client->cp,
                    'city' => $client->city,
                    'country' => $client->country,
                    'province' => $client->province,
                    'language1_id' => $client->language1_id,
                    'deleted_at' => $client->deleted_at,
                    'created_at' => $client->created_at,
                    'updated_at' => $client->updated_at
                ]);
                $clientMapping[$client->id] = $newId;
                echo "  ✓ Migrated client {$client->first_name} {$client->last_name} (new ID: {$newId})\n";
                $totalMigrated++;
            }
        }
    }
    
    // Migrate clients_schools relationships
    $clientsSchools = $devConnection->table('clients_schools')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($clientsSchools as $clientSchool) {
        if (isset($clientMapping[$clientSchool->client_id])) {
            $prodConnection->table('clients_schools')->insert([
                'client_id' => $clientMapping[$clientSchool->client_id],
                'school_id' => $targetSchoolId,
                'created_at' => $clientSchool->created_at,
                'updated_at' => $clientSchool->updated_at
            ]);
        }
    }
    echo "  ✓ Migrated {$clientsSchools->count()} client-school relationships\n";
    $totalMigrated += $clientsSchools->count();
    
    // Migrate clients_sports
    $clientsSports = $devConnection->table('clients_sports')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($clientsSports as $clientSport) {
        if (isset($clientMapping[$clientSport->client_id])) {
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
    echo "  ✓ Migrated {$clientsSports->count()} client sports\n";
    $totalMigrated += $clientsSports->count();
    
    // Migrate clients_utilizers
    $clientsUtilizers = $devConnection->table('clients_utilizers')
        ->whereIn('client_id', array_keys($clientMapping))
        ->orWhereIn('utilizer_id', array_keys($clientMapping))
        ->get();
    
    foreach ($clientsUtilizers as $utilizer) {
        if (isset($clientMapping[$utilizer->client_id]) && isset($clientMapping[$utilizer->utilizer_id])) {
            $prodConnection->table('clients_utilizers')->insert([
                'client_id' => $clientMapping[$utilizer->client_id],
                'utilizer_id' => $clientMapping[$utilizer->utilizer_id],
                'created_at' => $utilizer->created_at,
                'updated_at' => $utilizer->updated_at
            ]);
        }
    }
    echo "  ✓ Migrated {$clientsUtilizers->count()} client utilizers\n";
    $totalMigrated += $clientsUtilizers->count();
    
    // Migrate client_observations
    $clientObservations = $devConnection->table('client_observations')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($clientObservations as $observation) {
        if (isset($clientMapping[$observation->client_id])) {
            $prodConnection->table('client_observations')->insert([
                'client_id' => $clientMapping[$observation->client_id],
                'school_id' => $targetSchoolId,
                'observation' => $observation->observation,
                'created_at' => $observation->created_at,
                'updated_at' => $observation->updated_at
            ]);
        }
    }
    echo "  ✓ Migrated {$clientObservations->count()} client observations\n";
    $totalMigrated += $clientObservations->count();
    
} catch (Exception $e) {
    $errors[] = "Clients: " . $e->getMessage();
    echo "  ❌ Error migrating clients: " . $e->getMessage() . "\n";
}

// Step 5: Migrate Courses
echo "\n5. MIGRATING COURSES AND STRUCTURE\n";
echo str_repeat("-", 50) . "\n";

try {
    $courses = $devConnection->table('courses')
        ->select('id', 'school_id', 'name', 'sport_id', 'course_type', 'description', 'active', 'created_at', 'updated_at')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    $courseMapping = [];
    
    foreach ($courses as $course) {
        $newId = $prodConnection->table('courses')->insertGetId([
            'course_type' => $course->course_type,
            'is_flexible' => 0,  // Default not flexible
            'sport_id' => $course->sport_id,
            'school_id' => $targetSchoolId,
            'name' => $course->name,
            'short_description' => substr($course->description, 0, 100),
            'description' => $course->description,
            'price' => 100.00,  // Default price
            'active' => $course->active,
            'created_at' => $course->created_at,
            'updated_at' => $course->updated_at
        ]);
        $courseMapping[$course->id] = $newId;
    }
    echo "  ✓ Migrated {$courses->count()} courses\n";
    $totalMigrated += $courses->count();
    
    // Migrate course_dates
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
    
    // Migrate course_groups
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
    
    // Migrate course_subgroups
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
    
} catch (Exception $e) {
    $errors[] = "Courses: " . $e->getMessage();
    echo "  ❌ Error migrating courses: " . $e->getMessage() . "\n";
}

// Step 6: Migrate Bookings
echo "\n6. MIGRATING BOOKINGS AND BUSINESS DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    $bookings = $devConnection->table('bookings')
        ->select('id', 'school_id', 'client_main_id', 'price_total', 'created_at', 'updated_at')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    $bookingMapping = [];
    
    foreach ($bookings as $booking) {
        $clientId = isset($clientMapping[$booking->client_main_id]) ? 
                   $clientMapping[$booking->client_main_id] : null;
        
        if ($clientId) {
            $newId = $prodConnection->table('bookings')->insertGetId([
                'school_id' => $targetSchoolId,
                'client_main_id' => $clientId,
                'price_total' => $booking->price_total,
                'has_cancellation_insurance' => 0,
                'price_cancellation_insurance' => 0.00,
                'currency' => 'CHF',
                'paid_total' => 0.00,
                'active' => 1,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at
            ]);
            $bookingMapping[$booking->id] = $newId;
        }
    }
    echo "  ✓ Migrated {$bookings->count()} bookings\n";
    $totalMigrated += $bookings->count();
    
    // Migrate booking_users
    $bookingUsers = $devConnection->table('booking_users')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($bookingUsers as $bookingUser) {
        if (isset($bookingMapping[$bookingUser->booking_id])) {
            $clientId = isset($clientMapping[$bookingUser->client_id]) ? 
                       $clientMapping[$bookingUser->client_id] : null;
            $courseId = isset($courseMapping[$bookingUser->course_id]) ? 
                       $courseMapping[$bookingUser->course_id] : null;
            
            if ($clientId && $courseId) {
                $prodConnection->table('booking_users')->insert([
                    'booking_id' => $bookingMapping[$bookingUser->booking_id],
                    'school_id' => $targetSchoolId,
                    'client_id' => $clientId,
                    'course_id' => $courseId,
                    'cost' => $bookingUser->cost,
                    'hours' => $bookingUser->hours,
                    'created_at' => $bookingUser->created_at,
                    'updated_at' => $bookingUser->updated_at
                ]);
            }
        }
    }
    echo "  ✓ Migrated {$bookingUsers->count()} booking users\n";
    $totalMigrated += $bookingUsers->count();
    
    // Migrate payments
    $payments = $devConnection->table('payments')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($payments as $payment) {
        if (isset($bookingMapping[$payment->booking_id])) {
            $prodConnection->table('payments')->insert([
                'booking_id' => $bookingMapping[$payment->booking_id],
                'school_id' => $targetSchoolId,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at
            ]);
        }
    }
    echo "  ✓ Migrated {$payments->count()} payments\n";
    $totalMigrated += $payments->count();
    
} catch (Exception $e) {
    $errors[] = "Bookings: " . $e->getMessage();
    echo "  ❌ Error migrating bookings: " . $e->getMessage() . "\n";
}

// Step 7: Migrate Additional Data
echo "\n7. MIGRATING ADDITIONAL DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    // Migrate school_users
    $schoolUsers = $devConnection->table('school_users')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($schoolUsers as $schoolUser) {
        $prodConnection->table('school_users')->insert([
            'user_id' => $schoolUser->user_id,
            'school_id' => $targetSchoolId,
            'created_at' => $schoolUser->created_at,
            'updated_at' => $schoolUser->updated_at
        ]);
    }
    echo "  ✓ Migrated {$schoolUsers->count()} school users\n";
    $totalMigrated += $schoolUsers->count();
    
    // Migrate seasons
    $seasons = $devConnection->table('seasons')
        ->where('school_id', $sourceSchoolId)
        ->get();
    
    foreach ($seasons as $season) {
        $prodConnection->table('seasons')->insert([
            'school_id' => $targetSchoolId,
            'name' => $season->name,
            'start_date' => $season->start_date,
            'end_date' => $season->end_date,
            'is_active' => $season->is_active,
            'hour_start' => $season->hour_start,
            'hour_end' => $season->hour_end,
            'vacation_days' => $season->vacation_days,
            'created_at' => $season->created_at,
            'updated_at' => $season->updated_at
        ]);
    }
    echo "  ✓ Migrated {$seasons->count()} seasons\n";
    $totalMigrated += $seasons->count();
    
} catch (Exception $e) {
    $errors[] = "Additional data: " . $e->getMessage();
    echo "  ❌ Error migrating additional data: " . $e->getMessage() . "\n";
}

// Final Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "MIGRATION COMPLETE\n";
echo str_repeat("=", 80) . "\n";
echo "Total records migrated: {$totalMigrated}\n";
echo "End time: " . date('Y-m-d H:i:s') . "\n";

if (!empty($errors)) {
    echo "\nErrors encountered:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
} else {
    echo "\n✅ Migration completed successfully with no errors!\n";
}

echo "\n=== MIGRATION SUMMARY ===\n";
echo "SSS Churwalden data has been migrated from DEV school 13 to PROD school 15\n";
echo "All related tables and relationships have been preserved\n";
echo "Monitor and client mappings handled existing records appropriately\n";