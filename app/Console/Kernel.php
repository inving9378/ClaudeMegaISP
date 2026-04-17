<?php

namespace App\Console;

use App\Http\Repository\CommandConfigRepository;
use App\Http\Repository\FrequencyCommandRepository;
use App\Models\BillingReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $commandConfigRepository = new CommandConfigRepository();
        $commandConfigs = $commandConfigRepository->getAllCommandActive();
        $currentHour = now()->format('H:i');
        foreach ($commandConfigs as $commandConfig) {
            $frequencyCommandRepository = new FrequencyCommandRepository();
            $frequency = $frequencyCommandRepository->getNameFrequencyFilterById($commandConfig->frequency_id);
            $timeExecution = $commandConfig->execution_time;
            if ($timeExecution && $timeExecution != $currentHour) continue;
            $schedule->command($commandConfig->process_name)->$frequency($timeExecution)->withoutOverlapping();
        }
        /*  $schedule->command('app:server-status-command')->everyMinute();
        $schedule->command('app:reminder-payment-command')->dailyAt('03:00');
        $schedule->command('app:send-all-emails-command')->everyFiveMinutes(); */

        $schedule->command('invoice:create-proformas')->dailyAt('03:00')->withoutOverlapping();
        $schedule->command('app:mikrotik-sync-command')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('mikrotik:sync-consumption')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('mikrotik:sync-ping')->everyFiveMinutes()->withoutOverlapping();

        //Comandos OLT
        $schedule->command('smartolt:sync-inventory')->dailyAt('05:00')->withoutOverlapping();
        $schedule->command('smartolt:sync-critical')->everyTenMinutes()->withoutOverlapping();
        // Archivar activity_logs con más de 90 días a la BD meganet_logs
        $schedule->command('activitylog:archive --days=90')->dailyAt('02:00')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands/Active');
        $this->load(__DIR__ . '/Commands/Scripts');
        $this->load(__DIR__ . '/Commands/Olts');

        require base_path('routes/console.php');
    }
}
