<?php

namespace App\Modules\Core\CRM\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detección de solo-lectura de document_crms huérfanos por criterio de
 * archivo faltante (#81/#9990084, decisión oficial de Irving q1/q2).
 *
 * Huérfano = document_crms cuyo padre `crms` SÍ existe (ese caso ya lo cubre
 * crm:purge-orphan-documents), pero cuyo archivo físico no está: o no hay
 * fila `files` (fileable_type=DocumentCrm) para él, o la hay pero su `path`
 * no resuelve a un archivo real en disco (CrmDocumentStorage::fileExists).
 *
 * Punto único de verdad: lo consume tanto crm:documentos-huerfanos como la
 * vista admin (sub-item hermano de #81) — mismo criterio, sin duplicar.
 */
class CrmOrphanDocumentDetector
{
    private const CRM_FILE_TYPE = 'App\\Models\\DocumentCrm';

    /** @return Collection<int, object{id:int,crm_id:int,title:?string,motivo:string}> */
    public static function detect(int $limit = 200): Collection
    {
        $rows = DB::table('document_crms as d')
            ->leftJoin('files as f', function ($join) {
                $join->on('f.fileable_id', '=', 'd.id')
                    ->where('f.fileable_type', '=', self::CRM_FILE_TYPE);
            })
            ->select('d.id', 'd.crm_id', 'd.title', 'f.path')
            ->orderBy('d.id')
            ->get();

        return $rows
            ->map(function ($row) {
                if ($row->path === null) {
                    return (object) [
                        'id' => $row->id,
                        'crm_id' => $row->crm_id,
                        'title' => $row->title,
                        'motivo' => 'sin fila files (fileable_type=DocumentCrm)',
                    ];
                }
                if (!CrmDocumentStorage::fileExists($row->path)) {
                    return (object) [
                        'id' => $row->id,
                        'crm_id' => $row->crm_id,
                        'title' => $row->title,
                        'motivo' => "path no existe en disco: {$row->path}",
                    ];
                }
                return null;
            })
            ->filter()
            ->values()
            ->take($limit);
    }
}
