<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== ANALYZING CLIENT LEVELS/SPORTS ===\n";
echo "Checking what's missing in client sports data\n";
echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

$devConnection = DB::connection('boukii_dev');
$prodConnection = DB::connection('boukii_pro');

$sourceSchoolId = 13;
$targetSchoolId = 15;

echo "1. ANALYZING CLIENT SPORTS IN DEV\n";
echo str_repeat("-", 50) . "\n";

// Get all clients for school 13 in DEV
$devClients = $devConnection->table('clients_schools')
    ->where('school_id', $sourceSchoolId)
    ->pluck('client_id');

echo "Clients in DEV school 13: " . $devClients->count() . "\n";

// Get client sports data
$devClientsSports = $devConnection->table('clients_sports')
    ->where('school_id', $sourceSchoolId)
    ->get();

echo "Client sports records in DEV: " . $devClientsSports->count() . "\n";

if ($devClientsSports->count() > 0) {
    echo "\nDEV Client Sports Details:\n";
    foreach ($devClientsSports as $clientSport) {
        $client = $devConnection->table('clients')->where('id', $clientSport->client_id)->first();
        $sport = $devConnection->table('sports')->where('id', $clientSport->sport_id)->first();
        
        echo "  - Client: {$client->first_name} {$client->last_name} ({$client->email})\n";
        echo "    Sport: {$sport->name} (ID: {$clientSport->sport_id})\n";
        echo "    Level: {$clientSport->level}\n";
        echo "    Client ID: {$clientSport->client_id}\n";
        echo "    ---\n";
    }
}

echo "\n2. ANALYZING CLIENT SPORTS IN PROD\n";
echo str_repeat("-", 50) . "\n";

// Get all clients for school 15 in PROD
$prodClients = $prodConnection->table('clients_schools')
    ->where('school_id', $targetSchoolId)
    ->pluck('client_id');

echo "Clients in PROD school 15: " . $prodClients->count() . "\n";

// Get client sports data in PROD
$prodClientsSports = $prodConnection->table('clients_sports')
    ->where('school_id', $targetSchoolId)
    ->get();

echo "Client sports records in PROD: " . $prodClientsSports->count() . "\n";

if ($prodClientsSports->count() > 0) {
    echo "\nPROD Client Sports Details:\n";
    foreach ($prodClientsSports as $clientSport) {
        $client = $prodConnection->table('clients')->where('id', $clientSport->client_id)->first();
        $sport = $prodConnection->table('sports')->where('id', $clientSport->sport_id)->first();
        
        echo "  - Client: {$client->first_name} {$client->last_name} ({$client->email})\n";
        echo "    Sport: {$sport->name} (ID: {$clientSport->sport_id})\n";
        echo "    Level: {$clientSport->level}\n";
        echo "    Client ID: {$clientSport->client_id}\n";
        echo "    ---\n";
    }
} else {
    echo "❌ NO CLIENT SPORTS DATA FOUND IN PROD!\n";
}

echo "\n3. CHECKING CLIENT OBSERVATIONS\n";
echo str_repeat("-", 50) . "\n";

$devClientObservations = $devConnection->table('client_observations')
    ->where('school_id', $sourceSchoolId)
    ->get();

$prodClientObservations = $prodConnection->table('client_observations')
    ->where('school_id', $targetSchoolId)
    ->get();

echo "Client observations in DEV: " . $devClientObservations->count() . "\n";
echo "Client observations in PROD: " . $prodClientObservations->count() . "\n";

if ($devClientObservations->count() > 0) {
    echo "\nDEV Client Observations:\n";
    foreach ($devClientObservations as $obs) {
        $client = $devConnection->table('clients')->where('id', $obs->client_id)->first();
        echo "  - Client: {$client->first_name} {$client->last_name}\n";
        echo "    Observation: {$obs->observation}\n";
        echo "    ---\n";
    }
}

echo "\n4. CHECKING CLIENT UTILIZERS (FAMILY RELATIONSHIPS)\n";
echo str_repeat("-", 50) . "\n";

// Check if we have family relationships
$devClientUtilizers = $devConnection->table('clients_utilizers')
    ->whereIn('client_id', $devClients)
    ->orWhereIn('utilizer_id', $devClients)
    ->get();

$prodClientUtilizers = $prodConnection->table('clients_utilizers')
    ->whereIn('client_id', $prodClients)
    ->orWhereIn('utilizer_id', $prodClients)
    ->get();

echo "Client utilizers in DEV: " . $devClientUtilizers->count() . "\n";
echo "Client utilizers in PROD: " . $prodClientUtilizers->count() . "\n";

echo "\n5. CREATING CLIENT MAPPING\n";
echo str_repeat("-", 50) . "\n";

// Create client mapping based on email
$clientMapping = [];
$prodAllClients = $prodConnection->table('clients')->get();

foreach ($prodAllClients as $prodClient) {
    $devClient = $devConnection->table('clients')->where('email', $prodClient->email)->first();
    if ($devClient) {
        $clientMapping[$devClient->id] = $prodClient->id;
    }
}

echo "Client mapping created for " . count($clientMapping) . " clients\n";

echo "\n6. WHAT NEEDS TO BE MIGRATED\n";
echo str_repeat("-", 50) . "\n";

$needsClientSports = $devClientsSports->count() - $prodClientsSports->count();
$needsClientObservations = $devClientObservations->count() - $prodClientObservations->count();
$needsClientUtilizers = $devClientUtilizers->count() - $prodClientUtilizers->count();

echo "MISSING DATA:\n";
echo "  - Client Sports: {$needsClientSports} records need to be migrated\n";
echo "  - Client Observations: {$needsClientObservations} records need to be migrated\n";
echo "  - Client Utilizers: {$needsClientUtilizers} records need to be migrated\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "CLIENT LEVELS ANALYSIS COMPLETE\n";
echo str_repeat("=", 80) . "\n";

if ($needsClientSports > 0 || $needsClientObservations > 0 || $needsClientUtilizers > 0) {
    echo "❌ CLIENT DATA IS INCOMPLETE - Migration needed!\n";
} else {
    echo "✅ All client data appears to be migrated\n";
}

echo "\n=== ANALYSIS COMPLETE ===\n";