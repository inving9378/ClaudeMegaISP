<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Exceptions\WhatsAppFunctionException;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstanceFunction;
use Illuminate\Support\Facades\DB;

/**
 * Punto único de verdad de la capa de funciones. Opera con el MODELO del pivote
 * (create()/delete()) para que los observers backstop disparen. Reglas 2-5 aquí;
 * regla 6 (borrado de línea dueña única) en guardInstanceRemoval() + observer.
 */
class WhatsAppFunctionService
{
    /**
     * Bandera para permitir que un movimiento/reasignación quite la "última" asignación
     * SIN que el observer lo bloquee (porque inmediatamente se re-attacha en otra línea).
     */
    public static bool $reassigning = false;

    /**
     * Regla 2 — asignar una función a una línea.
     * Si la función es exclusiva y ya está en otra línea → la MUEVE (detach + attach) en
     * una transacción y devuelve ['moved'=>true,'from'=>instanceId] para que la UI confirme.
     * Si no es exclusiva → attach directo. Idempotente si ya estaba en esta línea.
     */
    public function assign(WhatsAppInstance $instance, WhatsAppFunction $function, ?int $userId = null): array
    {
        return DB::transaction(function () use ($instance, $function, $userId) {
            $existing = WhatsAppInstanceFunction::where('instance_id', $instance->id)
                ->where('function_id', $function->id)->first();
            if ($existing) {
                return ['moved' => false, 'already' => true, 'from' => null];
            }

            $moved = false;
            $from  = null;

            if ($function->exclusive) {
                $current = WhatsAppInstanceFunction::where('function_id', $function->id)->first();
                if ($current) {
                    $from = $current->instance_id;
                    self::$reassigning = true;   // permite quitar la "única" porque se re-attacha enseguida
                    try {
                        $current->delete();
                    } finally {
                        self::$reassigning = false;
                    }
                    $moved = true;
                }
            }

            WhatsAppInstanceFunction::create([
                'instance_id' => $instance->id,
                'function_id' => $function->id,
                'assigned_by' => $userId,
                'assigned_at' => now(),
            ]);

            return ['moved' => $moved, 'already' => false, 'from' => $from];
        });
    }

    /**
     * Regla 3/5 — quitar una función de una línea. Si es la ÚNICA asignación de esa
     * función → lanza excepción (no la deja huérfana; obliga a reasignar primero).
     */
    public function unassign(WhatsAppInstance $instance, WhatsAppFunction $function): void
    {
        $row = WhatsAppInstanceFunction::where('instance_id', $instance->id)
            ->where('function_id', $function->id)->first();
        if (! $row) {
            return; // no estaba asignada — no-op
        }

        if (WhatsAppInstanceFunction::where('function_id', $function->id)->count() <= 1) {
            throw WhatsAppFunctionException::wouldOrphan($function);
        }

        $row->delete();
    }

    /**
     * Regla 4 — mover una función de una línea a otra en una transacción.
     * Attach al destino primero, luego detach del origen (con la bandera para no bloquear).
     */
    public function reassign(WhatsAppFunction $function, WhatsAppInstance $from, WhatsAppInstance $to, ?int $userId = null): void
    {
        DB::transaction(function () use ($function, $from, $to, $userId) {
            WhatsAppInstanceFunction::firstOrCreate(
                ['instance_id' => $to->id, 'function_id' => $function->id],
                ['assigned_by' => $userId, 'assigned_at' => now()]
            );

            $row = WhatsAppInstanceFunction::where('function_id', $function->id)
                ->where('instance_id', $from->id)->first();
            if ($row) {
                self::$reassigning = true;
                try {
                    $row->delete();
                } finally {
                    self::$reassigning = false;
                }
            }
        });
    }

    /**
     * Regla 5/6 — antes de borrar/desconectar una línea: si es la ÚNICA dueña de ≥1
     * función → lanza excepción con la lista de funciones a reasignar (bloquea la operación).
     * Lo invoca el observer WhatsAppInstance::deleting (backstop ante cualquier ->delete()).
     */
    public function guardInstanceRemoval(WhatsAppInstance $instance): void
    {
        $sole = [];
        foreach ($instance->functionAssignments()->with('function')->get() as $assignment) {
            if (! $assignment->function) {
                continue;
            }
            $owners = WhatsAppInstanceFunction::where('function_id', $assignment->function_id)->count();
            if ($owners <= 1) {
                $sole[] = $assignment->function;
            }
        }

        if (! empty($sole)) {
            throw WhatsAppFunctionException::wouldOrphanOnRemoval($instance, $sole);
        }
    }
}
