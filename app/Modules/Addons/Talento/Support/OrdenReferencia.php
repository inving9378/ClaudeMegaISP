<?php

namespace App\Modules\Addons\Talento\Support;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;

/**
 * Referencia inequívoca de una orden de campo con su origen (UNI-T E1).
 *
 *   T-<id>  → tasks (talento_type_id no nulo)      — flujo vigente
 *   L-<id>  → talento_work_orders                  — órdenes antiguas (solo lectura)
 *   <id>    → enlace numérico viejo: se resuelve solo si existe en UN origen; si existe en ambos, ambiguo.
 *
 * Punto único de resolución: FieldFlowEntity delega el caso de origen ya conocido aquí.
 */
class OrdenReferencia
{
    public const ORIGEN_TAREA = 'task';
    public const ORIGEN_ANTIGUA = 'work_order';

    public const OK = 'ok';
    public const AMBIGUA = 'ambigua';
    public const NO_ENCONTRADA = 'no_encontrada';
    public const INVALIDA = 'invalida';

    /** @return array{origen:?string,id:int}|null  origen null = numérica sin origen; null = no es referencia */
    public static function parse(string|int $ref): ?array
    {
        $ref = trim((string) $ref);
        if (preg_match('/^([TL])-(\d+)$/i', $ref, $m)) {
            return ['origen' => strtoupper($m[1]) === 'T' ? self::ORIGEN_TAREA : self::ORIGEN_ANTIGUA, 'id' => (int) $m[2]];
        }
        if (preg_match('/^\d+$/', $ref)) {
            return ['origen' => null, 'id' => (int) $ref];
        }
        return null;
    }

    public static function format(string $origen, int $id): string
    {
        return ($origen === self::ORIGEN_ANTIGUA ? 'L-' : 'T-') . $id;
    }

    /**
     * @return array{estado:string,origen?:string,id?:int,ref?:string,model?:Task|TalentoWorkOrder,candidatos?:array<int,string>}
     */
    public static function resolve(string|int $ref): array
    {
        $p = self::parse($ref);
        if (! $p) {
            return ['estado' => self::INVALIDA];
        }

        $id = $p['id'];
        $task = $p['origen'] !== self::ORIGEN_ANTIGUA ? self::tarea($id) : null;
        $wo = $p['origen'] !== self::ORIGEN_TAREA ? TalentoWorkOrder::find($id) : null;

        if ($task && $wo) {
            return [
                'estado' => self::AMBIGUA,
                'id' => $id,
                'candidatos' => [self::format(self::ORIGEN_TAREA, $id), self::format(self::ORIGEN_ANTIGUA, $id)],
            ];
        }
        if ($task) {
            return ['estado' => self::OK, 'origen' => self::ORIGEN_TAREA, 'id' => $id, 'ref' => self::format(self::ORIGEN_TAREA, $id), 'model' => $task];
        }
        if ($wo) {
            return ['estado' => self::OK, 'origen' => self::ORIGEN_ANTIGUA, 'id' => $id, 'ref' => self::format(self::ORIGEN_ANTIGUA, $id), 'model' => $wo];
        }
        return ['estado' => self::NO_ENCONTRADA, 'id' => $id];
    }

    private static function tarea(int $id): ?Task
    {
        return Task::where('id', $id)->whereNotNull('talento_type_id')->first();
    }
}
