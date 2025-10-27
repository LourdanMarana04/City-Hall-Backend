<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== User Database Check ===\n\n";

// Get all users
$users = User::all();

if ($users->count() > 0) {
    echo "Found " . $users->count() . " users in database:\n\n";
    
    foreach ($users as $user) {
        echo "ID: " . $user->id . "\n";
        echo "Name: " . $user->name . "\n";
        echo "Email: " . $user->email . "\n";
        echo "Role: " . $user->role . "\n";
        echo "Is Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
        echo "Created: " . $user->created_at . "\n";
        echo "---\n";
    }
} else {
    echo "No users found in database.\n";
}

echo "\n=== End of Check ===\n"; 