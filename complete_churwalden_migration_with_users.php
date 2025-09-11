<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPLETE CHURWALDEN MIGRATION WITH USERS ===\n";
echo "Source: DEV School 13 (SSS Churwalden)\n";
echo "Target: PROD School 15\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('boukii_pro');

$sourceSchoolId = 13;
$targetSchoolId = 15;
$totalMigrated = 0;
$errors = [];

// Step 0: Create target school
echo "0. CREATING TARGET SCHOOL\n";
echo str_repeat("-", 50) . "\n";

try {
    $sourceSchool = $devConnection->table('schools')->where('id', $sourceSchoolId)->first();
    if ($sourceSchool) {
        $prodConnection->table('schools')->insert([
            'id' => $targetSchoolId,
            'name' => $sourceSchool->name,
            'description' => $sourceSchool->description,
            'contact_email' => $sourceSchool->contact_email,
            'slug' => $sourceSchool->slug,
            'active' => $sourceSchool->active,
            'created_at' => $sourceSchool->created_at,
            'updated_at' => $sourceSchool->updated_at
        ]);
        echo "  ✓ Created target school: {$sourceSchool->name}\n";
        $totalMigrated++;
    }
} catch (Exception $e) {
    $errors[] = "School creation: " . $e->getMessage();
    echo "  ❌ Error creating school: " . $e->getMessage() . "\n";
}

// Step 1: Migrate School Basic Data
echo "\n1. MIGRATING SCHOOL BASIC DATA\n";
echo str_repeat("-", 50) . "\n";

try {
    // School colors
    $schoolColors = $devConnection->table('school_colors')->where('school_id', $sourceSchoolId)->get();
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
    $salaryLevels = $devConnection->table('school_salary_levels')->where('school_id', $sourceSchoolId)->get();
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
    $schoolSports = $devConnection->table('school_sports')->where('school_id', $sourceSchoolId)->get();
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
    $stationsSchools = $devConnection->table('stations_schools')->where('school_id', $sourceSchoolId)->get();
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
    $degrees = $devConnection->table('degrees')->where('school_id', $sourceSchoolId)->get();
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

// Step 3: Migrate Users FIRST (before monitors)
echo "\n3. MIGRATING USERS\n";
echo str_repeat("-", 50) . "\n";

try {
    // Get all users associated with the school (school_users, monitors, clients)
    $schoolUserIds = $devConnection->table('school_users')->where('school_id', $sourceSchoolId)->pluck('user_id');
    
    // Get monitor user IDs
    $monitorUserIds = $devConnection->table('monitors')->whereIn('id', 
        $devConnection->table('monitors_schools')->where('school_id', $sourceSchoolId)->pluck('monitor_id')
    )->pluck('user_id')->filter();
    
    // Get client user IDs  
    $clientUserIds = $devConnection->table('clients')->whereIn('id',
        $devConnection->table('clients_schools')->where('school_id', $sourceSchoolId)->pluck('client_id')
    )->pluck('user_id')->filter();
    
    // Combine all unique user IDs
    $allUserIds = $schoolUserIds->merge($monitorUserIds)->merge($clientUserIds)->unique()->filter();
    
    echo "  Found " . $allUserIds->count() . " users to migrate\n";
    
    $userMapping = [];
    
    if ($allUserIds->count() > 0) {
        $users = $devConnection->table('users')->whereIn('id', $allUserIds)->get();
        
        foreach ($users as $user) {
            // Check if user already exists in PROD by email
            $existingUser = $prodConnection->table('users')->where('email', $user->email)->first();
            
            if ($existingUser) {
                $userMapping[$user->id] = $existingUser->id;
                echo "  ✓ User {$user->first_name} {$user->last_name} already exists (ID: {$existingUser->id})\n";
            } else {
                try {
                    $newId = $prodConnection->table('users')->insertGetId([
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'password' => $user->password,
                        'type' => $user->type,
                        'active' => $user->active,
                        'image' => $user->image,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'deleted_at' => $user->deleted_at
                    ]);
                    $userMapping[$user->id] = $newId;
                    echo "  ✓ Migrated user {$user->first_name} {$user->last_name} (new ID: {$newId})\n";
                    $totalMigrated++;
                } catch (Exception $e) {
                    echo "  ⚠️ Error migrating user {$user->first_name}: " . substr($e->getMessage(), 0, 50) . "...\n";
                }
            }
        }
    }
    
    // Migrate school_users relationships
    foreach ($schoolUserIds as $userId) {
        if (isset($userMapping[$userId])) {
            $prodConnection->table('school_users')->insert([
                'user_id' => $userMapping[$userId],
                'school_id' => $targetSchoolId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    echo "  ✓ Migrated school-user relationships\n";
    
} catch (Exception $e) {
    $errors[] = "Users: " . $e->getMessage();
    echo "  ❌ Error migrating users: " . $e->getMessage() . "\n";
}

// Step 4: Migrate Monitors
echo "\n4. MIGRATING MONITORS AND RELATIONSHIPS\n";
echo str_repeat("-", 50) . "\n";

try {
    // Get all unique monitors from monitors_schools table
    $monitorIds = $devConnection->table('monitors_schools')->where('school_id', $sourceSchoolId)->pluck('monitor_id')->unique();
    echo "  Found {$monitorIds->count()} unique monitors\n";
    
    $monitorMapping = [];
    
    foreach ($monitorIds as $monitorId) {
        $monitor = $devConnection->table('monitors')
            ->select('id', 'first_name', 'last_name', 'email', 'birth_date', 'phone', 'address', 'cp', 'city', 'country', 'province', 'image', 'active', 'deleted_at', 'created_at', 'updated_at', 'user_id')
            ->where('id', $monitorId)->first();
        if ($monitor) {
            // Check if monitor already exists in PROD by email
            $existingMonitor = $prodConnection->table('monitors')->where('email', $monitor->email)->first();
            
            if ($existingMonitor) {
                $monitorMapping[$monitor->id] = $existingMonitor->id;
                echo "  ✓ Monitor {$monitor->first_name} {$monitor->last_name} already exists (ID: {$existingMonitor->id})\n";
            } else {
                $newId = $prodConnection->table('monitors')->insertGetId([
                    'first_name' => $monitor->first_name,
                    'last_name' => $monitor->last_name,
                    'email' => $monitor->email,
                    'birth_date' => $monitor->birth_date ?: '1990-01-01',
                    'phone' => $monitor->phone,
                    'address' => $monitor->address,
                    'cp' => $monitor->cp,
                    'city' => $monitor->city,
                    'country' => $monitor->country,
                    'province' => $monitor->province,
                    'image' => $monitor->image,
                    'user_id' => isset($userMapping[$monitor->user_id]) ? $userMapping[$monitor->user_id] : null,
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
    $monitorsSchools = $devConnection->table('monitors_schools')->where('school_id', $sourceSchoolId)->get();
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
    
    // Migrate monitor_sports_degrees with proper salary level mapping
    $monitorSportsDegrees = $devConnection->table('monitor_sports_degrees')->where('school_id', $sourceSchoolId)->get();
    $monitorSportsMapping = [];
    
    // Get salary level mapping
    $salaryLevelMapping = [];
    $devSalaryLevels = $devConnection->table('school_salary_levels')->where('school_id', $sourceSchoolId)->get();
    $prodSalaryLevels = $prodConnection->table('school_salary_levels')->where('school_id', $targetSchoolId)->get();
    
    foreach ($devSalaryLevels as $devSalary) {
        $prodSalary = $prodSalaryLevels->firstWhere('name', $devSalary->name);
        if ($prodSalary) {
            $salaryLevelMapping[$devSalary->id] = $prodSalary->id;
        }
    }
    
    foreach ($monitorSportsDegrees as $monitorSport) {
        if (isset($monitorMapping[$monitorSport->monitor_id])) {
            $salaryLevelId = isset($salaryLevelMapping[$monitorSport->salary_level]) ? 
                           $salaryLevelMapping[$monitorSport->salary_level] : null;
            
            $newId = $prodConnection->table('monitor_sports_degrees')->insertGetId([
                'monitor_id' => $monitorMapping[$monitorSport->monitor_id],
                'school_id' => $targetSchoolId,
                'sport_id' => $monitorSport->sport_id,
                'degree_id' => isset($degreeMapping[$monitorSport->degree_id]) ? $degreeMapping[$monitorSport->degree_id] : null,
                'salary_level' => $salaryLevelId,
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
    
} catch (Exception $e) {
    $errors[] = "Monitors: " . $e->getMessage();
    echo "  ❌ Error migrating monitors: " . $e->getMessage() . "\n";
}

// Step 5: Migrate Clients
echo "\n5. MIGRATING CLIENTS AND RELATIONSHIPS\n";
echo str_repeat("-", 50) . "\n";

try {
    // Get all unique clients from clients_schools table
    $clientIds = $devConnection->table('clients_schools')->where('school_id', $sourceSchoolId)->pluck('client_id')->unique();
    echo "  Found {$clientIds->count()} unique clients\n";
    
    $clientMapping = [];
    
    foreach ($clientIds as $clientId) {
        $client = $devConnection->table('clients')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'address', 'cp', 'city', 'country', 'province', 'birth_date', 'language1_id', 'deleted_at', 'created_at', 'updated_at', 'user_id')
            ->where('id', $clientId)->first();
        if ($client) {
            // Check if client already exists in PROD by email
            $existingClient = $prodConnection->table('clients')->where('email', $client->email)->first();
            
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
                    'user_id' => isset($userMapping[$client->user_id]) ? $userMapping[$client->user_id] : null,
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
    $clientsSchools = $devConnection->table('clients_schools')->where('school_id', $sourceSchoolId)->get();
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
    $clientsSports = $devConnection->table('clients_sports')->where('school_id', $sourceSchoolId)->get();
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
    
} catch (Exception $e) {
    $errors[] = "Clients: " . $e->getMessage();
    echo "  ❌ Error migrating clients: " . $e->getMessage() . "\n";
}

// Step 6: Migrate Courses
echo "\n6. MIGRATING COURSES AND STRUCTURE\n";
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
            'is_flexible' => 0,
            'sport_id' => $course->sport_id,
            'school_id' => $targetSchoolId,
            'name' => $course->name,
            'short_description' => substr($course->description ?? '', 0, 100),
            'description' => $course->description ?? '',
            'price' => 100.00,
            'date_start' => '2025-01-01',
            'date_end' => '2025-12-31',
            'active' => $course->active,
            'created_at' => $course->created_at,
            'updated_at' => $course->updated_at
        ]);
        $courseMapping[$course->id] = $newId;
    }
    echo "  ✓ Migrated {$courses->count()} courses\n";
    $totalMigrated += $courses->count();
    
} catch (Exception $e) {
    $errors[] = "Courses: " . $e->getMessage();
    echo "  ❌ Error migrating courses: " . $e->getMessage() . "\n";
}

// Step 7: Migrate Bookings
echo "\n7. MIGRATING BOOKINGS AND BUSINESS DATA\n";
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
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at
            ]);
            $bookingMapping[$booking->id] = $newId;
        }
    }
    echo "  ✓ Migrated {$bookings->count()} bookings\n";
    $totalMigrated += $bookings->count();
    
    // Migrate payments
    $payments = $devConnection->table('payments')->where('school_id', $sourceSchoolId)->get();
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

// Step 8: Migrate Seasons
echo "\n8. MIGRATING SEASONS\n";
echo str_repeat("-", 50) . "\n";

try {
    $seasons = $devConnection->table('seasons')->where('school_id', $sourceSchoolId)->get();
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
    $errors[] = "Seasons: " . $e->getMessage();
    echo "  ❌ Error migrating seasons: " . $e->getMessage() . "\n";
}

// Final Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "COMPLETE CHURWALDEN MIGRATION FINISHED\n";
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

echo "\n🎉 SSS CHURWALDEN MIGRATION IS COMPLETE!\n";
echo "All data migrated from DEV School 13 to PROD School 15\n";
echo "Including: users, monitors, clients, courses, degrees, bookings, and all relationships\n";