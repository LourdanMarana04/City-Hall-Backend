<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // Clear all currently serving data daily at midnight
        $schedule->call(function () {
            $departments = \App\Models\Department::all();
            foreach ($departments as $department) {
                cache()->forget("currently_serving_{$department->id}");
            }
        })->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Bootstrap the application.
     */
    public function boot(): void
    {
        parent::boot();

        // Clear all currently serving data on application boot (useful for server restarts)
        $departments = \App\Models\Department::all();
        foreach ($departments as $department) {
            cache()->forget("currently_serving_{$department->id}");
        }
    }
}
