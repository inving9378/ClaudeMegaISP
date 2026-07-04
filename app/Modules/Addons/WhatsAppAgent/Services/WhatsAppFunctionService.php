<?php

namespace App\Modules\Addons\WhatsAppAgent\Services;

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstanceFunction;
use Illuminate\Support\Facades\DB;

/**
 * Punto único de verdad de la capa de funciones. assign mueve exclusivas y no duplica.
 *
 * REGLA DE NEGOCIO (decisión de Irving, permanente): una función SÍ puede quedar sin
 * línea. unassign ya NO bloquea la última asignación y no hay guard al borrar una línea
 * dueña única (los observers backstop se eliminaron). El aviso vive en la UI.
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
     * Quitar una función de una línea. Puede dejar la función SIN líneas (0) — decisión
     * de Irving: permitido, con aviso en la UI. Ya NO bloquea la última asignación.
     */
    public function unassign(WhatsAppInstance $instance, WhatsAppFunction $function): void
    {
        WhatsAppInstanceFunction::where('instance_id', $instance->id)
            ->where('function_id', $function->id)
            ->delete();
    }

    /**
     * Mover una función de una línea a otra en una transacción. Sigue disponible (útil),
     * pero YA NO es obligatorio antes de quitar (la función puede quedar sin línea).
     * Attach al destino primero, luego detach del origen.
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
}
