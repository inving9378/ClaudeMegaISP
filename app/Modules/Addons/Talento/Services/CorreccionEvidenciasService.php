<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Support\OrdenReferencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correcciones de evidencia (#1286). Dos formas, con la misma tabla:
 *
 * - **Devolución** (revisión del supervisor, con `validacion_separada` encendido): la orden que el
 *   técnico terminó espera al supervisor; si algo no quedó bien, la devuelve con una nota. La orden
 *   regresa a En curso en la app con esa nota, el técnico corrige (sus fotos nuevas quedan marcadas)
 *   y la vuelve a terminar; el supervisor la valida y entonces cuentan sus puntos.
 * - **Corrección** sobre una orden ya validada: la orden no cambia de estado ni de puntos; el técnico
 *   sube las fotos y la envía, y el supervisor la da por buena.
 *
 * Estados: «pedida» (espera al técnico), «enviada» (espera al supervisor) y cerrada, con su cierre:
 * aceptada, cancelada o reemplazada (se pidió otra encima de una enviada).
 */
class CorreccionEvidenciasService
{
    public const CIERRES = ['aceptada', 'cancelada', 'reemplazada'];

    private static ?bool $conTabla = null;

    /** Sin la migración de #1286 no hay correcciones: nada cuenta como abierta. */
    public static function disponible(): bool
    {
        return self::$conTabla ??= Schema::hasTable('talento_orden_correcciones');
    }

    /** La corrección abierta (pedida o enviada) de una orden, o null. */
    public function abierta(int $taskId): ?array
    {
        if (! self::disponible()) {
            return null;
        }
        $c = $this->consultaAbiertas()->where('c.tarea_id', $taskId)->orderByDesc('c.id')->first();

        return $c ? $this->formato($c) : null;
    }

    /** Todas las abiertas, por id de orden (son pocas): para las listas, sin una consulta por fila. */
    public function abiertasPorTarea(): array
    {
        if (! self::disponible()) {
            return [];
        }

        return $this->consultaAbiertas()->orderBy('c.id')->get()
            ->mapWithKeys(fn ($c) => [(int) $c->tarea_id => $this->formato($c)])
            ->all();
    }

    /** La corrección que espera fotos del técnico, o null. */
    public function pedida(int $taskId): ?object
    {
        if (! self::disponible()) {
            return null;
        }

        return DB::table('talento_orden_correcciones')
            ->where('tarea_id', $taskId)->whereNull('cerrada_at')->whereNull('enviada_at')
            ->orderByDesc('id')->first();
    }

    /** Todas las de una orden, de la primera a la última (documentación de la orden). */
    public function historial(int $taskId): array
    {
        if (! self::disponible()) {
            return [];
        }

        return DB::table('talento_orden_correcciones')->where('tarea_id', $taskId)->orderBy('id')->get()
            ->map(fn ($c) => [
                'id'           => (int) $c->id,
                'estado'       => $this->estado($c),
                'motivo'       => $c->motivo,
                'pedida_at'    => $c->pedida_at,
                'pedida_por'   => $this->nombre($c->pedida_por),
                'enviada_at'   => $c->enviada_at,
                'nota_tecnico' => $c->nota_tecnico,
                'cerrada_at'   => $c->cerrada_at,
                'cerrada_por'  => $this->nombre($c->cerrada_por),
                'cierre'       => $c->cierre,
            ])->values()->all();
    }

    /**
     * Filtra una consulta de `tasks` a las que tienen una corrección abierta: 'pedida' (espera al
     * técnico), 'enviada' (espera al supervisor) o null (cualquiera de las dos).
     */
    public function scopeConAbierta(Builder $query, ?string $estado = null): Builder
    {
        if (! self::disponible()) {
            return $query->whereRaw('1=0');
        }

        return $query->whereExists(fn ($q) => $q->select(DB::raw(1))
            ->from('talento_orden_correcciones as c')
            ->whereColumn('c.tarea_id', 'tasks.id')
            ->whereNull('c.cerrada_at')
            ->when($estado === 'pedida', fn ($w) => $w->whereNull('c.enviada_at'))
            ->when($estado === 'enviada', fn ($w) => $w->whereNotNull('c.enviada_at')));
    }

    /**
     * El supervisor pide una corrección. Solo sobre órdenes terminadas; si ya hay una pedida hay
     * que esperarla o cancelarla, y si hay una enviada sin revisar queda reemplazada por la nueva.
     */
    public function pedir(Task $task, string $motivo, int $userId): array
    {
        if ($task->trashed() || $task->status !== 'Done') {
            return ['success' => false, 'message' => 'Solo se pide una corrección sobre una orden terminada.', 'status_code' => 422];
        }
        if (! $task->validated_at) {
            return ['success' => false, 'message' => 'Esta orden todavía no se valida: devuélvela al técnico con una nota.', 'status_code' => 422];
        }
        if ($this->pedida($task->id)) {
            return ['success' => false, 'message' => 'Ya hay una corrección pedida en esta orden: espera a que el técnico la envíe o cancélala.', 'status_code' => 422];
        }

        DB::transaction(fn () => $this->abrir($task->id, $motivo, $userId));

        return ['success' => true];
    }

    /**
     * Revisión del supervisor: devuelve al técnico, con una nota, la orden terminada que todavía no
     * valida. Regresa a En curso (OrdenTrabajoUnifiedService::devolver) y la nota queda como una
     * corrección pedida, que se envía sola cuando el técnico la vuelve a terminar.
     */
    public function devolver(Task $task, string $motivo, int $userId): array
    {
        if (! config('unificacion.interruptores.validacion_separada')) {
            return ['success' => false, 'message' => 'La revisión del supervisor está apagada: la orden quedó validada al terminarse. Usa «Pedir corrección».', 'status_code' => 422];
        }
        if ($task->trashed() || $task->status !== 'Done' || $task->validated_at) {
            return ['success' => false, 'message' => 'Solo se devuelve una orden terminada que todavía no se valida.', 'status_code' => 422];
        }

        return DB::transaction(function () use ($task, $motivo, $userId) {
            $r = app(OrdenTrabajoUnifiedService::class)->devolver($task->id, $motivo, OrdenReferencia::ORIGEN_TAREA);
            if (! $r['success']) {
                return $r;
            }
            $this->abrir($task->id, $motivo, $userId);

            return ['success' => true];
        });
    }

    /** El técnico volvió a terminar una orden devuelta: su corrección queda enviada al supervisor. */
    public function alCompletar(int $taskId, ?int $userId, ?string $nota): void
    {
        $c = $this->pedida($taskId);
        if (! $c) {
            return;
        }
        $nota = trim((string) $nota);
        DB::table('talento_orden_correcciones')->where('id', $c->id)->update([
            'enviada_at' => now(), 'enviada_por' => $userId, 'nota_tecnico' => $nota === '' ? null : $nota, 'updated_at' => now(),
        ]);
    }

    /** El supervisor validó la orden: lo que quedara abierto de su revisión queda aceptado. */
    public function alValidar(int $taskId, ?int $userId): void
    {
        if (! self::disponible()) {
            return;
        }
        DB::table('talento_orden_correcciones')->where('tarea_id', $taskId)->whereNull('cerrada_at')
            ->update(['cerrada_at' => now(), 'cerrada_por' => $userId, 'cierre' => 'aceptada', 'updated_at' => now()]);
    }

    /** Cierra lo abierto (reemplazada) y abre una corrección pedida. */
    private function abrir(int $taskId, string $motivo, int $userId): void
    {
        DB::table('talento_orden_correcciones')
            ->where('tarea_id', $taskId)->whereNull('cerrada_at')
            ->update(['cerrada_at' => now(), 'cerrada_por' => $userId, 'cierre' => 'reemplazada', 'updated_at' => now()]);
        DB::table('talento_orden_correcciones')->insert([
            'tarea_id' => $taskId, 'motivo' => $motivo, 'pedida_por' => $userId, 'pedida_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** El supervisor la da por buena (ya enviada) o la cancela (en cualquier momento). */
    public function cerrar(int $taskId, string $cierre, int $userId): array
    {
        $c = self::disponible()
            ? DB::table('talento_orden_correcciones')->where('tarea_id', $taskId)->whereNull('cerrada_at')->orderByDesc('id')->first()
            : null;
        if (! $c) {
            return ['success' => false, 'message' => 'Esta orden ya no tiene una corrección abierta. Actualiza la lista.', 'status_code' => 422];
        }
        if ($cierre === 'aceptada' && ! $c->enviada_at) {
            return ['success' => false, 'message' => 'El técnico todavía no envía la corrección.', 'status_code' => 422];
        }

        DB::table('talento_orden_correcciones')->where('id', $c->id)
            ->update(['cerrada_at' => now(), 'cerrada_por' => $userId, 'cierre' => $cierre, 'updated_at' => now()]);

        return ['success' => true];
    }

    /** El técnico avisa que ya subió las fotos; necesita al menos una. */
    public function enviar(int $taskId, int $userId, ?string $nota): array
    {
        $c = $this->pedida($taskId);
        if (! $c) {
            return ['success' => false, 'message' => 'Esta orden no tiene una corrección pendiente.', 'code' => 'SIN_CORRECCION', 'status_code' => 422];
        }
        if (DB::table('tasks')->where('id', $taskId)->value('status') !== 'Done') {
            return ['success' => false, 'message' => 'Tu supervisor te devolvió esta orden: corrígela y termínala con «Completar OT».', 'code' => 'ES_DEVOLUCION', 'status_code' => 422];
        }
        if (! DB::table('talento_work_order_media')->where('correccion_id', $c->id)->exists()) {
            return ['success' => false, 'message' => 'Sube al menos una foto de la corrección antes de enviarla.', 'code' => 'SIN_FOTOS', 'status_code' => 422];
        }

        $nota = trim((string) $nota);
        DB::table('talento_orden_correcciones')->where('id', $c->id)->update([
            'enviada_at' => now(), 'enviada_por' => $userId, 'nota_tecnico' => $nota === '' ? null : $nota, 'updated_at' => now(),
        ]);

        return ['success' => true];
    }

    private function consultaAbiertas()
    {
        return DB::table('talento_orden_correcciones as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.pedida_por')
            ->leftJoin('tasks as t', 't.id', '=', 'c.tarea_id')
            ->whereNull('c.cerrada_at')
            ->select(['c.*', 'u.name', 'u.father_last_name', 't.status as tarea_status', 't.validated_at as tarea_validada'])
            ->selectSub(fn ($q) => $q->from('talento_work_order_media')->whereColumn('correccion_id', 'c.id')->selectRaw('count(*)'), 'fotos');
    }

    private function formato(object $c): array
    {
        return [
            'id'           => (int) $c->id,
            'estado'       => $this->estado($c),
            // devolucion = la orden todavía no se valida (está con el técnico o espera al supervisor);
            // correccion = sobre una orden ya validada.
            'modo'         => ($c->tarea_status ?? null) === 'Done' && ! empty($c->tarea_validada) ? 'correccion' : 'devolucion',
            'motivo'       => $c->motivo,
            'pedida_at'    => $c->pedida_at,
            'pedida_por'   => trim(($c->name ?? '') . ' ' . ($c->father_last_name ?? '')) ?: null,
            'enviada_at'   => $c->enviada_at,
            'nota_tecnico' => $c->nota_tecnico,
            'fotos'        => (int) ($c->fotos ?? 0),
        ];
    }

    private function estado(object $c): string
    {
        return $c->cerrada_at ? 'cerrada' : ($c->enviada_at ? 'enviada' : 'pedida');
    }

    private function nombre($userId): ?string
    {
        if (! $userId) {
            return null;
        }
        $u = DB::table('users')->where('id', $userId)->first(['name', 'father_last_name']);

        return $u ? (trim($u->name . ' ' . ($u->father_last_name ?? '')) ?: null) : null;
    }
}
