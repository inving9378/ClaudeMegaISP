<?php

namespace App\Modules\Addons\Ventas\Services;

use App\Modules\Addons\Ventas\Models\VentaCustodia;
use App\Modules\Addons\Ventas\Models\VentaProspecto;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Motor de custodia de prospectos (item #9990780): ventana de 7 días, hasta 3
 * renovaciones con evidencia de trabajo, retorno automático al pool general.
 *
 * ⚠️ REGLA DE EVIDENCIA — PROVISIONAL (decisión q1 del item, opción recomendada por Irving:
 * "esperar/citar el reglamento de #0; si no existe, dejar el criterio como TODO configurable
 * y no hardcodear reglas"). El reglamento (docs/reglamento-ventas-comisiones.md) TODAVÍA no
 * existe en el repo — verificado en
 * docs/reglamento-ventas-comisiones-item-9990775-verificacion.md. Sin ese texto no se inventa
 * una regla de negocio específica (ej. "1 llamada en los últimos 3 días"); el gate usado aquí
 * es el mínimo mecánico defendible: **al menos EVIDENCIA_MINIMA registros en `ventas_evidencias`
 * ligados a la custodia**. Ajustar esta constante/consulta en cuanto el reglamento real defina
 * la regla exacta — no antes.
 */
class CustodiaService
{
    public const DIAS_VENTANA = 7;
    public const MAX_RENOVACIONES = 3;
    public const EVIDENCIA_MINIMA = 1;

    public function asignar(VentaProspecto $prospecto, int $colaboradorId): VentaCustodia
    {
        $custodia = VentaCustodia::create([
            'prospecto_id' => $prospecto->id,
            'colaborador_id' => $colaboradorId,
            'asignado_at' => now(),
            'fecha_limite' => now()->addDays(self::DIAS_VENTANA)->toDateString(),
            'renovaciones_usadas' => 0,
            'max_renovaciones' => self::MAX_RENOVACIONES,
            'estado' => 'activa',
        ]);

        $prospecto->update([
            'colaborador_id' => $colaboradorId,
            'estado' => 'en_custodia',
        ]);

        return $custodia;
    }

    /**
     * Renueva la custodia. Solo el colaborador asignado puede renovar (decisión q2, opción 1:
     * "solo el colaborador asignado puede renovar; el supervisor puede reasignar, no renovar").
     *
     * @throws RuntimeException si el actor no es el colaborador asignado, ya se agotaron las
     *                          renovaciones, la custodia ya fue liberada, o no hay evidencia.
     */
    public function renovar(VentaCustodia $custodia, int $actorColaboradorId): VentaCustodia
    {
        if ($custodia->estado === 'liberada') {
            throw new RuntimeException('La custodia ya fue liberada; el prospecto volvió al pool general.');
        }

        if ((int) $custodia->colaborador_id !== $actorColaboradorId) {
            throw new RuntimeException('Solo el colaborador asignado puede renovar esta custodia.');
        }

        if (! $custodia->tieneRenovacionesDisponibles()) {
            throw new RuntimeException("Esta custodia ya agotó sus {$custodia->max_renovaciones} renovaciones.");
        }

        if ($custodia->evidencias()->count() < self::EVIDENCIA_MINIMA) {
            throw new RuntimeException('No se puede renovar sin evidencia de trabajo registrada (contacto/seguimiento).');
        }

        $custodia->update([
            'fecha_limite' => Carbon::parse($custodia->fecha_limite)->max(now())->addDays(self::DIAS_VENTANA)->toDateString(),
            'renovaciones_usadas' => $custodia->renovaciones_usadas + 1,
            'estado' => 'activa',
        ]);

        return $custodia->refresh();
    }

    /**
     * Barre custodias vencidas. Pensado para correr vía job programado (decisión q4: diario
     * a las 00:05).
     *
     * - Vencida CON renovaciones disponibles → se marca 'vencida' (visible en la UI), sigue
     *   asignada al colaborador: espera una renovación manual, no se toca al prospecto.
     * - Vencida SIN renovaciones disponibles → se libera de verdad: la custodia pasa a
     *   'liberada' y el prospecto vuelve al pool general (colaborador_id=null, estado='pool').
     *
     * @return array{marcadas_vencidas: int, liberadas: int}
     */
    public function liberarVencidas(): array
    {
        $hoy = now()->toDateString();

        $marcadasVencidas = VentaCustodia::where('estado', 'activa')
            ->where('fecha_limite', '<', $hoy)
            ->whereColumn('renovaciones_usadas', '<', 'max_renovaciones')
            ->update(['estado' => 'vencida']);

        $agotadas = VentaCustodia::whereIn('estado', ['activa', 'vencida'])
            ->where('fecha_limite', '<', $hoy)
            ->whereColumn('renovaciones_usadas', '>=', 'max_renovaciones')
            ->get();

        $liberadas = 0;
        foreach ($agotadas as $custodia) {
            $custodia->update(['estado' => 'liberada', 'liberada_at' => now()]);
            $custodia->prospecto()->update(['colaborador_id' => null, 'estado' => 'pool']);
            $liberadas++;
        }

        return ['marcadas_vencidas' => $marcadasVencidas, 'liberadas' => $liberadas];
    }
}
