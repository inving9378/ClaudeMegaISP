<?php

namespace App\Observers;

use App\Modules\Core\CRM\Models\Crm;
use App\Modules\Core\CRM\Support\CrmDocumentStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cascade al eliminar un CRM (#169 — fix de fondo de #81).
 *
 * Al borrar un Crm (siempre vía Eloquent: CrmController@destroy y
 * @convertToClient), limpia los hijos que NO tienen FK con cascada:
 *   - document_crms + sus files (morph) + el archivo físico
 *   - deal_crms, quote_crms
 *
 * NO toca:
 *   - crm_main_information / crm_lead_information / commissions_details /
 *     payments_details  -> ya tienen FK ON DELETE CASCADE.
 *   - whatsapp_conversations -> FK ON DELETE SET NULL (intencional).
 *   - log_activities -> bitácora/auditoría: se PRESERVA a propósito.
 *
 * El archivo físico se borra vía DB::afterCommit: si convertToClient() (que
 * borra el CRM dentro de DB::transaction) hace rollback, NO se pierde el
 * archivo de un CRM que sigue vivo. Sin transacción, afterCommit ejecuta de
 * inmediato.
 */
class CrmObserver
{
    /** fileable_type real de los archivos de documentos CRM. */
    private const DOC_CRM_TYPE = 'App\\Models\\DocumentCrm';

    public function deleting(Crm $crm): void
    {
        $crmId = $crm->getKey();

        // 1) document_crms + files (morph) + archivo físico
        $docIds = DB::table('document_crms')->where('crm_id', $crmId)->pluck('id');
        if ($docIds->isNotEmpty()) {
            $paths = DB::table('files')
                ->where('fileable_type', self::DOC_CRM_TYPE)
                ->whereIn('fileable_id', $docIds)
                ->whereNotNull('path')
                ->pluck('path')
                ->all();

            // Filas DB dentro de la transacción actual (se revierten si hay rollback).
            DB::table('files')
                ->where('fileable_type', self::DOC_CRM_TYPE)
                ->whereIn('fileable_id', $docIds)
                ->delete();
            DB::table('document_crms')->whereIn('id', $docIds)->delete();

            // Archivo físico SOLO tras commit (o inmediato si no hay transacción).
            foreach ($paths as $path) {
                DB::afterCommit(static fn () => CrmDocumentStorage::deleteFileSafely($path));
            }
        }

        // 2) deal_crms + quote_crms (sin morph ni archivos)
        $deals  = DB::table('deal_crms')->where('crm_id', $crmId)->delete();
        $quotes = DB::table('quote_crms')->where('crm_id', $crmId)->delete();

        Log::info("[CrmObserver] CRM {$crmId} eliminado -> document_crms={$docIds->count()},"
            . " deal_crms={$deals}, quote_crms={$quotes} (log_activities preservado)");
    }
}
