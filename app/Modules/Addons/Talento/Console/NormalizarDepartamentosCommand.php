<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\DepartamentoCatalogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * UNI-T C4b: lleva el texto libre de talento_colaboradores.department al catálogo.
 * Sin banderas solo simula. --apply respalda (mysqldump + JSON id→valor) y escribe; --revertir restaura el JSON.
 */
class NormalizarDepartamentosCommand extends Command
{
    protected $signature = 'talento:normalizar-departamentos {--apply : Escribe los cambios (por defecto solo simula)} {--revertir= : Ruta del JSON de respaldo a restaurar}';

    protected $description = 'Normaliza el departamento de los colaboradores al catálogo (simula por defecto; --apply respalda y escribe; --revertir restaura).';

    public function handle(): int
    {
        if ($ruta = $this->option('revertir')) {
            return $this->revertir($ruta);
        }

        $plan = [];
        $sinEquivalencia = [];
        $yaCorrectos = 0;
        foreach (TalentoColaborador::query()->whereNotNull('department')->where('department', '!=', '')->get(['id', 'department']) as $c) {
            $destino = DepartamentoCatalogo::normalizar($c->department);
            if ($destino === null) {
                $sinEquivalencia[$c->department] = ($sinEquivalencia[$c->department] ?? 0) + 1;
            } elseif ($destino === $c->department) {
                $yaCorrectos++;
            } else {
                $plan[$c->id] = [$c->department, $destino];
            }
        }

        $this->info("Ya correctos: {$yaCorrectos} · A cambiar: " . count($plan) . ' · Sin equivalencia (se dejan igual): ' . array_sum($sinEquivalencia));
        $this->table(['Valor actual', 'Pasa a', 'Colaboradores'], collect($plan)->groupBy(fn ($p) => $p[0] . '|' . $p[1])
            ->map(fn ($g, $k) => [...explode('|', $k), $g->count()])->values()->all());
        foreach ($sinEquivalencia as $valor => $n) {
            $this->warn("Sin equivalencia: «{$valor}» ({$n})");
        }

        if (! $this->option('apply')) {
            $this->line('Simulación: no se escribió nada. Usa --apply tras la bitácora previa.');
            return self::SUCCESS;
        }
        if (! DepartamentoCatalogo::activo()) {
            $this->error('Interruptor UNIFICACION_DEPARTAMENTOS_CATALOGO_C4B apagado: no se aplica.');
            return self::FAILURE;
        }
        if (! $plan) {
            $this->info('Nada que cambiar.');
            return self::SUCCESS;
        }

        $dir = rtrim((string) config('unificacion.departamentos.respaldo_dir'), '/');
        if (! is_dir($dir) && ! @mkdir($dir, 0750, true)) {
            $this->error("No se pudo crear {$dir}; no se escribe sin respaldo.");
            return self::FAILURE;
        }
        $sello = date('Ymd-His');
        $json = "{$dir}/talento_colaboradores-department-{$sello}.json";
        $sql = "{$dir}/talento_colaboradores-{$sello}.sql";
        $cfg = config('database.connections.' . config('database.default'));

        $dump = new Process(['mysqldump', '--single-transaction', '--host=' . $cfg['host'], '--user=' . $cfg['username'], '--result-file=' . $sql, $cfg['database'], 'talento_colaboradores'], null, ['MYSQL_PWD' => (string) $cfg['password']]);
        $dump->run();
        if (! $dump->isSuccessful() || ! is_file($sql) || filesize($sql) === 0
            || file_put_contents($json, json_encode(array_map(fn ($p) => $p[0], $plan), JSON_UNESCAPED_UNICODE)) === false) {
            $this->error('Respaldo falló; no se escribió nada. ' . trim($dump->getErrorOutput()));
            return self::FAILURE;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $id => [, $destino]) {
                DB::table('talento_colaboradores')->where('id', $id)->update(['department' => $destino]);
            }
        });
        $this->info('Aplicado: ' . count($plan) . " filas. Respaldo: {$sql} · Reversión: php artisan talento:normalizar-departamentos --revertir={$json}");

        return self::SUCCESS;
    }

    private function revertir(string $ruta): int
    {
        $datos = is_file($ruta) ? json_decode(file_get_contents($ruta), true) : null;
        if (! is_array($datos)) {
            $this->error('Respaldo JSON ilegible.');
            return self::FAILURE;
        }
        DB::transaction(function () use ($datos) {
            foreach ($datos as $id => $valor) {
                DB::table('talento_colaboradores')->where('id', $id)->update(['department' => $valor]);
            }
        });
        $this->info('Revertido: ' . count($datos) . ' filas.');

        return self::SUCCESS;
    }
}
