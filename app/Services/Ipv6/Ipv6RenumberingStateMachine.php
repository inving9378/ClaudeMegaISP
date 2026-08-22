<?php

namespace App\Services\Ipv6;

use App\Modules\Addons\Ipv6\Models\Ipv6RenumberingPlan;
use App\Services\Ipv6\Exceptions\Ipv6RenumberingStateException;
use Illuminate\Support\Facades\Auth;

/**
 * Máquina de estados de un plan de renumeración IPv6 (RFC 4192, item #1067).
 *
 * Orden válido: planificado -> activo -> en_deprecacion -> retirado. Solo
 * transiciones ADYACENTES (transition() no permite saltos, p.ej.
 * planificado->retirado directo). El overlap de dos bloques activos
 * simultáneos es una propiedad de que DOS PLANES distintos puedan estar en
 * {activo, en_deprecacion} a la vez — esta FSM valida un plan individual.
 *
 * Antes de admitir 'retirado' exige los 3 puntos críticos del plan
 * (morosos reconstruidos, histórico preservado, simple queues actualizadas)
 * — sin bypass, ni con flag admin: si falta alguno, excepción explícita.
 *
 * Cada transición exitosa (incluido un rollback) escribe una fila INMUTABLE
 * en ipv6_renumbering_transitions; el historial nunca se edita ni se borra.
 *
 * Sin I/O a MikroTik: orquestación lógica pura (el push real es fase futura).
 */
class Ipv6RenumberingStateMachine
{
    public const ORDEN_ESTADOS = ['planificado', 'activo', 'en_deprecacion', 'retirado'];

    /** Estados donde el overlap sigue vivo -> rollback() permitido. 'retirado' es el punto de no retorno. */
    private const ESTADOS_CON_ROLLBACK = ['planificado', 'activo', 'en_deprecacion'];

    public function transition(Ipv6RenumberingPlan $plan, string $aEstado, ?string $nota = null): Ipv6RenumberingPlan
    {
        $deEstado = $plan->estado;

        if (!in_array($aEstado, self::ORDEN_ESTADOS, true)) {
            throw new Ipv6RenumberingStateException(
                "Estado destino '{$aEstado}' no existe. Estados válidos: ".implode(', ', self::ORDEN_ESTADOS).'.'
            );
        }

        $indiceActual = array_search($deEstado, self::ORDEN_ESTADOS, true);
        $indiceDestino = array_search($aEstado, self::ORDEN_ESTADOS, true);

        if ($indiceActual === false || $indiceDestino !== $indiceActual + 1) {
            throw new Ipv6RenumberingStateException(
                "Transición inválida de '{$deEstado}' a '{$aEstado}': solo se permiten transiciones adyacentes en el orden ".
                implode(' -> ', self::ORDEN_ESTADOS).', sin saltos.'
            );
        }

        if ($aEstado === 'retirado') {
            $this->exigirPuntosCriticos($plan);
        }

        $plan->estado = $aEstado;
        $plan->save();

        $this->registrarTransicion($plan, $deEstado, $aEstado, $nota);

        return $plan;
    }

    /**
     * Revierte el plan al estado anterior según su última transición
     * registrada. Solo permitido mientras el overlap siga activo
     * ({planificado, activo, en_deprecacion}). Registra el rollback como
     * una transición nueva (no borra ni edita el historial existente).
     */
    public function rollback(Ipv6RenumberingPlan $plan, ?string $nota = null): Ipv6RenumberingPlan
    {
        if (!in_array($plan->estado, self::ESTADOS_CON_ROLLBACK, true)) {
            throw new Ipv6RenumberingStateException(
                "El plan #{$plan->id} ya está en 'retirado' (punto de no retorno): rollback() requiere intervención manual."
            );
        }

        $ultimaTransicion = $plan->transitions()->orderByDesc('cuando')->orderByDesc('id')->first();

        if (! $ultimaTransicion || $ultimaTransicion->de_estado === null) {
            throw new Ipv6RenumberingStateException(
                "El plan #{$plan->id} no tiene una transición previa registrada; no hay estado anterior al cual revertir."
            );
        }

        $estadoActual = $plan->estado;
        $estadoDestino = $ultimaTransicion->de_estado;

        $plan->estado = $estadoDestino;
        $plan->save();

        $this->registrarTransicion($plan, $estadoActual, $estadoDestino, $nota ?? 'Rollback automático');

        return $plan;
    }

    private function exigirPuntosCriticos(Ipv6RenumberingPlan $plan): void
    {
        $faltantes = [];

        if ($plan->morosos_reconstruido_at === null) {
            $faltantes[] = 'morosos_reconstruido_at (address-lists de morosos reconstruidas sobre el bloque nuevo)';
        }

        if ($plan->historico_preservado !== true) {
            $faltantes[] = 'historico_preservado (el histórico del bloque viejo debe preservarse, nunca borrarse)';
        }

        if ($plan->simple_queues_actualizado_at === null) {
            $faltantes[] = 'simple_queues_actualizado_at (simple queues migradas al bloque nuevo)';
        }

        if ($faltantes) {
            throw new Ipv6RenumberingStateException(
                "No se puede retirar el plan #{$plan->id}: faltan los siguientes puntos críticos: ".implode('; ', $faltantes).'.'
            );
        }
    }

    private function registrarTransicion(Ipv6RenumberingPlan $plan, ?string $deEstado, string $aEstado, ?string $nota): void
    {
        $usuario = Auth::user();

        $plan->transitions()->create([
            'quien_user_id' => $usuario->id ?? null,
            'quien_nombre' => $usuario->name ?? null,
            'cuando' => now(),
            'de_estado' => $deEstado,
            'a_estado' => $aEstado,
            'nota' => $nota,
        ]);
    }
}
