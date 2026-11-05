<?php

namespace App\Modules\Addons\Talento\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UNI-T E1 Fase 3: marca (sin borrar) las órdenes antiguas de cuentas demo con es_prueba=1.
 * Sin banderas solo simula. --apply respalda las filas afectadas (JSON) y marca; --revertir restaura desde ese JSON.
 */
class MarcarOrdenesPruebaCommand extends Command
{
    protected $signature = 'talento:marcar-ordenes-prueba {--apply : Marca las órdenes (por defecto solo simula)} {--revertir= : Ruta del JSON de respaldo a revertir}';

    protected $description = 'Marca como prueba las órdenes antiguas (talento_work_orders) de cuentas demo_* (simula por defecto).';

    private const DIR = '/var/backups/mysql/preservados/uni';

    public function handle(): int
    {
        if ($ruta = $this->option('revertir')) {
            return $this->revertir($ruta);
        }

        $ids = $this->candidatas();
        $this->info('Órdenes antiguas totales: ' . DB::table('talento_work_orders')->count());
        $this->info('Candidatas a marcar (cuentas demo_*, aún sin marcar): ' . count($ids));

        if (! $this->option('apply')) {
            $this->line('Simulación: no se escribió nada. Usa --apply para marcar.');
            return self::SUCCESS;
        }
        if (! $ids) {
            return self::SUCCESS;
        }

        $ruta = self::DIR . '/ordenes-prueba-' . now()->format('Ymd-His') . '.json';
        if (! is_dir(self::DIR) || ! is_writable(self::DIR)) {
            $this->error('No se puede escribir el respaldo en ' . self::DIR . '; no se marcó nada.');
            return self::FAILURE;
        }
        file_put_contents($ruta, json_encode(['ids' => $ids], JSON_PRETTY_PRINT));

        $n = DB::table('talento_work_orders')->whereIn('id', $ids)->update(['es_prueba' => 1]);
        $this->info("Marcadas: {$n}. Respaldo: {$ruta}");
        return self::SUCCESS;
    }

    /** @return int[] */
    private function candidatas(): array
    {
        return DB::table('talento_work_orders as w')
            ->join('talento_colaboradores as c', 'c.id', '=', 'w.colaborador_id')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('u.login_user', 'like', 'demo\\_%')
            ->where('w.es_prueba', 0)
            ->pluck('w.id')->map(fn ($i) => (int) $i)->all();
    }

    private function revertir(string $ruta): int
    {
        $ids = json_decode((string) @file_get_contents($ruta), true)['ids'] ?? null;
        if (! is_array($ids)) {
            $this->error('Respaldo ilegible: ' . $ruta);
            return self::FAILURE;
        }
        $n = DB::table('talento_work_orders')->whereIn('id', $ids)->update(['es_prueba' => 0]);
        $this->info("Revertidas: {$n}.");
        return self::SUCCESS;
    }
}
