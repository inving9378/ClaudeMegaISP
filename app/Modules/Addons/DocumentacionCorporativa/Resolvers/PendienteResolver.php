<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPendiente;

/**
 * El resolvedor de último recurso: SIEMPRE está disponible.
 *
 * Cuando un concepto no tiene fuente configurada, o la que declara todavía no
 * existe, `ResolverFactory` cae aquí. Renderiza una tarjeta explícita —"sin
 * fuente configurada"— con el botón de subir documento y el de asignar
 * responsable. Nunca una vista vacía, nunca un error.
 */
class PendienteResolver extends BaseResolver
{
    public function disponible(DcConcepto $concepto, int $empresaId): bool
    {
        return true;
    }

    public function resolver(DcConcepto $concepto, int $empresaId): ResultadoConcepto
    {
        $pendiente = DcPendiente::deEmpresa($empresaId)
            ->where('concepto_id', $concepto->id)
            ->abiertos()
            ->with('responsable')
            ->first();

        $metricas = [
            'obligatorio'         => (bool) $concepto->obligatorio,
            'tiene_responsable'   => $pendiente?->responsable_user_id !== null,
            'fecha_compromiso'    => optional($pendiente?->fecha_compromiso)->toDateString(),
            // Id del pendiente abierto (o null): así el frontend sabe si debe
            // crear uno nuevo o editar el existente sin una consulta aparte.
            'pendiente_id'        => $pendiente?->id,
        ];

        $mensaje = 'Sin fuente configurada. Este concepto todavía no lee de ningún módulo '
            . 'del sistema y no tiene documentos cargados. Sube el documento o asigna un '
            . 'responsable para que quede en la bandeja de pendientes.';

        if ($pendiente) {
            $responsable = $pendiente->responsable?->name ?? 'sin responsable';
            $mensaje .= ' Pendiente abierto: ' . $responsable
                . ($pendiente->fecha_compromiso
                    ? ' · compromiso ' . $pendiente->fecha_compromiso->format('d/m/Y')
                    : ' · sin fecha compromiso') . '.';
        }

        return ResultadoConcepto::sinFuente($mensaje, $metricas);
    }
}
