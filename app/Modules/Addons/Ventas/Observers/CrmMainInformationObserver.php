<?php

namespace App\Modules\Addons\Ventas\Observers;

use App\Modules\Addons\Ventas\Services\ProspectoCrmSyncService;
use App\Modules\Core\CRM\Models\CrmMainInformation;

/**
 * Item #9990779 — doble escritura, mitad "datos de contacto" del par CRM (ver
 * CrmLeadInformationObserver para la mitad "propietario/estado"). Cubre el orden de creación
 * en el que `crm_main_information` se guarda antes que su `crm_lead_information` (ej.
 * WhatsAppCrmService::class): sin este observer, ese caso quedaría sin sincronizar hasta la
 * siguiente edición del lead.
 */
class CrmMainInformationObserver
{
    public function saved(CrmMainInformation $main): void
    {
        ProspectoCrmSyncService::sincronizar($main->crm_id);
    }
}
