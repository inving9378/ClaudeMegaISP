<?php

namespace App\Modules\Addons\VoIP\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MegaVoz Fase 5 — importa a `voip_queue_log` las líneas nuevas del `queue_log`
 * nativo de Asterisk. Tail-ea por OFFSET DE BYTES (no por línea, y no relee el
 * archivo completo cada vez) — el offset se persiste en un JSON pequeño junto
 * al resto del estado de MegaISP.
 *
 * Detecta rotación de log: si el archivo actual es MÁS CHICO que el offset
 * guardado, es que rotó (o se truncó) — se relee desde cero en vez de fallar
 * o perderse eventos.
 */
class ImportarQueueLogCommand extends Command
{
    protected $signature = 'megavoz:importar-queue-log';

    protected $description = 'Importa líneas nuevas de queue_log (app_queue) a voip_queue_log para el tablero de KPIs de MegaVoz';

    public function handle(): int
    {
        $path = config('voip.queue_log_path', '/var/log/asterisk/queue_log');

        if (! is_readable($path)) {
            $this->error("[megavoz:importar-queue-log] No se puede leer {$path}.");
            return self::FAILURE;
        }

        $stateFile = storage_path('app/megavoz-queue-log-offset.json');
        $state     = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];
        $offset    = (int) ($state['offset'] ?? 0);

        $size = filesize($path);
        if ($size === false) {
            $this->error("[megavoz:importar-queue-log] No se pudo leer el tamaño de {$path}.");
            return self::FAILURE;
        }
        if ($size < $offset) {
            $this->line('[megavoz:importar-queue-log] El log rotó — releyendo desde el inicio.');
            $offset = 0;
        }
        if ($size === $offset) {
            $this->info('[megavoz:importar-queue-log] Sin líneas nuevas.');
            return self::SUCCESS;
        }

        $fh = fopen($path, 'r');
        fseek($fh, $offset);

        $rows = [];
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line);
            if (count($parts) < 5) {
                continue; // línea incompleta (corte a media escritura) — se reintenta el offset viejo
            }

            [$ts, $callid, $queuename, $agent, $event] = array_slice($parts, 0, 5);

            $rows[] = [
                'ts'         => date('Y-m-d H:i:s', (int) $ts),
                'callid'     => $callid === 'NONE' ? null : $callid,
                'queuename'  => $queuename === 'NONE' ? null : $queuename,
                'agent'      => $agent === 'NONE' ? null : $agent,
                'event'      => $event,
                'data1'      => $parts[5] ?? null,
                'data2'      => $parts[6] ?? null,
                'data3'      => $parts[7] ?? null,
                'data4'      => $parts[8] ?? null,
                'data5'      => $parts[9] ?? null,
                'created_at' => now(),
            ];
        }

        $nuevoOffset = ftell($fh);
        fclose($fh);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('voip_queue_log')->insert($chunk);
        }

        if (! is_dir(dirname($stateFile))) {
            @mkdir(dirname($stateFile), 0755, true);
        }
        file_put_contents($stateFile, json_encode(['offset' => $nuevoOffset, 'path' => $path]));

        $this->info('[megavoz:importar-queue-log] ' . count($rows) . ' evento(s) importado(s).');
        return self::SUCCESS;
    }
}
