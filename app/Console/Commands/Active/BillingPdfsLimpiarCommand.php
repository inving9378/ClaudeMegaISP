<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\ClasificadorPdfsFacturacion as C;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * #511 (decisión de Irving 2026-10-16): borra PDFs de TRABAJO viejos de facturación. Solo dos
 * categorías del inventario y solo si tienen más de --dias (90 por defecto):
 *  - tickets que no se envían;
 *  - PDFs ya enviados cuya copia exacta está en evidencias (FAC-05).
 * NUNCA toca evidencias (enviados/), recibos (los descarga la app), lo que está en espera, huérfanos
 * ni cancelados. Modo prueba por defecto; con --ejecutar borra y deja la lista de lo borrado en
 * storage/app/billing/limpiezas/. Lo corre Irving en producción. Los PDFs de trabajo se pueden
 * regenerar (BillingDocumentService::regenerar); las evidencias no se tocan.
 */
class BillingPdfsLimpiarCommand extends Command
{
    protected $signature = 'billing:pdfs-limpiar
                            {--dias=90 : Antigüedad mínima en días}
                            {--limite=0 : Máximo de archivos a borrar en esta corrida (0 = sin límite)}
                            {--ejecutar : Borrar de verdad (sin esto, solo lista)}';

    protected $description = 'Borra PDFs de trabajo viejos de facturación (tickets no enviables y enviados con copia en evidencias); prueba por defecto';

    public const BORRABLES = [C::TICKET_NO_ENVIABLE, C::ENVIADO_CON_COPIA];

    public function handle(C $clasificador): int
    {
        $dias = max(30, (int) $this->option('dias'));
        $limite = max(0, (int) $this->option('limite'));
        $ejecutar = (bool) $this->option('ejecutar');
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));

        $elegidos = [];
        $clasificador->recorrer($dias, function (string $archivo, int $bytes, string $categoria, bool $viejo) use (&$elegidos, $limite) {
            if ($viejo && in_array($categoria, self::BORRABLES, true) && ($limite === 0 || count($elegidos) < $limite)) {
                $elegidos[] = ['archivo' => $archivo, 'bytes' => $bytes, 'categoria' => $categoria];
            }
        });

        $mb = number_format(array_sum(array_column($elegidos, 'bytes')) / 1048576, 1);
        $this->info(($ejecutar ? 'Borrando' : 'MODO PRUEBA — se borrarían') . ': ' . count($elegidos) . " archivos ({$mb} MB) con más de {$dias} días.");
        foreach (array_count_values(array_column($elegidos, 'categoria')) as $cat => $n) {
            $this->line("  · {$cat}: {$n}");
        }

        if (! $ejecutar || ! $elegidos) {
            $this->line($ejecutar ? 'Nada que borrar.' : 'Nada se borró. Para borrar: --ejecutar');

            return self::SUCCESS;
        }

        $borrados = [];
        foreach ($elegidos as $e) {
            if (str_contains($e['archivo'], '/enviados/')) {
                continue; // defensa extra: jamás una evidencia
            }
            if ($disco->delete($e['archivo'])) {
                $borrados[] = $e;
            }
        }
        $lista = 'billing/limpiezas/pdfs-' . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
        Storage::disk('local')->put($lista, json_encode(['dias' => $dias, 'ejecutado_at' => now()->toIso8601String(), 'borrados' => $borrados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Log::info('[billing:pdfs-limpiar] PDFs de trabajo borrados', ['borrados' => count($borrados), 'dias' => $dias, 'lista' => $lista]);
        $this->info('Borrados: ' . count($borrados) . ". Lista: storage/app/{$lista}");

        return self::SUCCESS;
    }
}
