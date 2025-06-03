<?php

namespace App\Console;

use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('filesystem:reconcile /path/to/watch --skip-scan')
        //   ->dailyAt('3:00')
        //   ->onSuccess(function () {
        //       Log::info('Nightly reconciliation completed successfully');
        //   })
        //   ->onFailure(function () {
        //       Log::error('Nightly reconciliation failed');
        //   });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
