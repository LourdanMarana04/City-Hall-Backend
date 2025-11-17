<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QueueNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get department and transaction IDs
        $departments = DB::table('departments')->get();
        $transactions = DB::table('transactions')->get();

        if ($departments->isEmpty() || $transactions->isEmpty()) {
            echo "No departments or transactions found. Please run DepartmentSeeder first.\n";
            return;
        }

        $statuses = ['waiting', 'successful', 'canceled', 'failed'];
        $sources = ['web', 'kiosk'];

        // Generate queue numbers for the last 30 days
        for ($day = 30; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            
            // Generate 10-50 queue numbers per day
            $dailyCount = rand(10, 50);
            
            for ($i = 1; $i <= $dailyCount; $i++) {
                $department = $departments->random();
                $transaction = $transactions->where('department_id', $department->id)->first();
                
                if (!$transaction) {
                    continue;
                }

                $status = $statuses[array_rand($statuses)];
                $source = $sources[array_rand($sources)];
                
                // Generate queue number with simple prefix
                $queueNumber = $i;
                $prefix = strtoupper(substr($department->name, 0, 2));
                $fullQueueNumber = $prefix . str_pad($queueNumber, 3, '0', STR_PAD_LEFT);
                
                // Set completion time for completed transactions
                $completedAt = null;
                if ($status === 'successful') {
                    $completedAt = $date->copy()->addMinutes(rand(5, 45));
                }
                
                // Set updated_at for analytics calculations
                $updatedAt = $status === 'successful' ? $completedAt : $date->copy()->addMinutes(rand(1, 10));

                DB::table('queue_numbers')->insert([
                    'department_id' => $department->id,
                    'transaction_id' => $transaction->id,
                    'queue_number' => $queueNumber,
                    'full_queue_number' => $fullQueueNumber,
                    'status' => $status,
                    'source' => $source,
                    'completed_at' => $completedAt,
                    'user_id' => null,
                    'created_at' => $date,
                    'updated_at' => $updatedAt,
                ]);
            }
        }

        echo "Generated queue numbers for the last 30 days.\n";
    }
} 