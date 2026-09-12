<?php

namespace App\Modules\Addons\Ventas\Observers;

use App\Modules\Addons\Ventas\Services\ProspectoCrmSyncService;
use App\Modules\Core\CRM\Models\CrmLeadInformation;

/**
 * Item #9990779 — doble escritura: cada alta/edición de un lead de CRM se proyecta también
 * hacia `ventas_prospectos` (fuente única del motor de ventas), sin dejar de escribir en
 * `crm_lead_information` (CRM sigue siendo la fuente legacy viva; ver #13 para su apagado).
 */
class CrmLeadInformationObserver
{
    public function saved(CrmLeadInformation $lead): void
    {
        ProspectoCrmSyncService::sincronizar($lead->crm_id);
    }
}
