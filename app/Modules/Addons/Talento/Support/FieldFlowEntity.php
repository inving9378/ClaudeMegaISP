<?php

namespace App\Modules\Addons\Talento\Support;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve el "origen" real de un id de orden de campo: talento_work_orders
 * o tasks (tipo=campo). Las tablas hijas del flujo (media, firmas, ia,
 * activación, encuesta) tienen dos columnas —work_order_id y tarea_id—
 * porque las dos secuencias de ids son independientes; nunca hacer OR entre
 * ambas sin resolver primero el origen, o dos órdenes con el mismo número
 * podrían mezclarse.
 *
 * Punto único de esta resolución — antes de este helper vivía duplicada
 * (con leves variantes) en FieldFlowService::resolveEntity(),
 * TalentoFieldFlowController::resolveFieldFlowOwnerColumn() y el bloque
 * inline de storeSignature(); las tres delegan aquí ahora.
 */
class FieldFlowEntity
{
    /**
     * @return array{origen:string,model:TalentoWorkOrder|Task,fk:string}|null
     */
    public static function resolve(int $id): ?array
    {
        $wo = TalentoWorkOrder::find($id);
        if ($wo) {
            return ['origen' => 'work_order', 'model' => $wo, 'fk' => 'work_order_id'];
        }

        $task = Task::where('id', $id)
            ->whereNotNull('talento_type_id')
            ->first();
        if ($task) {
            return ['origen' => 'task', 'model' => $task, 'fk' => 'tarea_id'];
        }

        return null;
    }

    /**
     * Igual que resolve() pero acepta una referencia con origen (T-<id>/L-<id>) cuando
     * unificacion.referencia_orden está ON; OFF = orden de búsqueda actual, solo numérica.
     * Ambigua/inválida/no encontrada → null (el llamador decide el 409/404 con OrdenReferencia::resolve).
     */
    public static function resolveRef(string|int $ref): ?array
    {
        if (! config('unificacion.interruptores.referencia_orden')) {
            return ctype_digit((string) $ref) ? self::resolve((int) $ref) : null;
        }
        $r = OrdenReferencia::resolve($ref);
        if ($r['estado'] !== OrdenReferencia::OK) {
            return null;
        }
        return $r['origen'] === OrdenReferencia::ORIGEN_ANTIGUA
            ? ['origen' => 'work_order', 'model' => $r['model'], 'fk' => 'work_order_id']
            : ['origen' => 'task', 'model' => $r['model'], 'fk' => 'tarea_id'];
    }

    /** 'work_order_id' si $id es una OT real; 'tarea_id' en cualquier otro caso. */
    public static function fkColumn(int $id): string
    {
        return TalentoWorkOrder::whereKey($id)->exists() ? 'work_order_id' : 'tarea_id';
    }

    public static function clientIdForTask(Task $task): ?int
    {
        if (! $task->client_main_information_id) {
            return null;
        }
        return DB::table('client_main_information')
            ->where('id', $task->client_main_information_id)
            ->value('client_id');
    }
}
