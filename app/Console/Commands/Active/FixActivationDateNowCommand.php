<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Item #1148 (FR-H7 fase 2): corrige client_main_information.activation_date = 'now'
 * (valor literal inválido) a la fecha de alta (created_at). Dry-run por defecto.
 * Con --force escribe, tras guardar un CSV de respaldo (id, valor viejo, valor nuevo).
 */
class FixActivationDateNowCommand extends Command
{
    protected $signature = 'clientes:fix-activation-date-now {--force : Aplica la corrección (por defecto solo lista)}';

    protected $description = "Lista/corrige clientes con activation_date='now' usando created_at (dry-run por defecto)";

    public function handle(): int
    {
        $rows = DB::table('client_main_information')
            ->where('activation_date', 'now')
            ->whereNotNull('created_at')
            ->get(['id', 'client_id', 'activation_date', 'created_at']);

        $sinFecha = DB::table('client_main_information')
            ->where('activation_date', 'now')->whereNull('created_at')->count();

        $this->table(['id', 'client_id', 'viejo', 'nuevo'], $rows->map(fn ($r) => [
            $r->id, $r->client_id, $r->activation_date, substr($r->created_at, 0, 10),
        ])->all());
        $this->info("Corregibles: {$rows->count()} · sin created_at (se omiten): {$sinFecha}");

        if (!$this->option('force')) {
            $this->warn('DRY-RUN: no se escribió nada. Usa --force para aplicar.');
            return self::SUCCESS;
        }

        $dir = storage_path('app/respaldos');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $archivo = $dir . '/activation_date_now-' . date('Ymd-His') . '.csv';
        $fh = fopen($archivo, 'w');
        fputcsv($fh, ['id', 'client_id', 'valor_viejo', 'valor_nuevo']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r->id, $r->client_id, $r->activation_date, substr($r->created_at, 0, 10)]);
        }
        fclose($fh);
        $this->info("Respaldo: {$archivo}");

        $n = 0;
        DB::transaction(function () use ($rows, &$n) {
            foreach ($rows as $r) {
                $n += DB::table('client_main_information')
                    ->where('id', $r->id)->where('activation_date', 'now')
                    ->update(['activation_date' => substr($r->created_at, 0, 10)]);
            }
        });
        $this->info("Corregidos: {$n}");
        return self::SUCCESS;
    }
}
