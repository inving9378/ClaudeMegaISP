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

        $schedule->command('invoice:create-proformas')->dailyAt('03:00')->withoutOverlapping();
        $schedule->command('billing:send-pending-notifications')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('auditoria:minar-bitacora')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('app:mikrotik-sync-command')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('mikrotik:sync-consumption')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('mikrotik:sync-ping')->everyFiveMinutes()->withoutOverlapping();
        // Item #676: cada 30 min, ventana mayor a los ~18 min de los 5 tries internos de
        // CreateClientWithServiceJob (ver ese job) — evita re-despachar mientras un intento
        // previo sigue en su propio ciclo de backoff.
        $schedule->command('mikrotik:reintentar-sync')->everyThirtyMinutes()->withoutOverlapping();

        //Comandos OLT
        $schedule->command('smartolt:sync-inventory')->dailyAt('05:00')->withoutOverlapping();
        $schedule->command('smartolt:sync-clients-with-ont')->dailyAt('05:30')->withoutOverlapping();
        $schedule->command('smartolt:sync-critical')->everyTenMinutes()->withoutOverlapping();
        // Huawei Telnet scan — una sesión por corrida, TTL lock 900s (scan ≈7-10 min).
        // Si duration_s > 8 min en los logs, subir el intervalo a 15 min y ajustar withoutOverlapping.
        $schedule->command('gestionred:sync-huawei')->everyTenMinutes()->withoutOverlapping(15);
        // Revertir promos vencidas: hourly para revertir el mismo día del vencimiento con reintentos automáticos
        $schedule->command('smartolt:sync-promotions')->hourly()->withoutOverlapping()->onOneServer();
        // Revisión diaria 6 AM: reversiones residuales + reconciliación BD vs ONU + aviso de promos que vencen hoy
        $schedule->command('promociones:revision-diaria')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
        // Backup diario de la base de datos (mysqldump + gzip, retención 14 días)
        $schedule->command('backup_db:process')->dailyAt('02:00')->withoutOverlapping();

        // Archivar activity_logs con más de 90 días a la BD meganet_logs
        $schedule->command('activitylog:archive --days=90')->dailyAt('02:00')->withoutOverlapping();

        // #921 Fase 2 / #957 — reactiva items del Roadmap con agendado_para ya vencido (vuelven al pool).
        $schedule->command('circuito:reactivar-agendados')->dailyAt('00:05')->withoutOverlapping();

        // MR-32 (#971) — LIBERADOR EN CASCADA ACOTADO de la épica MAPA DE RED (#936).
        //
        // Libera el freno del SIGUIENTE item de MR-01→MR-07 sólo cuando el anterior cerró limpio, y
        // se autodesactiva al llegar al techo #943. Nunca pasa de ahí: de MR-08 en adelante empieza
        // el modelo de datos, donde una decisión mal tomada se arrastra a diez items.
        //
        // Sólo mueve `excluir_pool_automatico` de true a false. No despacha, no cierra items y no
        // vuelve a frenar nada. Verifica en cada vuelta que la red de guards de datos siga vigente
        // (GuardBaseDePruebas + phpunit.xml en _test + MigrationGuardService) y se detiene si falta.
        $schedule->command('circuito:liberar-cascada-mapa-red')
            ->everyTenMinutes()
            ->withoutOverlapping();

        // #634 — el freno del CLASIFICADOR caduca solo a los N días sin confirmar (2A.4), pero nada
        // corría `circuito:re-triage --apply`: los frenos se quedaban bloqueados para siempre en vez
        // de liberarse. El freno HUMANO nunca caduca (el propio comando es fail-closed sobre eso),
        // así que correrlo aquí no revoca ninguna decisión de Irving, sólo vence consejos vencidos.
        $schedule->command('circuito:re-triage --apply')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/circuito-retriage.log'));

        // Pieza 1b (#765, sub-item de #672) — INVOCADOR REAL del backfill de #764. Reconciliación
        // diaria de `torre_frontera_dura_eventos` sobre TODO `roadmap_items.log` (idempotente, ver
        // BackfillFronteraDuraEventosCommand). Cubre las dos vías que la captura en vivo de
        // `TorreAutomationPolicy::estadoInicial()` no ve (excluidas a propósito de su docblock):
        // el alta directa de un humano (`RoadmapController::store`) y la vía externa/MCP
        // (`RoadmapCircuitoService::guard()`). Sin este `schedule`, el comando quedaría igual que
        // #902 (`MedirValvulaContextoCommand`): escrito y nunca invocado por nadie.
        $schedule->command('circuito:backfill-frontera-dura-eventos')
            ->dailyAt('03:35')
            ->withoutOverlapping()
            ->onOneServer();

        // Regeneración semanal del manual de usuario vía Claude API
        $schedule->command('manual:regenerate')->weekly()->sundays()->at('03:00')->withoutOverlapping();

        // Programa de Embajadores: acreditar comisiones vencidas, expirar y alertar recompensas
        $schedule->job(new \App\Jobs\Referrals\ApplyReferralCommissions)->dailyAt('03:15')->withoutOverlapping(60)->onOneServer();
        $schedule->job(new \App\Jobs\Referrals\ExpireReferralRewards)->dailyAt('03:30')->withoutOverlapping(30)->onOneServer();
        $schedule->job(new \App\Jobs\Referrals\WarnExpiringRewards)->dailyAt('09:00')->withoutOverlapping(30)->onOneServer()->name('embajadores:warn-expiring-rewards');
        // Stats diarias del programa de embajadores (snapshot del día anterior)
        $schedule->job(new \App\Jobs\Referrals\CalculateDailyStats)->dailyAt('02:30')->withoutOverlapping(30)->onOneServer()->name('embajadores:daily-stats');
        // Respaldo de auto-sanación: recompute de contadores tras aplicar comisiones
        // (03:15) y expirar recompensas (03:30) — captura cualquier drift residual.
        $schedule->command('embajadores:rebuild-kpis')->dailyAt('04:00')->withoutOverlapping()->onOneServer();

        // Portal de Pago — recurrencia asistida (genera ligas del mes, NO auto-débito).
        // Gateado por config('pagos.recurrentes_cron_enabled') (env PAGOS_RECURRENTES_CRON_ENABLED,
        // false por default — mismo patrón que domiciliacion.cobro_live_enabled). El schedule ya
        // queda listo con el deploy; activarlo en .198 es flip de env, decisión explícita de Irving
        // (item roadmap #163, q1: primero dry-run en dev, recién después activar en producción).
        if (config('pagos.recurrentes_cron_enabled')) {
            $schedule->command('pagos:enviar-recurrentes')->daily()->withoutOverlapping();
        }

        // Deploy remoto — ejecuta los DeploymentLogs pendientes creados por el webhook.
        // Se desactiva en consumidoras (GITHUB_UPDATES_ENABLED=true): en esas instancias
        // el único trigger de actualización es el botón "Actualizar ahora" del banner.
        if (!config('updates.enabled')) {
            $schedule->command('remote:deploy-run-pending')
                ->everyMinute()
                ->withoutOverlapping();
        }

        // Detección de versión nueva desde GitHub Releases (solo en consumidoras).
        if (config('updates.enabled')) {
            $schedule->command('updates:check-github')
                ->everyThirtyMinutes()
                ->withoutOverlapping();
        }

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

        // Domiciliación — cobro recurrente mensual; corre diario a las 10:00 para reintentos
        // Solo opera si domiciliacion.cobro_live_enabled=true Y domiciliacion_habilitada=true
        // (self-gated en el command; independiente de OPENPAY_SANDBOX — ver config/domiciliacion.php).
        $schedule->command('domiciliacion:cobrar')
            ->dailyAt('10:00')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->name('domiciliacion:cobrar');

        // Item #627 — purga diaria de push tokens FCM sin actividad (>60 días)
        $schedule->command('push-tokens:purge')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->onOneServer();

        // DocumentacionCorporativa Fase 2b (item #735) — marca recordatorio_enviado_at
        // en pendientes vencidos o por vencer. Sólo marca; el envío real es aparte.
        $schedule->command('dc:pendientes-recordatorio')
            ->dailyAt('08:30')
            ->withoutOverlapping()
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands/Active');
        $this->load(__DIR__ . '/Commands/Scripts');
        $this->load(__DIR__ . '/Commands/Olts');
        $this->load(__DIR__ . '/Commands/Schema');
        $this->load(__DIR__ . '/Commands/Circuito');

        require base_path('routes/console.php');
    }
}
