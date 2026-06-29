<?php

namespace App\Console\Commands\Scripts;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purga de documentos CRM huérfanos (#81).
 *
 * Huérfano = fila en `document_crms` cuyo `crm_id` ya no existe en `crms`
 * (el CRM padre fue eliminado y, al no haber cascada ni limpieza en la app,
 * el documento quedó atrás). Ver causa raíz en el roadmap de #81.
 *
 * Por defecto SOLO borra la fila `document_crms` + su fila polimórfica en
 * `files` (fileable_type = App\Models\DocumentCrm). El archivo físico NO se
 * toca salvo --files=1 (segunda pasada, con salvaguarda estricta de path).
 *
 * Idempotente y por lotes: re-ejecutar encuentra los huérfanos restantes;
 * cuando no quedan, reporta 0.
 */
class PurgeOrphanCrmDocuments extends Command
{
    protected $signature = 'crm:purge-orphan-documents
        {--dry-run : Solo reporta, no borra nada}
        {--files=0 : 1 = además borra el archivo físico por files.path exacto (NO usar sin revisar)}
        {--limit=200 : Tamaño de lote}';

    protected $description = 'Borra document_crms huérfanos (sin CRM padre) + su fila files. #81';

    /** fileable_type real con el que se guardan los archivos de documentos CRM. */
    private const CRM_FILE_TYPE = 'App\\Models\\DocumentCrm';

    public function handle(): int
    {
        $dryRun     = (bool) $this->option('dry-run');
        $deleteFile = (int) $this->option('files') === 1;
        $limit      = max(1, (int) $this->option('limit'));

        // Conteo y sanity global (criterio: crm_id sin padre en crms)
        $total = $this->orphanQuery()->count();
        $this->info("Documentos CRM huérfanos (document_crms sin CRM padre): {$total}");

        if ($total === 0) {
            $this->info('Nada que purgar. ✔');
            return self::SUCCESS;
        }

        $ids       = $this->orphanQuery()->orderBy('d.id')->pluck('d.id');
        $crmIds    = $this->orphanQuery()->pluck('d.crm_id')->unique()->values();
        $filesCnt  = DB::table('files')
            ->where('fileable_type', self::CRM_FILE_TYPE)
            ->whereIn('fileable_id', $ids)->count();

        // Sanity: NINGÚN crm_id objetivo debe existir en crms.
        $leak = DB::table('crms')->whereIn('id', $crmIds)->count();

        $this->line("  - document_crms a borrar : {$total}  (ids {$ids->first()} … {$ids->last()})");
        $this->line("  - filas files asociadas  : {$filesCnt}");
        $this->line("  - CRMs distintos (deben estar ausentes de crms): {$crmIds->count()}");
        $this->line("  - crm_id objetivo que SÍ existen en crms (debe ser 0): {$leak}");

        if ($leak > 0) {
            $this->error("ABORTANDO: {$leak} crm_id objetivo existen en crms. El criterio de huérfano no es consistente.");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: no se borró nada.');
            if ($deleteFile) {
                $this->warn('(--files=1 indicado, pero en dry-run tampoco se tocan archivos físicos.)');
            }
            return self::SUCCESS;
        }

        $totalDocs = 0;
        $totalFiles = 0;
        $totalPhysical = 0;
        $lote = 0;

        // Bucle por lotes: cada vuelta toma --limit huérfanos vigentes y los borra.
        while (true) {
            $batchIds = $this->orphanQuery()->orderBy('d.id')->limit($limit)->pluck('d.id');
            if ($batchIds->isEmpty()) {
                break;
            }
            $lote++;

            // Archivos físicos (solo si --files=1) ANTES de borrar la fila files.
            $physicalDeleted = 0;
            if ($deleteFile) {
                $paths = DB::table('files')
                    ->where('fileable_type', self::CRM_FILE_TYPE)
                    ->whereIn('fileable_id', $batchIds)
                    ->whereNotNull('path')->pluck('path');
                foreach ($paths as $p) {
                    if ($this->deletePhysicalFileSafely($p)) {
                        $physicalDeleted++;
                    }
                }
            }

            // Borrado DB en transacción: primero files, luego document_crms.
            $deletedFiles = 0;
            $deletedDocs  = 0;
            DB::transaction(function () use ($batchIds, &$deletedFiles, &$deletedDocs) {
                $deletedFiles = DB::table('files')
                    ->where('fileable_type', self::CRM_FILE_TYPE)
                    ->whereIn('fileable_id', $batchIds)->delete();
                $deletedDocs = DB::table('document_crms')->whereIn('id', $batchIds)->delete();
            });

            $totalDocs     += $deletedDocs;
            $totalFiles    += $deletedFiles;
            $totalPhysical += $physicalDeleted;

            $msg = "Lote {$lote}: docs={$deletedDocs} files={$deletedFiles}"
                . ($deleteFile ? " fisicos={$physicalDeleted}" : '')
                . " | ids {$batchIds->first()}..{$batchIds->last()}";
            $this->line('  ' . $msg);
            Log::info('[crm:purge-orphan-documents] ' . $msg);
        }

        $this->info("Purga completa. document_crms borrados={$totalDocs}, files borrados={$totalFiles}"
            . ($deleteFile ? ", archivos físicos borrados={$totalPhysical}" : ''));
        $this->info('Restantes -> document_crms=' . DB::table('document_crms')->count()
            . ', files(DocumentCrm)=' . DB::table('files')->where('fileable_type', self::CRM_FILE_TYPE)->count());

        return self::SUCCESS;
    }

    /** Query base de huérfanos: document_crms sin CRM padre. */
    private function orphanQuery()
    {
        return DB::table('document_crms as d')
            ->leftJoin('crms as c', 'c.id', '=', 'd.crm_id')
            ->whereNull('c.id')
            ->select('d.id', 'd.crm_id');
    }

    /**
     * Borra UN archivo físico por su files.path exacto, con salvaguardas:
     *  - resuelve path -> storage/app/public (igual que el symlink /storage)
     *  - SOLO borra si es un archivo regular (is_file); NUNCA un directorio
     *  - el realpath debe quedar DENTRO de storage/app/public (anti path-traversal)
     *  - NUNCA borra una carpeta client/{id}/ completa: esos ids se solapan con
     *    clientes reales vivos (DocumentClient). Solo unlink del archivo puntual.
     */
    private function deletePhysicalFileSafely(string $path): bool
    {
        $rel = preg_replace('#^/?storage/#', '', $path);
        $abs = storage_path('app/public/' . $rel);

        $base = realpath(storage_path('app/public'));
        $real = realpath($abs);

        if ($real === false || $base === false) {
            return false; // no existe / no resoluble
        }
        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
            $this->warn("    SALTADO (fuera de storage/app/public): {$path}");
            return false;
        }
        if (!is_file($real)) {
            // Es directorio o no es archivo regular -> NO tocar.
            $this->warn("    SALTADO (no es archivo regular): {$path}");
            return false;
        }

        return @unlink($real);
    }
}
