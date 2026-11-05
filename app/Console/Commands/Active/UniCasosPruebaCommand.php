<?php

namespace App\Console\Commands\Active;

use Database\Seeders\UniPruebaSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * UNI-T A2b Fase 4 — casos de prueba UNI-PRUEBA en DEV: --dry-run lista, --crear siembra
 * (con respaldo previo de las tablas tocadas), --borrar elimina solo filas con el prefijo.
 * Solo APP_ENV=local. Sin pagos, facturas ni ledger.
 */
class UniCasosPruebaCommand extends Command
{
    protected $signature = 'uni:casos-prueba {--crear} {--borrar} {--dry-run}';

    protected $description = 'UNI-T: crea/borra casos de prueba UNI-PRUEBA en DEV con respaldo previo';

    private const TABLAS = [
        'users', 'model_has_roles', 'talento_colaboradores', 'talento_work_order_types', 'talento_work_orders',
        'talento_work_order_media', 'talento_work_order_signatures', 'talento_activity_types', 'talento_projects',
        'talento_project_activities', 'talento_project_activity_reports', 'talento_activity_report_participants',
        'tickets', 'sellers', 'clients', 'referrals', 'talento_liquidations',
    ];

    private const DIR_RESPALDO = '/var/backups/mysql/preservados/uni';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Solo corre en DEV (APP_ENV=local).');

            return 1;
        }
        if (! $this->option('crear') && ! $this->option('borrar')) {
            $this->warn('Indica --crear o --borrar (con --dry-run para simular).');

            return 1;
        }

        $antes = $this->conteos();
        $accion = $this->option('crear') ? 'crear' : 'borrar';

        if ($this->option('dry-run')) {
            $this->info("DRY-RUN ({$accion}): no se escribe nada. Filas UNI-PRUEBA actuales:");
            foreach ($this->existentes() as $t => $n) {
                $this->line(sprintf('  %-42s %d', $t, $n));
            }
            $this->line($accion === 'crear' ? 'Crearía el set completo del seeder (idempotente).' : 'Borraría solo las filas listadas arriba.');

            return 0;
        }

        if (! $this->respaldar()) {
            return 1;
        }

        DB::transaction(function () use ($accion) {
            if ($accion === 'crear') {
                UniPruebaSeeder::$permitirDev = true;
                app(UniPruebaSeeder::class)->run();
            } else {
                $this->borrar();
            }
        });

        $despues = $this->conteos();
        $this->table(['tabla', 'antes', 'después'], collect($antes)->map(fn ($n, $t) => [$t, $n, $despues[$t]])->values()->all());

        return 0;
    }

    private function conteos(): array
    {
        return collect(self::TABLAS)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    private function ids(): array
    {
        $p = UniPruebaSeeder::PREFIJO;
        $users = DB::table('users')->where('login_user', 'like', "{$p}-%")->pluck('id')->all();
        $colabs = DB::table('talento_colaboradores')->whereIn('user_id', $users)->pluck('id')->all();
        $ordenes = DB::table('talento_work_orders')->where('notes', 'like', "{$p} %")->pluck('id')->all();
        $proyectos = DB::table('talento_projects')->where('name', 'like', "{$p} %")->pluck('id')->all();
        $acts = DB::table('talento_project_activities')->whereIn('project_id', $proyectos)->pluck('id')->all();
        $reportes = DB::table('talento_project_activity_reports')->whereIn('project_activity_id', $acts)->pluck('id')->all();
        $clientes = DB::table('clients')->where('referred_by_code', 'like', "{$p}-%")->pluck('id')->all();

        return compact('users', 'colabs', 'ordenes', 'proyectos', 'acts', 'reportes', 'clientes');
    }

    private function existentes(): array
    {
        $i = $this->ids();
        $p = UniPruebaSeeder::PREFIJO;

        return [
            'users' => count($i['users']),
            'talento_colaboradores' => count($i['colabs']),
            'talento_work_orders' => count($i['ordenes']),
            'talento_work_order_media' => DB::table('talento_work_order_media')->whereIn('work_order_id', $i['ordenes'])->count(),
            'talento_work_order_signatures' => DB::table('talento_work_order_signatures')->whereIn('work_order_id', $i['ordenes'])->count(),
            'talento_work_order_types' => DB::table('talento_work_order_types')->where('name', 'like', "{$p} %")->count(),
            'talento_activity_types' => DB::table('talento_activity_types')->where('name', 'like', "{$p} %")->count(),
            'talento_projects' => count($i['proyectos']),
            'talento_project_activities' => count($i['acts']),
            'talento_project_activity_reports' => count($i['reportes']),
            'talento_activity_report_participants' => DB::table('talento_activity_report_participants')->whereIn('report_id', $i['reportes'])->count(),
            'tickets' => DB::table('tickets')->where('topic', 'like', "{$p} %")->count(),
            'sellers' => DB::table('sellers')->whereIn('user_id', $i['users'])->count(),
            'clients' => count($i['clientes']),
            'referrals' => DB::table('referrals')->whereIn('embajador_id', $i['clientes'])->count(),
            'talento_liquidations' => DB::table('talento_liquidations')->whereIn('colaborador_id', $i['colabs'])->count(),
        ];
    }

    private function borrar(): void
    {
        $i = $this->ids();
        $p = UniPruebaSeeder::PREFIJO;

        DB::table('talento_activity_report_participants')->whereIn('report_id', $i['reportes'])->delete();
        DB::table('talento_project_activity_reports')->whereIn('id', $i['reportes'])->delete();
        DB::table('talento_project_activities')->whereIn('id', $i['acts'])->delete();
        DB::table('talento_projects')->whereIn('id', $i['proyectos'])->delete();
        DB::table('talento_activity_types')->where('name', 'like', "{$p} %")->delete();
        DB::table('talento_work_order_media')->whereIn('work_order_id', $i['ordenes'])->delete();
        DB::table('talento_work_order_signatures')->whereIn('work_order_id', $i['ordenes'])->delete();
        DB::table('talento_work_orders')->whereIn('id', $i['ordenes'])->delete();
        DB::table('talento_work_order_types')->where('name', 'like', "{$p} %")->delete();
        DB::table('talento_liquidations')->whereIn('colaborador_id', $i['colabs'])->delete();
        DB::table('tickets')->where('topic', 'like', "{$p} %")->delete();
        DB::table('referrals')->whereIn('embajador_id', $i['clientes'])->delete();
        DB::table('clients')->whereIn('id', $i['clientes'])->delete();
        DB::table('sellers')->whereIn('user_id', $i['users'])->delete();
        DB::table('talento_colaboradores')->whereIn('id', $i['colabs'])->update(['supervisor_id' => null]);
        DB::table('talento_colaboradores')->whereIn('id', $i['colabs'])->delete();
        DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->whereIn('model_id', $i['users'])->delete();
        DB::table('users')->whereIn('id', $i['users'])->delete();
    }

    private function respaldar(): bool
    {
        if (! is_dir(self::DIR_RESPALDO) && ! @mkdir(self::DIR_RESPALDO, 0750, true)) {
            $this->error('No se pudo crear ' . self::DIR_RESPALDO);

            return false;
        }
        $c = config('database.connections.mysql');
        $archivo = self::DIR_RESPALDO . '/uni-casos-' . date('Ymd-His') . '.sql';
        $proc = new Process(array_merge(
            ['mysqldump', '--single-transaction', '--host=' . ($c['host'] ?? 'localhost'), '--user=' . $c['username'], '--result-file=' . $archivo, $c['database']],
            self::TABLAS
        ), null, ['MYSQL_PWD' => $c['password']]);
        $proc->setTimeout(300)->run();
        if (! $proc->isSuccessful() || ! is_file($archivo) || filesize($archivo) === 0) {
            $this->error('Respaldo falló; no se escribe nada. ' . $proc->getErrorOutput());

            return false;
        }
        $this->info("Respaldo: {$archivo}");

        return true;
    }
}
