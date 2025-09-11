<?php
/**
 * Check current database structure to understand what exists
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔍 CHECKING DATABASE STRUCTURE\n";
echo "=====================================\n\n";

try {
    // Check which tables exist
    echo "📋 Existing Tables:\n";
    $tables = DB::select("SHOW TABLES");
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        echo "  ✅ $tableName\n";
    }
    
    echo "\n";
    
    // Check specific table structures
    $tablesToCheck = ['users', 'schools', 'roles', 'permissions', 'user_school_roles', 'modules'];
    
    foreach ($tablesToCheck as $tableName) {
        if (Schema::hasTable($tableName)) {
            echo "📝 Structure of '{$tableName}' table:\n";
            $columns = DB::select("DESCRIBE $tableName");
            foreach ($columns as $column) {
                echo "  - {$column->Field} ({$column->Type}) " . 
                     ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . 
                     ($column->Key ? " [{$column->Key}]" : '') . 
                     ($column->Default !== null ? " DEFAULT: {$column->Default}" : '') . "\n";
            }
            echo "\n";
        } else {
            echo "❌ Table '{$tableName}' does not exist\n\n";
        }
    }
    
    // Check for users
    if (Schema::hasTable('users')) {
        $userCount = DB::table('users')->count();
        echo "👥 Users in database: {$userCount}\n";
        
        if ($userCount > 0) {
            $sampleUsers = DB::table('users')
                ->select(['id', 'email', 'username', 'type', 'active'])
                ->limit(5)
                ->get();
            
            echo "Sample users:\n";
            foreach ($sampleUsers as $user) {
                echo "  - ID: {$user->id}, Email: {$user->email}, Type: {$user->type}, Active: {$user->active}\n";
            }
        }
        echo "\n";
    }
    
    // Check for schools
    if (Schema::hasTable('schools')) {
        $schoolCount = DB::table('schools')->count();
        echo "🏫 Schools in database: {$schoolCount}\n";
        
        if ($schoolCount > 0) {
            $sampleSchools = DB::table('schools')
                ->select(['id', 'name', 'active'])
                ->limit(5)
                ->get();
            
            echo "Sample schools:\n";
            foreach ($sampleSchools as $school) {
                echo "  - ID: {$school->id}, Name: {$school->name}, Active: {$school->active}\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}