<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        Commands\SubscriptionBillCron::class,
        Commands\DeleteNotifications::class,
        Commands\DeleteOldBackups::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('subscriptionBill:cron')->daily();
        $schedule->command('notifications:delete')->monthly();
        $schedule->command('transport:expiry-reminder')->daily();
        $schedule->command('app:delete-old-backups')->daily();
        // $schedule->command('whatsapp:process-scheduled')->everyMinute()->withoutOverlapping();
        $schedule->command('whatsapp:process-scheduled')->everyMinute();

        // Schools finish at different times, so this runs every minute and each
        // school is processed only once its own school end + 1 hour has passed.
        $schedule->command('staff-attendance:finalize')->everyMinute()->withoutOverlapping();

        // Raw punch rows are cleared once older than the retention window.
        // Finalized attendance is never deleted.
        $schedule->command('staff-attendance:purge-punches')->monthlyOn(1, '01:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
