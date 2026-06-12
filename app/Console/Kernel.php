<?php

namespace App\Console;

use App\Modules\Core\Configuracion\Repositories\CommandConfigRepository;
use App\Modules\Core\Configuracion\Repositories\FrequencyCommandRepository;
use App\Modules\Core\Configuracion\Models\BillingReminder;
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

//        $schedule->command('invoice:create-proformas')->dailyAt('03:00')->withoutOverlapping();
//        $schedule->command('billing:send-pending-notifications')->everyFifteenMinutes()->withoutOverlapping();
//        $schedule->command('app:mikrotik-sync-command')->everyFiveMinutes()->withoutOverlapping();
//        $schedule->command('mikrotik:sync-consumption')->everyTenMinutes()->withoutOverlapping();
//        $schedule->command('mikrotik:sync-ping')->everyFiveMinutes()->withoutOverlapping();

        //Comandos OLT
        $schedule->command('smartolt:sync-inventory')->dailyAt('05:00')->withoutOverlapping();
        $schedule->command('smartolt:sync-critical')->everyTenMinutes()->withoutOverlapping();
        // Huawei Telnet scan — una sesión por corrida, TTL lock 900s (scan ≈7-10 min).
        // Si duration_s > 8 min en los logs, subir el intervalo a 15 min y ajustar withoutOverlapping.
        $schedule->command('gestionred:sync-huawei')->everyTenMinutes()->withoutOverlapping(15);
        // Revertir promos vencidas: hourly para revertir el mismo día del vencimiento con reintentos automáticos
        $schedule->command('smartolt:sync-promotions')->hourly()->withoutOverlapping()->onOneServer();
        // Revisión diaria 6 AM: reversiones residuales + reconciliación BD vs ONU + aviso de promos que vencen hoy
        $schedule->command('promociones:revision-diaria')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
        // Archivar activity_logs con más de 90 días a la BD meganet_logs
        $schedule->command('activitylog:archive --days=90')->dailyAt('02:00')->withoutOverlapping();

        // Regeneración semanal del manual de usuario vía Claude API
        $schedule->command('manual:regenerate')->weekly()->sundays()->at('03:00')->withoutOverlapping();

        // Programa de Embajadores: acreditar comisiones vencidas, expirar y alertar recompensas
        $schedule->job(new \App\Jobs\Referrals\ApplyReferralCommissions)->dailyAt('03:15')->withoutOverlapping(60)->onOneServer();
        $schedule->job(new \App\Jobs\Referrals\ExpireReferralRewards)->dailyAt('03:30')->withoutOverlapping(30)->onOneServer();
        $schedule->job(new \App\Jobs\Referrals\WarnExpiringRewards)->dailyAt('09:00')->withoutOverlapping(30)->onOneServer()->name('embajadores:warn-expiring-rewards');
        // Stats diarias del programa de embajadores (snapshot del día anterior)
        $schedule->job(new \App\Jobs\Referrals\CalculateDailyStats)->dailyAt('02:30')->withoutOverlapping(30)->onOneServer()->name('embajadores:daily-stats');

        // Deploy remoto — ejecuta los DeploymentLogs pendientes creados por el webhook.
        // Trigger confiable vía el cron por minuto (no depende de fastcgi/colas/workers).
        // En foreground a propósito: en este host el spawn en background falla silencioso.
        $schedule->command('remote:deploy-run-pending')
            ->everyMinute()
            ->withoutOverlapping();

        // Marketing Publicador Multicanal (Fase 5)
        $schedule->command('marketing:publish-due')->everyMinute()->withoutOverlapping();
        $schedule->job(new \App\Modules\Addons\Marketing\Jobs\RefreshMetaTokensJob())->dailyAt('03:45')->withoutOverlapping(30)->name('marketing:refresh-meta-tokens');
        $schedule->job(new \App\Modules\Addons\Marketing\Jobs\FetchAllMetricsJob())->everyFourHours()->withoutOverlapping(60)->name('marketing:fetch-all-metrics');

        // Flotas Fase 6.1 — Mantenimiento de suscripciones SaaS (trials, expiración, conteo vencidos)
        $schedule->command('flotas:check-subscriptions')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->onOneServer();

        // Flotas Fase 4 — Alertas de vencimiento de documentos (cron diario 08:00)
        $schedule->command('flotas:check-document-expirations')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->onOneServer();

        // Talento Fase 6b — Alertas de vencimiento de credenciales (cron diario 07:00)
        $schedule->command('talento:check-credential-expirations')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->onOneServer();

        // War Room — snapshot diario de KPIs al cierre del día (23:55)
        $schedule->command('warroom:refresh --skip-insights')
            ->dailyAt('23:55')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->name('warroom:refresh-snapshot');

        // CobranzaBlaster (Fase 6) — dispara el blast cada 5 minutos en campañas activas
        $schedule->call(function () {
            \App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana::activa()->get()
                ->each(fn ($campana) => \App\Modules\Addons\CobranzaBlaster\Jobs\BlastCampanaJob::dispatch($campana->id));
        })->everyFiveMinutes()->name('cobranza:blast-activas')->withoutOverlapping(10);
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands/Active');
        $this->load(__DIR__ . '/Commands/Scripts');
        $this->load(__DIR__ . '/Commands/Olts');

        require base_path('routes/console.php');
    }
}
