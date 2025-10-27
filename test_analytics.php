<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Analytics Endpoints\n";
echo "==========================\n\n";

// Test 1: Check if we have data
echo "1. Database Data Check:\n";
echo "   Queue Numbers: " . DB::table('queue_numbers')->count() . "\n";
echo "   Departments: " . DB::table('departments')->count() . "\n";
echo "   Transactions: " . DB::table('transactions')->count() . "\n\n";

// Test 2: Check historical data query
echo "2. Historical Data Query Test:\n";
$startDate = Carbon::now()->subMonth();
$transactions = DB::table('queue_numbers')
    ->where('created_at', '>=', $startDate)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->groupBy('date')
    ->orderBy('date')
    ->get();

echo "   Transactions data points: " . $transactions->count() . "\n";
if ($transactions->count() > 0) {
    echo "   Sample data: " . $transactions->first()->date . " - " . $transactions->first()->count . " transactions\n";
}

// Test 3: Check wait times query
$waitTimes = DB::table('queue_numbers')
    ->whereIn('status', ['completed', 'successful'])
    ->where('created_at', '>=', $startDate)
    ->selectRaw('DATE(COALESCE(completed_at, created_at)) as date, AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(completed_at, updated_at))) as average_wait')
    ->groupBy('date')
    ->orderBy('date')
    ->get();

echo "   Wait times data points: " . $waitTimes->count() . "\n";
if ($waitTimes->count() > 0) {
    echo "   Sample data: " . $waitTimes->first()->date . " - " . round($waitTimes->first()->average_wait, 2) . " minutes\n";
}

// Test 4: Check canceled transactions query
$canceledTransactions = DB::table('queue_numbers')
    ->whereIn('status', ['failed', 'canceled'])
    ->where('created_at', '>=', $startDate)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->groupBy('date')
    ->orderBy('date')
    ->get();

echo "   Canceled transactions data points: " . $canceledTransactions->count() . "\n";
if ($canceledTransactions->count() > 0) {
    echo "   Sample data: " . $canceledTransactions->first()->date . " - " . $canceledTransactions->first()->count . " canceled\n";
}

echo "\nAnalytics endpoints should be working with real data!\n";
