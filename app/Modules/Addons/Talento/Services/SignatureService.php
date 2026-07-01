<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoWorkOrderSignature;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Persistencia única de firmas (técnico/cliente) para OTs, sirviendo tanto a
 * work_orders como a tasks. Un solo escritor reutilizado por el admin
 * (TalentoFieldFlowController::storeSignature) y por el Portal Técnico Web.
 *
 * talento_work_order_signatures tiene UNIQUE (work_order_id, signer_type) y una
 * columna tarea_id con FK a tasks; re-firmar sobreescribe (updateOrCreate).
 */
class SignatureService
{
    /**
     * @param string      $origen 'work_order' | 'task'
     * @param int         $entityId  id de talento_work_orders o de tasks
     * @param string      $signerType 'technician' | 'client'
     * @param string      $base64 PNG en base64 (con o sin prefijo data URI)
     */
    public function store(
        string $origen,
        int $entityId,
        string $signerType,
        string $base64,
        ?float $lat,
        ?float $lng,
        ?string $signedAt,
        int $userId
    ): TalentoWorkOrderSignature {
        $isTask = $origen === 'task';
        $fkCol  = $isTask ? 'tarea_id' : 'work_order_id';

        // Limpia el prefijo data URI si viene incrustado.
        $data = $base64;
        if (str_starts_with($data, 'data:')) {
            $data = preg_replace('/^data:[^;]+;base64,/', '', $data);
        }

        $folder = $isTask ? "tarea_{$entityId}" : $entityId;
        $path   = "talento/signatures/{$folder}/{$signerType}_" . time() . '.png';
        Storage::disk('local')->put($path, base64_decode($data));

        return TalentoWorkOrderSignature::updateOrCreate(
            [$fkCol => $entityId, 'signer_type' => $signerType],
            [
                'signature_path' => $path,
                'signed_lat'     => $lat,
                'signed_lng'     => $lng,
                'signed_at'      => $signedAt ? Carbon::parse($signedAt) : now(),
                'created_by'     => $userId,
            ]
        );
    }

    /**
     * Tipos de firmante ya registrados para una OT (por origen).
     * @return string[] p.ej. ['technician','client']
     */
    public function signerTypesFor(string $origen, int $entityId): array
    {
        $fkCol = $origen === 'task' ? 'tarea_id' : 'work_order_id';
        return TalentoWorkOrderSignature::where($fkCol, $entityId)
            ->pluck('signer_type')
            ->all();
    }
}
