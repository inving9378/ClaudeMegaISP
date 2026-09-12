<?php

namespace App\Modules\Addons\Ventas\Services;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Ventas\Models\VentaProspecto;
use App\Modules\Core\CRM\Models\CrmLeadInformation;
use App\Modules\Core\CRM\Models\CrmMainInformation;

/**
 * Item #9990779 — catálogo único de prospectos.
 *
 * Proyecta cada par (`crm_lead_information`,`crm_main_information`) — ambos ligados por
 * `crm_id` — hacia `ventas_prospectos` (fuente única del motor de ventas, #9990799).
 *
 * `colaborador_id` es INFORMATIVO/de linaje: refleja quién es el dueño del lead en CRM hoy
 * (`crm_lead_information.owner_id`, que es `users.id`, resuelto a `talento_colaboradores` por
 * `user_id`). NO implica una custodia activa del motor nuevo (#9990780): esa se abre solo
 * cuando alguien llama a `CustodiaService::asignar()`, que crea su propia fila en
 * `ventas_custodias`. Por eso un prospecto puede tener `colaborador_id` poblado con
 * `estado='pool'` — es intencional, no una fila corrupta (documentado en el reporte de cierre
 * del item).
 *
 * `estado` se deriva de `crm_status` (Ganado/Instalación → vendido, Perdido → perdido, el
 * resto → pool). No se crean custodias retroactivas para los ~2100 prospectos activos de CRM:
 * eso sería inventar asignaciones de ventana de 7 días para historial, decisión de negocio que
 * no toca a este item (alcance: "NO entra: construir el motor de custodia").
 */
class ProspectoCrmSyncService
{
    private const ESTADOS_VENDIDO = ['Ganado', 'Instalacion'];
    private const ESTADOS_PERDIDO = ['Perdido'];

    public static function sincronizar(int $crmId): ?VentaProspecto
    {
        $lead = CrmLeadInformation::where('crm_id', $crmId)->first();

        if (! $lead) {
            return null;
        }

        $main = CrmMainInformation::where('crm_id', $crmId)->first();

        return VentaProspecto::updateOrCreate(
            ['origen' => 'crm', 'origen_id' => $crmId],
            [
                'colaborador_id' => self::resolverColaboradorId($lead->owner_id),
                'client_id' => null,
                'nombre' => self::resolverNombre($main, $crmId),
                'telefono' => $main->phone ?? null,
                'email' => $main->email ?? null,
                'estado' => self::resolverEstado($lead->crm_status),
                'notas' => "Consolidado desde CRM (crm_status={$lead->crm_status})",
            ]
        );
    }

    private static function resolverColaboradorId(?int $ownerId): ?int
    {
        if (! $ownerId) {
            return null;
        }

        return TalentoColaborador::where('user_id', $ownerId)->value('id');
    }

    private static function resolverNombre(?CrmMainInformation $main, int $crmId): string
    {
        if (! $main) {
            return "Prospecto CRM #{$crmId}";
        }

        $nombre = trim(implode(' ', array_filter([
            $main->name,
            $main->father_last_name,
            $main->mother_last_name,
        ])));

        return $nombre !== '' ? $nombre : "Prospecto CRM #{$crmId}";
    }

    private static function resolverEstado(string $crmStatus): string
    {
        if (in_array($crmStatus, self::ESTADOS_VENDIDO, true)) {
            return 'vendido';
        }

        if (in_array($crmStatus, self::ESTADOS_PERDIDO, true)) {
            return 'perdido';
        }

        return 'pool';
    }
}
