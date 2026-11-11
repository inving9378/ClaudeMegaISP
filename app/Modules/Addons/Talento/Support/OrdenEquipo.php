<?php

namespace App\Modules\Addons\Talento\Support;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\OrderParticipantsService;
use Illuminate\Support\Collection;

/**
 * Órdenes en equipo (#1325): un responsable y hasta MAX_AUXILIARES auxiliares.
 *
 * - Todos quedan en task_user (así la orden les sale en su app y en «Mi día») y el responsable se
 *   guarda como principal en talento_order_participants (OrderParticipantsService).
 * - Detrás de unificacion.interruptores.ordenes_equipo, que además necesita participantes_d8. Con el
 *   interruptor apagado una orden tiene una sola persona, como antes.
 * - Puntos: los cuenta LiquidationService por cada persona en task_user. Mientras el reparto por rol
 *   (porcentajes_rol_d8b) esté apagado, cada integrante recibe la orden completa.
 */
class OrdenEquipo
{
    public const MAX_AUXILIARES = 5;

    public static function activo(): bool
    {
        return (bool) config('unificacion.interruptores.ordenes_equipo')
            && app(OrderParticipantsService::class)->activo();
    }

    /** Reparto que se aplicará al pago, para mostrarlo al asignar (porcentajes de D8b). */
    public static function reparto(): array
    {
        $activo = (bool) config('unificacion.interruptores.porcentajes_rol_d8b');

        return [
            'activo'      => $activo,
            'responsable' => $activo ? (float) config('unificacion.interruptores.porcentajes_rol_d8b_responsable', 100) : 100.0,
            'auxiliar'    => $activo ? (float) config('unificacion.interruptores.porcentajes_rol_d8b_participante', 100) : 100.0,
        ];
    }

    /** user_id del responsable: el principal guardado o, si no hay, el primero asignado. */
    public static function responsableUserId(Task $task): ?int
    {
        if (self::activo()) {
            foreach (app(OrderParticipantsService::class)->listar('task', $task->id) as $p) {
                if ($p['rol'] === 'principal') {
                    return (int) $p['user_id'];
                }
            }
        }

        return $task->users->first()?->id;
    }

    /** user_ids de los auxiliares, en el orden en que se guardaron. Vacío con el interruptor apagado. */
    public static function auxiliaresUserIds(Task $task): array
    {
        if (! self::activo()) {
            return [];
        }
        $responsable = self::responsableUserId($task);

        return collect(app(OrderParticipantsService::class)->listar('task', $task->id))
            ->map(fn ($p) => (int) $p['user_id'])
            ->filter(fn ($id) => $id !== $responsable)
            ->values()
            ->all();
    }

    /** Responsable y auxiliares en task_user y en participantes. Sin el interruptor, solo el responsable. */
    public static function asignar(Task $task, int $responsableUserId, array $auxiliaresUserIds = []): void
    {
        $auxiliares = self::activo()
            ? array_values(array_diff(array_unique(array_map('intval', $auxiliaresUserIds)), [$responsableUserId]))
            : [];

        $task->users()->sync([$responsableUserId, ...$auxiliares]);
        app(OrderParticipantsService::class)->fijar('task', $task->id, $responsableUserId,
            array_map(fn ($id) => ['user_id' => $id], $auxiliares));
    }

    /**
     * Valida los auxiliares pedidos (ids de talento_colaboradores): activos, con usuario, distintos del
     * responsable y sin pasar del máximo.
     *
     * @return Collection<TalentoColaborador>|string  los colaboradores o el mensaje de error
     */
    public static function resolverAuxiliares(array $colaboradorIds, int $responsableColaboradorId): Collection|string
    {
        $ids = array_values(array_unique(array_map('intval', $colaboradorIds)));
        if (! $ids) {
            return collect();
        }
        if (! self::activo()) {
            return 'Las órdenes en equipo todavía no están activas.';
        }
        if (in_array($responsableColaboradorId, $ids, true)) {
            return 'El responsable no puede ser también auxiliar.';
        }
        if (count($ids) > self::MAX_AUXILIARES) {
            return 'Una orden lleva como máximo ' . self::MAX_AUXILIARES . ' auxiliares.';
        }

        $colaboradores = TalentoColaborador::activos()->with('user')->whereIn('id', $ids)->whereNotNull('user_id')->get();
        if ($colaboradores->count() !== count($ids)) {
            return 'Alguno de los auxiliares ya no está activo o no tiene usuario; quítalo y elige a otra persona.';
        }

        return $colaboradores->sortBy(fn ($c) => array_search($c->id, $ids, true))->values();
    }
}
