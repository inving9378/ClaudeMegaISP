<?php

namespace App\Console\Commands\Active;

use App\Modules\Core\Clientes\Services\ClienteSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #9990835 (Fase 2 de #9990833) — backfill de
 * client_additional_information.serie_equipo / serie_equipo_norm /
 * serie_equipo_origen a partir del SN real del equipo.
 *
 * Jerarquía de fuente (D4, ya decidida en el item — no re-evaluar):
 *   1) olt_onus.sn (si el cliente tiene fila ahí, es la fuente de verdad)
 *   2) CPE inalámbrico — no existe integración hoy, se omite (no se inventa)
 *   3) client_additional_information.modem_sn — último recurso, esperado
 *      para clientes WiFi (no es una excepción que no tengan fila en OLT)
 *
 * modem_sn es SOLO LECTURA aquí (D3): este comando NUNCA lo escribe.
 * Los valores que no normalizan a 16 hex van al CSV de excepciones (D5),
 * sin transformación forzada.
 */
class NormalizarSeriesClientesCommand extends Command
{
    protected $signature = 'clientes:normalizar-series
        {--dry-run : No escribe nada en la BD, solo cuenta y genera el CSV de excepciones}
        {--csv= : Ruta del CSV de excepciones (cliente_id, valor_original, origen, razon)}
        {--chunk=500 : Tamaño de chunk sobre la tabla clients}';

    protected $description = 'Backfill de serie_equipo/serie_equipo_norm/serie_equipo_origen en client_additional_information (item #9990835). Idempotente, nunca toca modem_sn.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $csvPath = $this->option('csv');

        $this->info($dryRun ? 'Modo DRY-RUN — no se escribe nada.' : 'Modo APLICAR — se escriben serie_equipo/serie_equipo_norm/serie_equipo_origen.');

        $csvHandle = null;
        if ($csvPath) {
            $csvHandle = fopen($csvPath, 'w');
            fputcsv($csvHandle, ['cliente_id', 'valor_original', 'origen', 'razon']);
        }

        $totalClientes = 0;
        $sinDato = 0;
        $conCandidato = 0;
        $normalizados = 0;
        $excepciones = 0;

        // Un olt_onus por cliente (el de menor id con sn no vacío) — evita
        // duplicar filas del join cuando un cliente tiene varias ONUs.
        $oltPick = DB::table('olt_onus')
            ->select('client_id', DB::raw('MIN(id) as min_id'))
            ->whereNotNull('client_id')
            ->whereNotNull('sn')
            ->where('sn', '!=', '')
            ->groupBy('client_id');

        $query = DB::table('clients')
            ->leftJoinSub($oltPick, 'onu_pick', function ($join) {
                $join->on('onu_pick.client_id', '=', 'clients.id');
            })
            ->leftJoin('olt_onus as onu', 'onu.id', '=', 'onu_pick.min_id')
            ->leftJoin('client_additional_information as cai', function ($join) {
                $join->on('cai.client_id', '=', 'clients.id')
                    ->whereNull('cai.deleted_at');
            })
            ->whereNull('clients.deleted_at')
            ->select('clients.id as client_id', 'cai.client_id as cai_client_id', 'cai.modem_sn as modem_sn', 'onu.sn as olt_sn')
            ->orderBy('clients.id');

        $query->chunkById($chunkSize, function ($rows) use (
            $dryRun,
            $csvHandle,
            &$totalClientes,
            &$sinDato,
            &$conCandidato,
            &$normalizados,
            &$excepciones
        ) {
            foreach ($rows as $row) {
                $totalClientes++;

                if (!empty($row->olt_sn)) {
                    $candidato = $row->olt_sn;
                    $origen = 'olt';
                } elseif (!empty($row->modem_sn)) {
                    $candidato = $row->modem_sn;
                    $origen = 'manual';
                } else {
                    $sinDato++;
                    continue;
                }

                $conCandidato++;
                $normalizado = ClienteSearchService::normalizarSn($candidato);

                if ($normalizado === null || strlen($normalizado) !== 16 || !ctype_xdigit($normalizado)) {
                    $excepciones++;
                    if ($csvHandle) {
                        fputcsv($csvHandle, [$row->client_id, $candidato, $origen, 'no normaliza a 16 hex']);
                    }
                    continue;
                }

                // Existencia de la fila se decide por el JOIN, NUNCA por las filas
                // afectadas del UPDATE: MySQL reporta 0 afectadas cuando los valores
                // ya son idénticos (2a corrida), lo que rompía la idempotencia (#5) —
                // marcaba clientes correctos como falsa excepción "sin fila".
                if ($row->cai_client_id === null) {
                    $excepciones++;
                    if ($csvHandle) {
                        fputcsv($csvHandle, [$row->client_id, $candidato, $origen, 'sin fila client_additional_information']);
                    }
                    continue;
                }

                if ($dryRun) {
                    $normalizados++;
                    continue;
                }

                DB::table('client_additional_information')
                    ->where('client_id', $row->client_id)
                    ->update([
                        'serie_equipo' => ClienteSearchService::formatoCorto($normalizado),
                        'serie_equipo_norm' => $normalizado,
                        'serie_equipo_origen' => $origen,
                        'updated_at' => now(),
                    ]);

                $normalizados++;
            }
        }, 'clients.id', 'client_id');

        if ($csvHandle) {
            fclose($csvHandle);
        }

        $this->newLine();
        $this->info("Clientes revisados: {$totalClientes} | Sin dato de SN (nada que hacer): {$sinDato} | Con candidato: {$conCandidato}");
        $this->info(($dryRun ? 'Normalizarían' : 'Normalizados') . ": {$normalizados} | Excepciones (a CSV): {$excepciones}");
        if ($csvPath) {
            $this->info("CSV de excepciones: {$csvPath}");
        }

        return 0;
    }
}
