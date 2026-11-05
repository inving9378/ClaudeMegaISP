<?php

namespace App\Modules\Addons\CrmEmpresarial\Observers;

use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmSegmento;
use App\Modules\Core\CRM\Models\CrmMainInformation;
use Illuminate\Support\Facades\Log;

/**
 * CRM-E 03 (#557): al guardar un prospecto con segmento distinto de Residencial (o con la casilla
 * de Dedicado) se abre su expediente en borrador. Sin segmento = residencial: no hace nada.
 * Con el addon inactivo este observer no se registra y el CRM se comporta como siempre.
 */
class CrmMainInformationObserver
{
    public function saved(CrmMainInformation $info): void
    {
        try {
            if (! $info->crm_id || CrmExpediente::where('crm_id', $info->crm_id)->exists()) {
                return;
            }
            $segmento = $info->segmento_id ? CrmSegmento::find($info->segmento_id) : null;
            $dedicado = (bool) $info->requiere_expediente_dedicado && $segmento?->codigo === 'DED';
            if (! $dedicado && ! ($segmento && $segmento->requiere_expediente)) {
                return;
            }
            $moral = $info->tipo_persona === 'moral';
            CrmExpediente::create([
                'crm_id' => $info->crm_id,
                'segmento_id' => $segmento->id,
                'tipo_persona' => $info->tipo_persona,
                'razon_social' => $moral ? $info->name : null,
                'nombre_comercial' => $info->nombre_comercial,
                'rfc' => $info->nif_pasaport ?: null,
                'telefono' => $info->phone,
                'email' => $info->email,
                'requiere_expediente_dedicado' => $dedicado,
                'estado' => 'borrador',
            ]);
        } catch (\Throwable $e) {
            // El expediente es un efecto secundario: nunca debe tumbar el alta del prospecto.
            Log::warning('CRM-E: no se pudo abrir el expediente del prospecto ' . $info->crm_id . ': ' . $e->getMessage());
        }
    }
}
