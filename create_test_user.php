<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$app = new Application(__DIR__);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->boot();

try {
    // Check if user already exists
    $existingUser = User::where('email', 'admin.test@boukii.com')->first();
    
    if ($existingUser) {
        echo "User admin.test@boukii.com already exists (ID: {$existingUser->id})\n";
        echo "Updating password...\n";
        $existingUser->update([
            'password' => Hash::make('password123')
        ]);
        echo "Password updated successfully!\n";
    } else {
        // Create new user
        $user = User::create([
            'username' => 'admin_test',
            'email' => 'admin.test@boukii.com',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'password' => Hash::make('password123'),
            'type' => 1, // admin type
            'active' => true
        ]);
        
        echo "User created successfully: {$user->email} (ID: {$user->id})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}