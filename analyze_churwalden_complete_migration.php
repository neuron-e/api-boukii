<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPLETE CHURWALDEN SCHOOL MIGRATION ANALYSIS ===" . PHP_EOL;
echo "School: SSS Churwalden (ID: 13)" . PHP_EOL;
echo "Target: PROD School 15" . PHP_EOL;
echo "Analysis Date: " . date('Y-m-d H:i:s') . PHP_EOL;

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('mysql');

$sourceSchoolId = 13; // SSS Churwalden in DEV
$targetSchoolId = 15; // Target in PROD

// Get school basic info
$sourceSchool = $devConnection->table('schools')->where('id', $sourceSchoolId)->first();
if (!$sourceSchool) {
    echo "❌ Source school not found!" . PHP_EOL;
    exit;
}

echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "SOURCE SCHOOL INFORMATION" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

echo "ID: {$sourceSchool->id}" . PHP_EOL;
echo "Name: {$sourceSchool->name}" . PHP_EOL;
echo "Description: {$sourceSchool->description}" . PHP_EOL;
echo "Contact Email: {$sourceSchool->contact_email}" . PHP_EOL;
echo "Slug: {$sourceSchool->slug}" . PHP_EOL;
echo "Active: " . ($sourceSchool->active ? 'Yes' : 'No') . PHP_EOL;

// 1. CORE SCHOOL DATA
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "1. CORE SCHOOL DATA TO MIGRATE" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

$coreData = [
    'school_colors' => [
        'table' => 'school_colors',
        'condition' => "school_id = {$sourceSchoolId}",
        'description' => 'School color schemes for UI',
        'dependencies' => ['schools'],
        'fields' => ['name', 'color', 'default', 'active']
    ],
    
    'school_salary_levels' => [
        'table' => 'school_salary_levels',
        'condition' => "school_id = {$sourceSchoolId}",
        'description' => 'Salary levels for monitors',
        'dependencies' => ['schools'],
        'fields' => ['name', 'color', 'active']
    ],
    
    'school_sports' => [
        'table' => 'school_sports',
        'condition' => "school_id = {$sourceSchoolId}",
        'description' => 'Sports offered by the school',
        'dependencies' => ['schools', 'sports'],
        'fields' => ['sport_id']
    ],
    
    'stations_schools' => [
        'table' => 'stations_schools',
        'condition' => "school_id = {$sourceSchoolId}",
        'description' => 'Stations associated with school',
        'dependencies' => ['schools', 'stations'],
        'fields' => ['station_id']
    ],
    
    'seasons' => [
        'table' => 'seasons',
        'condition' => "school_id = {$sourceSchoolId}",
        'description' => 'School seasons/periods',
        'dependencies' => ['schools'],
        'fields' => ['name', 'date_start', 'date_end', 'active']
    ],
    
    'tasks' => [
        'table' => 'tasks',
        'condition' => "school_id = {$sourceSchoolId}",
        'description' => 'School administrative tasks',
        'dependencies' => ['schools'],
        'fields' => ['name', 'description', 'active']
    ],
];

foreach ($coreData as $key => $info) {
    $count = $devConnection->select("SELECT COUNT(*) as count FROM {$info['table']} WHERE {$info['condition']} AND deleted_at IS NULL")[0]->count;
    echo sprintf("%-25s: %4d records - %s", $key, $count, $info['description']) . PHP_EOL;
    
    if ($count > 0) {
        // Show sample data
        $sampleData = $devConnection->select("SELECT * FROM {$info['table']} WHERE {$info['condition']} AND deleted_at IS NULL LIMIT 2");
        foreach ($sampleData as $sample) {
            $relevantFields = [];
            foreach ($info['fields'] as $field) {
                if (property_exists($sample, $field)) {
                    $relevantFields[] = "{$field}: {$sample->$field}";
                }
            }
            echo "  └─ Sample: " . implode(', ', $relevantFields) . PHP_EOL;
        }
    }
}

// 2. DEGREES AND SPORTS SYSTEM
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "2. DEGREES AND SPORTS SYSTEM" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

$degreesCount = $devConnection->table('degrees')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "degrees: {$degreesCount} records - Skill levels/certifications" . PHP_EOL;

if ($degreesCount > 0) {
    // Analyze degrees by sport
    $degreesBySport = $devConnection->table('degrees as d')
        ->join('sports as s', 'd.sport_id', '=', 's.id')
        ->where('d.school_id', $sourceSchoolId)
        ->whereNull('d.deleted_at')
        ->select('s.name as sport_name', 'd.sport_id')
        ->selectRaw('COUNT(*) as degree_count')
        ->groupBy('d.sport_id', 's.name')
        ->get();
    
    foreach ($degreesBySport as $sport) {
        echo "  └─ {$sport->sport_name}: {$sport->degree_count} degrees" . PHP_EOL;
    }
    
    // Sample degrees with key information
    $sampleDegrees = $devConnection->table('degrees')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->select(['name', 'annotation', 'level', 'color', 'sport_id'])
        ->limit(5)
        ->get();
    
    echo "  Sample degrees:" . PHP_EOL;
    foreach ($sampleDegrees as $degree) {
        echo "    • {$degree->name} ({$degree->annotation}) - Level: {$degree->level}, Color: {$degree->color}" . PHP_EOL;
    }
}

// Check degrees_school_sport_goals
$degreesGoalsCount = $devConnection->table('degrees_school_sport_goals as dssg')
    ->join('degrees as d', 'dssg.degree_id', '=', 'd.id')
    ->where('d.school_id', $sourceSchoolId)
    ->whereNull('dssg.deleted_at')
    ->count();

echo "degrees_school_sport_goals: {$degreesGoalsCount} records - Learning objectives for degrees" . PHP_EOL;

// 3. MONITORS AND THEIR RELATIONSHIPS
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "3. MONITORS AND RELATIONSHIPS" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// Get all unique monitor IDs for this school
$monitorIds = $devConnection->table('monitors_schools')
    ->where('school_id', $sourceSchoolId)
    ->distinct()
    ->pluck('monitor_id');

echo "unique_monitors: {$monitorIds->count()} monitors" . PHP_EOL;

// monitors_schools relationships
$monitorsSchoolsCount = $devConnection->table('monitors_schools')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

$activeMonitorsCount = $devConnection->table('monitors_schools')
    ->where('school_id', $sourceSchoolId)
    ->where('active_school', 1)
    ->whereNull('deleted_at')
    ->count();

echo "monitors_schools: {$monitorsSchoolsCount} records ({$activeMonitorsCount} active) - Monitor-school associations" . PHP_EOL;

// monitor_sports_degrees
$monitorSportsDegreesCount = $devConnection->table('monitor_sports_degrees')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "monitor_sports_degrees: {$monitorSportsDegreesCount} records - Monitor specializations" . PHP_EOL;

if ($monitorSportsDegreesCount > 0) {
    // Show sports distribution
    $monitorSportsDist = $devConnection->table('monitor_sports_degrees as msd')
        ->join('sports as s', 'msd.sport_id', '=', 's.id')
        ->where('msd.school_id', $sourceSchoolId)
        ->whereNull('msd.deleted_at')
        ->select('s.name as sport_name')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('s.name')
        ->get();
    
    foreach ($monitorSportsDist as $sport) {
        echo "  └─ {$sport->sport_name}: {$sport->count} monitor specializations" . PHP_EOL;
    }
}

// monitor_sport_authorized_degrees (complex relationship)
$monitorAuthorizedDegreesCount = $devConnection->table('monitor_sport_authorized_degrees as msad')
    ->join('monitor_sports_degrees as msd', 'msad.monitor_sport_id', '=', 'msd.id')
    ->where('msd.school_id', $sourceSchoolId)
    ->whereNull('msad.deleted_at')
    ->count();

echo "monitor_sport_authorized_degrees: {$monitorAuthorizedDegreesCount} records - Authorized teaching levels" . PHP_EOL;

// monitor_nwd (working hours/availability)
$monitorNwdCount = $devConnection->table('monitor_nwd')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "monitor_nwd: {$monitorNwdCount} records - Monitor working hours/availability" . PHP_EOL;

// monitor_observations
$monitorObservationsCount = $devConnection->table('monitor_observations')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "monitor_observations: {$monitorObservationsCount} records - Monitor notes/comments" . PHP_EOL;

// 4. CLIENTS AND RELATIONSHIPS
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "4. CLIENTS AND RELATIONSHIPS" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// Get unique client IDs for this school
$clientIds = $devConnection->table('clients_schools')
    ->where('school_id', $sourceSchoolId)
    ->distinct()
    ->pluck('client_id');

echo "unique_clients: {$clientIds->count()} clients associated with school" . PHP_EOL;

// clients_schools
$clientsSchoolsCount = $devConnection->table('clients_schools')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "clients_schools: {$clientsSchoolsCount} records - Client-school associations" . PHP_EOL;

// clients_sports
$clientsSportsCount = $devConnection->table('clients_sports')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "clients_sports: {$clientsSportsCount} records - Client sport preferences/levels" . PHP_EOL;

// clients_utilizers (family relationships)
$clientUtilizersCount = 0;
if ($clientIds->count() > 0) {
    $clientUtilizersCount = $devConnection->table('clients_utilizers')
        ->whereIn('main_id', $clientIds)
        ->orWhereIn('client_id', $clientIds)
        ->whereNull('deleted_at')
        ->count();
}
echo "clients_utilizers: {$clientUtilizersCount} records - Family/utilizer relationships" . PHP_EOL;

// client_observations
$clientObservationsCount = $devConnection->table('client_observations')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "client_observations: {$clientObservationsCount} records - Client notes/comments" . PHP_EOL;

// 5. COURSES AND STRUCTURE
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "5. COURSES AND STRUCTURE" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// courses
$coursesCount = $devConnection->table('courses')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "courses: {$coursesCount} records - Main courses offered" . PHP_EOL;

if ($coursesCount > 0) {
    $sampleCourses = $devConnection->table('courses')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->select(['name', 'course_type', 'sport_id'])
        ->get();
    
    foreach ($sampleCourses as $course) {
        echo "  └─ {$course->name} (Type: {$course->course_type}, Sport: {$course->sport_id})" . PHP_EOL;
    }
}

// Get course IDs for related data
$courseIds = $devConnection->table('courses')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->pluck('id');

$courseDatesCount = 0;
$courseGroupsCount = 0;
$courseSubgroupsCount = 0;
$courseExtrasCount = 0;

if ($courseIds->count() > 0) {
    $courseDatesCount = $devConnection->table('course_dates')
        ->whereIn('course_id', $courseIds)
        ->whereNull('deleted_at')
        ->count();
    
    $courseGroupsCount = $devConnection->table('course_groups')
        ->whereIn('course_id', $courseIds)
        ->whereNull('deleted_at')
        ->count();
    
    $courseSubgroupsCount = $devConnection->table('course_subgroups')
        ->whereIn('course_id', $courseIds)
        ->whereNull('deleted_at')
        ->count();
        
    $courseExtrasCount = $devConnection->table('course_extras')
        ->whereIn('course_id', $courseIds)
        ->whereNull('deleted_at')
        ->count();
}

echo "course_dates: {$courseDatesCount} records - Course schedule dates" . PHP_EOL;
echo "course_groups: {$courseGroupsCount} records - Course skill/age groups" . PHP_EOL;
echo "course_subgroups: {$courseSubgroupsCount} records - Course subgroups with monitors" . PHP_EOL;
echo "course_extras: {$courseExtrasCount} records - Additional services/equipment" . PHP_EOL;

// 6. BOOKINGS AND BUSINESS DATA
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "6. BOOKINGS AND BUSINESS DATA" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// bookings
$bookingsCount = $devConnection->table('bookings')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "bookings: {$bookingsCount} records - Customer reservations" . PHP_EOL;

if ($bookingsCount > 0) {
    $bookingStats = $devConnection->table('bookings')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->select('status')
        ->selectRaw('COUNT(*) as count')
        ->groupBy('status')
        ->get();
    
    foreach ($bookingStats as $stat) {
        $statusName = match($stat->status) {
            1 => 'Active',
            2 => 'Partial',
            3 => 'Cancelled',
            default => "Status {$stat->status}"
        };
        echo "  └─ {$statusName}: {$stat->count}" . PHP_EOL;
    }
    
    // Date range of bookings
    $dateRange = $devConnection->table('bookings')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->selectRaw('MIN(created_at) as first_booking, MAX(created_at) as last_booking')
        ->first();
    
    if ($dateRange->first_booking) {
        echo "  └─ Booking period: {$dateRange->first_booking} to {$dateRange->last_booking}" . PHP_EOL;
    }
}

// booking_users
$bookingUsersCount = $devConnection->table('booking_users')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "booking_users: {$bookingUsersCount} records - Individual course bookings" . PHP_EOL;

// booking_user_extras
$bookingUserExtrasCount = 0;
if ($bookingUsersCount > 0) {
    $bookingUserIds = $devConnection->table('booking_users')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->pluck('id');
    
    if ($bookingUserIds->count() > 0) {
        $bookingUserExtrasCount = $devConnection->table('booking_user_extras')
            ->whereIn('booking_user_id', $bookingUserIds)
            ->whereNull('deleted_at')
            ->count();
    }
}

echo "booking_user_extras: {$bookingUserExtrasCount} records - Additional services booked" . PHP_EOL;

// payments
$paymentsCount = $devConnection->table('payments')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "payments: {$paymentsCount} records - Payment transactions" . PHP_EOL;

if ($paymentsCount > 0) {
    $paymentStats = $devConnection->table('payments')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->selectRaw('SUM(amount) as total_amount, COUNT(*) as count')
        ->first();
    
    echo "  └─ Total amount: {$paymentStats->total_amount} CHF" . PHP_EOL;
}

// 7. VOUCHERS AND FINANCIAL
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "7. VOUCHERS AND FINANCIAL" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// vouchers
$vouchersCount = $devConnection->table('vouchers')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "vouchers: {$vouchersCount} records - Gift vouchers/credits" . PHP_EOL;

// vouchers_log
$vouchersLogCount = 0;
if ($vouchersCount > 0 || $bookingsCount > 0) {
    $voucherIds = $devConnection->table('vouchers')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->pluck('id');
    
    $bookingIds = $devConnection->table('bookings')
        ->where('school_id', $sourceSchoolId)
        ->whereNull('deleted_at')
        ->pluck('id');
    
    $vouchersLogCount = $devConnection->table('vouchers_log')
        ->where(function($query) use ($voucherIds, $bookingIds) {
            if ($voucherIds->count() > 0) {
                $query->whereIn('voucher_id', $voucherIds);
            }
            if ($bookingIds->count() > 0) {
                $query->orWhereIn('booking_id', $bookingIds);
            }
        })
        ->whereNull('deleted_at')
        ->count();
}

echo "vouchers_log: {$vouchersLogCount} records - Voucher usage history" . PHP_EOL;

// discount_codes
$discountCodesCount = $devConnection->table('discounts_codes')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "discounts_codes: {$discountCodesCount} records - Promotional discount codes" . PHP_EOL;

// 8. ADMINISTRATIVE DATA
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "8. ADMINISTRATIVE DATA" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// school_users (staff)
$schoolUsersCount = $devConnection->table('school_users')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "school_users: {$schoolUsersCount} records - School staff/admin users" . PHP_EOL;

// task_checks (if tasks exist)
$taskChecksCount = 0;
$taskIds = $devConnection->table('tasks')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->pluck('id');

if ($taskIds->count() > 0) {
    $taskChecksCount = $devConnection->table('task_checks')
        ->whereIn('task_id', $taskIds)
        ->whereNull('deleted_at')
        ->count();
}

echo "task_checks: {$taskChecksCount} records - Task completion tracking" . PHP_EOL;

// email_log
$emailLogCount = $devConnection->table('email_log')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "email_log: {$emailLogCount} records - Email communication log" . PHP_EOL;

// mails (templates)
$mailsCount = $devConnection->table('mails')
    ->where('school_id', $sourceSchoolId)
    ->whereNull('deleted_at')
    ->count();

echo "mails: {$mailsCount} records - Email templates" . PHP_EOL;

// 9. EVALUATION SYSTEM
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "9. EVALUATION SYSTEM" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

// evaluations (complex relationship through clients)
$evaluationsCount = 0;
if ($clientIds->count() > 0) {
    $evaluationsCount = $devConnection->table('evaluations')
        ->whereIn('client_id', $clientIds)
        ->whereNull('deleted_at')
        ->count();
}

echo "evaluations: {$evaluationsCount} records - Student skill evaluations" . PHP_EOL;

// evaluation_files
$evaluationFilesCount = 0;
if ($evaluationsCount > 0) {
    $evaluationIds = $devConnection->table('evaluations')
        ->whereIn('client_id', $clientIds)
        ->whereNull('deleted_at')
        ->pluck('id');
    
    if ($evaluationIds->count() > 0) {
        $evaluationFilesCount = $devConnection->table('evaluation_files')
            ->whereIn('evaluation_id', $evaluationIds)
            ->whereNull('deleted_at')
            ->count();
    }
}

echo "evaluation_files: {$evaluationFilesCount} records - Evaluation attachments" . PHP_EOL;

// evaluation_fulfilled_goals
$evaluationFulfilledGoalsCount = 0;
if ($evaluationsCount > 0) {
    $evaluationIds = $devConnection->table('evaluations')
        ->whereIn('client_id', $clientIds)
        ->whereNull('deleted_at')
        ->pluck('id');
    
    if ($evaluationIds->count() > 0) {
        $evaluationFulfilledGoalsCount = $devConnection->table('evaluation_fulfilled_goals')
            ->whereIn('evaluation_id', $evaluationIds)
            ->whereNull('deleted_at')
            ->count();
    }
}

echo "evaluation_fulfilled_goals: {$evaluationFulfilledGoalsCount} records - Completed learning objectives" . PHP_EOL;

// 10. SUMMARY AND MIGRATION COMPLEXITY
echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "10. MIGRATION SUMMARY AND COMPLEXITY" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

$totalRecords = 0;
$complexityScore = 0;

// Count all records
$allCounts = [
    // Core school data
    'school_colors' => $devConnection->table('school_colors')->where('school_id', $sourceSchoolId)->whereNull('deleted_at')->count(),
    'school_salary_levels' => $devConnection->table('school_salary_levels')->where('school_id', $sourceSchoolId)->whereNull('deleted_at')->count(),
    'school_sports' => $devConnection->table('school_sports')->where('school_id', $sourceSchoolId)->whereNull('deleted_at')->count(),
    'stations_schools' => $devConnection->table('stations_schools')->where('school_id', $sourceSchoolId)->whereNull('deleted_at')->count(),
    'seasons' => $devConnection->table('seasons')->where('school_id', $sourceSchoolId)->whereNull('deleted_at')->count(),
    
    // Degrees system
    'degrees' => $degreesCount,
    'degrees_school_sport_goals' => $degreesGoalsCount,
    
    // Monitors system
    'monitors_schools' => $monitorsSchoolsCount,
    'monitor_sports_degrees' => $monitorSportsDegreesCount,
    'monitor_sport_authorized_degrees' => $monitorAuthorizedDegreesCount,
    'monitor_nwd' => $monitorNwdCount,
    'monitor_observations' => $monitorObservationsCount,
    
    // Clients system
    'clients_schools' => $clientsSchoolsCount,
    'clients_sports' => $clientsSportsCount,
    'clients_utilizers' => $clientUtilizersCount,
    'client_observations' => $clientObservationsCount,
    
    // Courses system
    'courses' => $coursesCount,
    'course_dates' => $courseDatesCount,
    'course_groups' => $courseGroupsCount,
    'course_subgroups' => $courseSubgroupsCount,
    'course_extras' => $courseExtrasCount,
    
    // Business data
    'bookings' => $bookingsCount,
    'booking_users' => $bookingUsersCount,
    'booking_user_extras' => $bookingUserExtrasCount,
    'payments' => $paymentsCount,
    'vouchers' => $vouchersCount,
    'vouchers_log' => $vouchersLogCount,
    'discounts_codes' => $discountCodesCount,
    
    // Administrative
    'school_users' => $schoolUsersCount,
    'tasks' => $devConnection->table('tasks')->where('school_id', $sourceSchoolId)->whereNull('deleted_at')->count(),
    'task_checks' => $taskChecksCount,
    'email_log' => $emailLogCount,
    'mails' => $mailsCount,
    
    // Evaluation system
    'evaluations' => $evaluationsCount,
    'evaluation_files' => $evaluationFilesCount,
    'evaluation_fulfilled_goals' => $evaluationFulfilledGoalsCount,
];

foreach ($allCounts as $table => $count) {
    $totalRecords += $count;
}

echo "Total records to migrate: {$totalRecords}" . PHP_EOL;
echo "Unique clients: {$clientIds->count()}" . PHP_EOL;
echo "Unique monitors: {$monitorIds->count()}" . PHP_EOL;

// Calculate complexity based on relationships
$highComplexityTables = ['monitor_sport_authorized_degrees', 'course_subgroups', 'booking_users', 'evaluation_fulfilled_goals'];
$mediumComplexityTables = ['monitor_sports_degrees', 'clients_sports', 'course_groups', 'vouchers_log'];

$complexityScore = array_sum($allCounts);
$highComplexity = array_sum(array_intersect_key($allCounts, array_flip($highComplexityTables)));
$mediumComplexity = array_sum(array_intersect_key($allCounts, array_flip($mediumComplexityTables)));

echo "\nComplexity Analysis:" . PHP_EOL;
echo "  High complexity records: {$highComplexity}" . PHP_EOL;
echo "  Medium complexity records: {$mediumComplexity}" . PHP_EOL;
echo "  Migration complexity score: " . round($complexityScore / 100, 1) . "/10" . PHP_EOL;

echo "\nData Quality Indicators:" . PHP_EOL;
echo "  Bookings per monitor: " . round($bookingsCount / max($monitorIds->count(), 1), 2) . PHP_EOL;
echo "  Clients per monitor: " . round($clientIds->count() / max($monitorIds->count(), 1), 2) . PHP_EOL;
echo "  Business activity score: " . ($bookingsCount + $paymentsCount + $vouchersCount) . PHP_EOL;

echo "\n" . str_repeat("=", 80) . PHP_EOL;
echo "MIGRATION READINESS: " . ($totalRecords > 0 ? "✅ READY" : "❌ NO DATA") . PHP_EOL;
echo "Estimated migration time: " . round($totalRecords / 100, 1) . " minutes" . PHP_EOL;
echo str_repeat("=", 80) . PHP_EOL;

echo "\n=== ANALYSIS COMPLETE - READY TO PROCEED WITH MIGRATION ===" . PHP_EOL;