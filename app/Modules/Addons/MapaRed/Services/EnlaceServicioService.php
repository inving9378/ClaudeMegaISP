<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use Illuminate\Support\Facades\DB;

/**
 * M6 Fase 1 (item #1299) — crea el enlace de servicio (drop) cliente ↔ puerto de NAP.
 *
 * Idempotente por (cliente_nombre, puerto_nap_id). Solo servicio: sin rutas, UI ni backfill
 * (la invocación se decide en fases posteriores). Los casos de negocio (caja llena, sin caja,
 * puerto ocupado por otro cliente) devuelven `motivo` sin lanzar excepción.
 *
 * Resultado: ['ok'=>bool, 'creado'=>bool, 'motivo'=>?string, 'enlace'=>?MapaRedEnlaceServicio]
 */
class EnlaceServicioService
{
    public const MOTIVO_SIN_CAJA = 'sin_caja';
    public const MOTIVO_CAJA_LLENA = 'caja_llena';
    public const MOTIVO_PUERTO_INEXISTENTE = 'puerto_inexistente';
    public const MOTIVO_PUERTO_OCUPADO = 'puerto_ocupado_por_otro';
    public const MOTIVO_PUERTO_NO_DISPONIBLE = 'puerto_no_disponible';

    /**
     * @param  int|null  $puertoNapId  puerto `nap_salida` concreto; si es null se elige uno libre de la caja.
     * @param  array|null  $caja  ['type'=>class, 'id'=>int] dueño de los puertos cuando no se da puerto.
     */
    public function crear(string $clienteNombre, string $ontSerie, ?int $puertoNapId = null, ?array $caja = null): array
    {
        return DB::transaction(function () use ($clienteNombre, $ontSerie, $puertoNapId, $caja) {
            if ($puertoNapId !== null) {
                $puerto = MapaRedPuerto::query()->lockForUpdate()->find($puertoNapId);
                if (! $puerto || $puerto->rol !== MapaRedPuerto::ROL_NAP_SALIDA) {
                    return $this->falla(self::MOTIVO_PUERTO_INEXISTENTE);
                }
            } elseif (! empty($caja['type']) && ! empty($caja['id'])) {
                $base = MapaRedPuerto::query()
                    ->delDueno($caja['type'], (int) $caja['id'])
                    ->where('rol', MapaRedPuerto::ROL_NAP_SALIDA);
                if (! (clone $base)->exists()) {
                    return $this->falla(self::MOTIVO_SIN_CAJA);
                }

                // Si el cliente ya tiene enlace en esta caja, se reutiliza (idempotencia sin puerto).
                $existente = MapaRedEnlaceServicio::query()
                    ->where('cliente_nombre', $clienteNombre)
                    ->whereIn('puerto_nap_id', (clone $base)->pluck('id'))
                    ->first();
                if ($existente) {
                    return ['ok' => true, 'creado' => false, 'motivo' => null, 'enlace' => $existente];
                }

                $puerto = (clone $base)->libre()->orderBy('id')->lockForUpdate()->first();
                if (! $puerto) {
                    return $this->falla(self::MOTIVO_CAJA_LLENA);
                }
            } else {
                return $this->falla(self::MOTIVO_SIN_CAJA);
            }

            $existente = MapaRedEnlaceServicio::query()
                ->where('cliente_nombre', $clienteNombre)
                ->where('puerto_nap_id', $puerto->id)
                ->first();
            if ($existente) {
                return ['ok' => true, 'creado' => false, 'motivo' => null, 'enlace' => $existente];
            }

            if ($puerto->estado !== MapaRedPuerto::ESTADO_LIBRE) {
                $otro = MapaRedEnlaceServicio::query()->where('puerto_nap_id', $puerto->id)->activos()->exists();

                return $this->falla($otro ? self::MOTIVO_PUERTO_OCUPADO : self::MOTIVO_PUERTO_NO_DISPONIBLE);
            }

            $enlace = MapaRedEnlaceServicio::create([
                'cliente_nombre' => $clienteNombre,
                'ont_serie' => $ontSerie,
                'puerto_nap_id' => $puerto->id,
                'fecha_alta' => now()->toDateString(),
                'estado' => 'activo',
            ]);
            $puerto->update(['estado' => MapaRedPuerto::ESTADO_OCUPADO]);

            return ['ok' => true, 'creado' => true, 'motivo' => null, 'enlace' => $enlace];
        });
    }

    private function falla(string $motivo): array
    {
        return ['ok' => false, 'creado' => false, 'motivo' => $motivo, 'enlace' => null];
    }
}
