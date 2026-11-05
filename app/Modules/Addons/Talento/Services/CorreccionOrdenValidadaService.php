<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T D7.1 — corrige tipo, puntos y/o participantes de una orden YA validada, solo mientras su semana de
 * pago siga abierta. Exige permiso 'talento.ordenes.corregir_validada' y motivo; deja evento con antes/después.
 * No recalcula nómina: el pago lo toma al cerrar la semana. Detrás de unificacion.interruptores.correccion_validada_d71.
 * D7.2: si la semana de CUALQUIER participante (antes o después del cambio) ya está cerrada/pagada, la orden NO se
 * reescribe: se registra un ajuste visible (talento_period_adjustments, mecanismo J3) en la siguiente semana abierta.
 * Sin endpoint todavía.
 */
class CorreccionOrdenValidadaService
{
    public const PERMISO = 'talento.ordenes.corregir_validada';
    public const MOTIVO_MIN = 10;

    public function activo(): bool
    {
        return (bool) config('unificacion.interruptores.correccion_validada_d71');
    }

    /**
     * @param string $origen 'task' | 'work_order'
     * @param array  $cambios ['tipo_id'=>?int, 'puntos'=>?int, 'participantes'=>?[['user_id'=>,'aporte'=>?],...]] (solo lo presente se corrige)
     * @return array{success:bool,message?:string,status_code?:int,antes?:array,despues?:array}
     */
    public function corregir(string $origen, int $id, array $cambios, ?string $motivo, ?int $actorId): array
    {
        if (! $this->activo()) {
            return $this->rechazo('Corrección de órdenes validadas no habilitada.', 403);
        }
        $actor = $actorId ? \App\Models\User::find($actorId) : null;
        if (! $actor || ! $actor->can(self::PERMISO)) {
            return $this->rechazo('Sin permiso para corregir órdenes validadas.', 403);
        }
        $motivo = trim((string) $motivo);
        if (mb_strlen($motivo) < self::MOTIVO_MIN) {
            return $this->rechazo('El motivo es obligatorio (mínimo ' . self::MOTIVO_MIN . ' caracteres).', 422);
        }
        $cambios = array_intersect_key($cambios, array_flip(['tipo_id', 'puntos', 'participantes']));
        if (! $cambios) {
            return $this->rechazo('No hay nada que corregir.', 422);
        }
        if (! in_array($origen, ['task', 'work_order'], true)) {
            return $this->rechazo('Origen de orden inválido.', 422);
        }

        $orden = $origen === 'task' ? Task::where('id', $id)->whereNotNull('talento_type_id')->first() : TalentoWorkOrder::find($id);
        if (! $orden) {
            return $this->rechazo('Orden no encontrada.', 404);
        }
        $validadaAt = $orden->validated_at;
        if (! $validadaAt) {
            return $this->rechazo('Solo se corrigen órdenes validadas.', 422);
        }
        if (isset($cambios['tipo_id']) && ! TalentoWorkOrderType::whereKey($cambios['tipo_id'])->exists()) {
            return $this->rechazo('Tipo de orden inexistente.', 422);
        }
        if (isset($cambios['puntos']) && (! is_numeric($cambios['puntos']) || $cambios['puntos'] < 0)) {
            return $this->rechazo('Puntos inválidos.', 422);
        }

        $participantes = app(OrderParticipantsService::class);
        $validadaCarbon = Carbon::parse($validadaAt);
        $colabsAntes = $this->colaboradoresDe($origen, $orden, $participantes, $id);
        $colabsNuevos = [];
        foreach ($cambios['participantes'] ?? [] as $p) {
            $c = $this->colaboradorDeUsuario((int) ($p['user_id'] ?? 0));
            if ($c) {
                $colabsNuevos[] = $c;
            }
        }
        $cerrados = array_values(array_filter(array_unique(array_merge($colabsAntes, $colabsNuevos)), fn ($c) => $this->semanaCerrada($c, $validadaCarbon)));
        if ($cerrados) {
            return $this->ajustar($origen, $id, $orden, $cambios, $motivo, $actorId, $cerrados, $validadaCarbon, $participantes);
        }

        $antes = $this->foto($origen, $orden, $participantes, $id);

        DB::transaction(function () use ($origen, $id, $cambios, $participantes, $orden) {
            $tabla = $origen === 'task' ? 'tasks' : 'talento_work_orders';
            $colTipo = $origen === 'task' ? 'talento_type_id' : 'type_id';
            $upd = [];
            if (isset($cambios['tipo_id'])) {
                $upd[$colTipo] = (int) $cambios['tipo_id'];
            }
            if (isset($cambios['puntos'])) {
                $upd['points'] = (int) $cambios['puntos'];
            }
            if ($upd) {
                $upd['updated_at'] = now();
                DB::table($tabla)->where('id', $id)->update($upd);
            }
            if (isset($cambios['participantes'])) {
                $lista = array_values($cambios['participantes']);
                $principal = (int) ($lista[0]['user_id'] ?? 0);
                if ($principal) {
                    $participantes->fijar($origen, $id, $principal, array_slice($lista, 1));
                }
            }
        });

        $despues = $this->foto($origen, $origen === 'task' ? Task::find($id) : TalentoWorkOrder::find($id), $participantes, $id);

        TalentoOrderEventRecorder::registrar($origen, $id, 'order.corrected', 'validated', 'validated', $actorId, $motivo, [
            'antes' => $antes, 'despues' => $despues,
        ]);

        return ['success' => true, 'antes' => $antes, 'despues' => $despues];
    }

    private function foto(string $origen, $orden, OrderParticipantsService $participantes, int $id): array
    {
        return [
            'tipo_id'       => $origen === 'task' ? $orden->talento_type_id : $orden->type_id,
            'puntos'        => $orden->points,
            'participantes' => array_map(fn ($p) => ['user_id' => $p['user_id'], 'rol' => $p['rol'], 'aporte' => $p['aporte']], $participantes->listar($origen, $id)),
        ];
    }

    /** D7.2: semana cerrada → sin reescribir la orden; ajuste en la siguiente semana abierta por cada colaborador cerrado. */
    private function ajustar(string $origen, int $id, $orden, array $cambios, string $motivo, ?int $actorId, array $cerrados, Carbon $validadaAt, OrderParticipantsService $participantes): array
    {
        $antes = $this->foto($origen, $orden, $participantes, $id);
        $puntosNuevos = isset($cambios['puntos']) ? (int) $cambios['puntos'] : (int) $antes['puntos'];
        if (! isset($cambios['puntos']) && isset($cambios['tipo_id'])) {
            $puntosNuevos = (int) TalentoWorkOrderType::whereKey($cambios['tipo_id'])->value('points');
        }
        $delta = $puntosNuevos - (int) $antes['puntos'];
        $orig = PayWeek::boundsFor($validadaAt->copy());
        $ajustes = [];
        DB::transaction(function () use ($cerrados, $orig, $delta, $origen, $id, $motivo, $actorId, &$ajustes) {
            foreach ($cerrados as $colabId) {
                $target = $orig;
                for ($i = 0; $i < 6; $i++) {
                    $target = PayWeek::boundsFor($target['end_instant']->copy()->addSecond());
                    if (! $this->semanaCerrada($colabId, $target['start_instant'])) {
                        break;
                    }
                }
                $ajustes[] = DB::table('talento_period_adjustments')->insertGetId([
                    'colaborador_id' => $colabId, 'original_type' => $origen === 'task' ? 'task' : 'work_order', 'original_id' => $id,
                    'original_period_start' => $orig['period_start'], 'original_period_end' => $orig['period_end'],
                    'target_period_start' => $target['period_start'], 'target_period_end' => $target['period_end'],
                    'points_delta' => $delta, 'amount_delta' => 0,
                    'reason' => 'Corrección de orden validada en semana cerrada: ' . mb_substr($motivo, 0, 200),
                    'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        TalentoOrderEventRecorder::registrar($origen, $id, 'order.corrected', 'validated', 'validated', $actorId, $motivo, [
            'antes' => $antes, 'solicitado' => $cambios, 'ajuste' => true, 'ajustes_ids' => $ajustes, 'points_delta' => $delta,
        ]);

        return ['success' => true, 'ajuste' => true, 'ajustes_ids' => $ajustes, 'points_delta' => $delta, 'antes' => $antes, 'despues' => $antes];
    }

    /** @return int[] ids de colaborador de los participantes actuales (o del principal histórico). */
    private function colaboradoresDe(string $origen, $orden, OrderParticipantsService $participantes, int $id): array
    {
        $ids = [];
        foreach ($participantes->listar($origen, $id) as $p) {
            if ($c = $this->colaboradorDeUsuario((int) $p['user_id'])) {
                $ids[] = $c;
            }
        }
        if (! $ids && ($c = $this->colaboradorId($origen, $orden))) {
            $ids[] = $c;
        }
        return array_values(array_unique($ids));
    }

    private function colaboradorDeUsuario(int $userId): ?int
    {
        $c = $userId ? \App\Modules\Addons\Talento\Models\TalentoColaborador::where('user_id', $userId)->value('id') : null;
        return $c ? (int) $c : null;
    }

    private function colaboradorId(string $origen, $orden): ?int
    {
        if ($origen === 'work_order') {
            return $orden->colaborador_id ? (int) $orden->colaborador_id : null;
        }
        $uid = $orden->users->first()?->id;
        return $uid ? (int) \App\Modules\Addons\Talento\Models\TalentoColaborador::where('user_id', $uid)->value('id') : null;
    }

    /** Semana abierta = no existe liquidación cerrada/pagada de ese colaborador para la ventana de validated_at. */
    private function semanaCerrada(?int $colabId, Carbon $instante): bool
    {
        if (! $colabId || ! Schema::hasTable('talento_liquidations')) {
            return false;
        }
        $w = PayWeek::boundsFor($instante->copy());
        return TalentoLiquidation::where('colaborador_id', $colabId)
            ->where('period_start', $w['period_start'])->where('period_end', $w['period_end'])
            ->whereIn('status', ['closed', 'paid'])->exists();
    }

    private function rechazo(string $mensaje, int $codigo): array
    {
        return ['success' => false, 'message' => $mensaje, 'status_code' => $codigo];
    }
}
