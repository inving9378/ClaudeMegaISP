<?php

namespace App\Console\Commands\Active;

use App\Models\BillingDocumentoEvidencia;
use App\Models\BillingNotification;
use App\Services\Finance\Billing\EvidenciaDocumentoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * FAC-05 (item #510): inventario de los PDFs de facturación para decidir una política de retención
 * (la decisión es de Irving, FAC-05b). SOLO LECTURA: no borra, no mueve ni modifica nada.
 *
 * Clasifica cada archivo de billing.pdf_disk / billing.pdf_path_prefix:
 *  - EXCLUIDOS (nunca candidatos): evidencias enviadas (enviados/), recibos (los descarga la app
 *    de MegaFamilia), documentos de notificaciones en espera.
 *  - CANDIDATOS (con motivo): tickets que no se envían, PDFs de notificaciones canceladas o con
 *    error de más de --dias, PDFs de trabajo ya enviados que tienen copia en evidencias, huérfanos
 *    (ninguna notificación los usa) de más de --dias.
 *
 * Con --verificar-evidencias recalcula el sha256 de cada evidencia y reporta diferencias o faltantes.
 */
class BillingPdfsInventarioCommand extends Command
{
    protected $signature = 'billing:pdfs-inventario
                            {--dias=90 : Antigüedad mínima para proponer un archivo como candidato}
                            {--detalle=0 : Cuántos ejemplos listar por categoría}
                            {--verificar-evidencias : Recalcular el sha256 de las evidencias guardadas}';

    protected $description = 'Inventario (solo lectura) de los PDFs de facturación y propuesta de candidatos a retención';

    public function handle(EvidenciaDocumentoService $evidencias): int
    {
        $disco = Storage::disk(config('billing.pdf_disk', 'local'));
        $prefijo = rtrim((string) config('billing.pdf_path_prefix', 'billing/pdf'), '/');
        $limite = now()->subDays(max(0, (int) $this->option('dias')))->getTimestamp();
        $ejemplos = max(0, (int) $this->option('detalle'));

        $this->info("Inventario de {$prefijo} (disco " . config('billing.pdf_disk', 'local') . ', candidatos con más de '
            . (int) $this->option('dias') . ' días). Solo lectura: no se borra nada.');

        // pdf_path → notificaciones que lo usan.
        $usos = [];
        BillingNotification::query()->whereNotNull('pdf_path')
            ->select(['id', 'pdf_path', 'status', 'document_type', 'send_by_email'])
            ->orderBy('id')
            ->chunk(5000, function ($filas) use (&$usos) {
                foreach ($filas as $n) {
                    $usos[$n->pdf_path][] = $n;
                }
            });
        $conEvidencia = array_flip(BillingDocumentoEvidencia::query()->whereNotNull('billing_notification_id')
            ->distinct()->pluck('billing_notification_id')->all());

        $categorias = [];
        $anotar = function (string $categoria, string $archivo, int $bytes) use (&$categorias, $ejemplos) {
            $c = &$categorias[$categoria];
            $c['archivos'] = ($c['archivos'] ?? 0) + 1;
            $c['bytes'] = ($c['bytes'] ?? 0) + $bytes;
            if (count($c['ejemplos'] ?? []) < $ejemplos) {
                $c['ejemplos'][] = $archivo;
            }
        };

        foreach ($disco->allFiles($prefijo) as $archivo) {
            $bytes = (int) $disco->size($archivo);
            $viejo = $disco->lastModified($archivo) < $limite;
            $notifs = $usos[$archivo] ?? [];
            $tipos = array_unique(array_map(fn ($n) => $n->document_type, $notifs));
            $estados = array_unique(array_map(fn ($n) => $n->status, $notifs));

            if (str_starts_with($archivo, $prefijo . '/enviados/')) {
                $anotar('EXCLUIDO · evidencia de un envío (FAC-05)', $archivo, $bytes);
            } elseif (in_array(BillingNotification::TYPE_RECIBO, $tipos, true)) {
                $anotar('EXCLUIDO · recibo (lo descarga la app de MegaFamilia)', $archivo, $bytes);
            } elseif (in_array(BillingNotification::STATUS_WAITING, $estados, true)
                && ! (in_array(BillingNotification::TYPE_TICKET, $tipos, true) && ! collect($notifs)->contains('send_by_email', true))) {
                $anotar('EXCLUIDO · notificación en espera', $archivo, $bytes);
            } elseif (in_array(BillingNotification::TYPE_TICKET, $tipos, true) && ! collect($notifs)->contains('send_by_email', true)) {
                $anotar('CANDIDATO · ticket que no se envía', $archivo, $bytes);
            } elseif (! $notifs) {
                $anotar($viejo ? 'CANDIDATO · huérfano (ninguna notificación lo usa)' : 'EXCLUIDO · huérfano reciente', $archivo, $bytes);
            } elseif ($estados && ! array_diff($estados, [BillingNotification::STATUS_CANCELLED, BillingNotification::STATUS_ERROR])) {
                $anotar($viejo ? 'CANDIDATO · notificación cancelada o con error' : 'EXCLUIDO · cancelada/error reciente', $archivo, $bytes);
            } elseif (collect($notifs)->every(fn ($n) => isset($conEvidencia[$n->id]))) {
                $anotar('CANDIDATO · ya enviado y con copia en evidencias', $archivo, $bytes);
            } else {
                $anotar('EXCLUIDO · enviado sin copia en evidencias (envíos previos a FAC-05)', $archivo, $bytes);
            }
        }

        ksort($categorias);
        $filas = [];
        $total = ['archivos' => 0, 'bytes' => 0, 'candidatos' => 0, 'bytes_candidatos' => 0];
        foreach ($categorias as $nombre => $c) {
            $filas[] = [$nombre, $c['archivos'], self::mb($c['bytes'])];
            $total['archivos'] += $c['archivos'];
            $total['bytes'] += $c['bytes'];
            if (str_starts_with($nombre, 'CANDIDATO')) {
                $total['candidatos'] += $c['archivos'];
                $total['bytes_candidatos'] += $c['bytes'];
            }
        }
        $this->table(['Categoría', 'Archivos', 'MB'], $filas);
        $this->line("Total: {$total['archivos']} archivos, " . self::mb($total['bytes']) . " MB. Candidatos: {$total['candidatos']} archivos, "
            . self::mb($total['bytes_candidatos']) . ' MB. (Nada se borró.)');

        foreach ($categorias as $nombre => $c) {
            foreach ($c['ejemplos'] ?? [] as $archivo) {
                $this->line("  · {$nombre}: {$archivo}");
            }
        }

        if ($this->option('verificar-evidencias')) {
            $ok = $distintas = $faltan = 0;
            BillingDocumentoEvidencia::query()->orderBy('id')->chunk(1000, function ($filas) use ($evidencias, &$ok, &$distintas, &$faltan) {
                foreach ($filas as $e) {
                    $r = $evidencias->verificar($e);
                    if ($r === true) {
                        $ok++;
                    } elseif ($r === null) {
                        $faltan++;
                        $this->warn("  evidencia #{$e->id}: falta el archivo {$e->archivo}");
                    } else {
                        $distintas++;
                        $this->error("  evidencia #{$e->id}: el sha256 NO coincide ({$e->archivo})");
                    }
                }
            });
            $this->line("Evidencias: {$ok} íntegras, {$distintas} con sha256 distinto, {$faltan} sin archivo.");
        }

        return self::SUCCESS;
    }

    private static function mb(int $bytes): string
    {
        return number_format($bytes / 1048576, 1);
    }
}
