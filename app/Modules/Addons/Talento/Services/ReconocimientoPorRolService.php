<?php

namespace App\Modules\Addons\Talento\Services;

/**
 * UNI-T D8b — punto único del porcentaje de reconocimiento por rol (responsable / participante) de una
 * orden con varios asignados. Detrás de unificacion.interruptores.porcentajes_rol_d8b: apagado = 1.0
 * (unidad completa a cada asignado, como hoy). Requiere participantes_d8 para conocer el rol.
 */
class ReconocimientoPorRolService
{
    public function activo(): bool
    {
        return (bool) config('unificacion.interruptores.porcentajes_rol_d8b')
            && app(OrderParticipantsService::class)->activo();
    }

    /** Factor 0..1 con que cuenta la orden $origen/$id para el usuario $userId. */
    public function factor(string $origen, int $id, int $userId): float
    {
        if (! $this->activo()) {
            return 1.0;
        }
        foreach (app(OrderParticipantsService::class)->listar($origen, $id) as $p) {
            if ((int) $p['user_id'] === $userId) {
                $clave = $p['rol'] === 'principal' ? 'responsable' : 'participante';
                $pct   = (float) config("unificacion.interruptores.porcentajes_rol_d8b_{$clave}", 100);
                return max(0.0, min(100.0, $pct)) / 100;
            }
        }
        return 1.0;
    }

    /** user_id del responsable principal de la orden, o null si el interruptor está apagado / no hay dato (el llamador conserva su regla de hoy). */
    public function responsablePrincipalUserId(string $origen, int $id): ?int
    {
        if (! $this->activo()) {
            return null;
        }
        foreach (app(OrderParticipantsService::class)->listar($origen, $id) as $p) {
            if ($p['rol'] === 'principal') {
                return (int) $p['user_id'];
            }
        }
        return null;
    }
}
