<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskScheduleService;
use Carbon\Carbon;

/**
 * Regla ÚNICA de choque de agenda de las órdenes de trabajo de Talento (item #1227, nace en #1225).
 *
 * La usan los tres flujos que ponen una orden en la agenda de alguien:
 *  - Crear:       TalentoWorkOrderController::store
 *  - Editar:      OrdenPendienteService::editar
 *  - Reprogramar: AgendaOrdenesService::reprogramar (panel de Órdenes, «Mi día» del portal y app)
 *
 * Regla:
 *  - Una orden con hora ocupa la franja [inicio, inicio + 1 h) de la persona asignada.
 *  - Choca con otra orden ABIERTA de la misma persona que se encime con esa franja. Abierta =
 *    AgendaOrdenesService::ESTADOS_ABIERTOS (Pendiente o En curso), la misma definición del #1225:
 *    Completada/Validada (status Done) no cuentan y las eliminadas tampoco (SoftDeletes de tasks).
 *  - Sin hora (00:00) nunca choca: ocupa el día, no una franja. Ni la nueva ni las existentes.
 *  - Al editar o reprogramar se excluye la propia orden ($excluirTaskId).
 */
class ChoqueAgendaOrdenes
{
    /**
     * null si no choca. Si choca, la falla con el mismo formato de los servicios de órdenes:
     * ['ok' => false, 'codigo' => 422, 'mensaje' => …, 'choque' => id de la otra orden].
     */
    public function verificar(?User $usuario, ?Carbon $inicio, ?int $excluirTaskId = null): ?array
    {
        if (! $usuario || ! $inicio) {
            return null;
        }
        $otra = $this->buscar($usuario->id, $inicio, $excluirTaskId);
        if (! $otra) {
            return null;
        }

        return [
            'ok'      => false,
            'codigo'  => 422,
            'mensaje' => $this->mensaje($otra, $usuario),
            'choque'  => $otra->id,
        ];
    }

    /** Otra orden abierta del usuario que se encima con [inicio, inicio + 1 h), o null. */
    public function buscar(int $userId, Carbon $inicio, ?int $excluirTaskId = null): ?Task
    {
        if ($inicio->format('H:i') === '00:00') {
            return null;
        }
        $fin = $inicio->copy()->addHour();

        return Task::with('talentoType')
            ->whereNotNull('talento_type_id')
            ->whereIn('status', AgendaOrdenesService::ESTADOS_ABIERTOS)
            ->whereDate('start_date', $inicio->toDateString())
            ->when($excluirTaskId, fn ($q) => $q->whereKeyNot($excluirTaskId))
            ->whereHas('users', fn ($u) => $u->where('users.id', $userId))
            ->orderBy('start_time')
            ->get()
            ->first(function (Task $otra) use ($inicio, $fin) {
                $ini = TaskScheduleService::start($otra->start_date, $otra->start_time);
                if (! $ini || $ini->format('H:i') === '00:00') {
                    return false;
                }

                return $ini->lt($fin) && $inicio->lt($ini->copy()->addHour());
            });
    }

    /** Mismo texto en los tres flujos: con qué orden choca, de quién y a qué hora. */
    public function mensaje(Task $otra, ?User $usuario): string
    {
        $quien      = $usuario ? (trim($usuario->name . ' ' . $usuario->father_last_name) ?: "Usuario #{$usuario->id}") : 'Esta persona';
        $otraInicio = TaskScheduleService::start($otra->start_date, $otra->start_time);

        return "Choque de agenda: {$quien} ya tiene la orden #{$otra->id}"
            . ($otra->talentoType ? " ({$otra->talentoType->name})" : '')
            . ' el ' . $otraInicio->format('d/m') . ' a las ' . $otraInicio->format('H:i')
            . '. Elige otra hora u otra persona.';
    }
}
