<?php

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstance;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstanceFunction;
use Illuminate\Database\Migrations\Migration;

/**
 * FIX 2 — Función de línea "Conciliación de pagos".
 *
 * 5ª función del catálogo (junto a ventas/cobranza/soporte/atencion). La UI de
 * instancias es dinámica (WhatsAppInstanceManager.vue itera functions-catalog =
 * WhatsAppFunction::active()), así que con esta fila la casilla aparece sola —
 * NO requiere npm run prod.
 *
 * Con la casilla, los listeners de conciliación (ConciliationListener /
 * ConciliationTextListener) gatean por hasFunction('conciliacion'): una línea sin
 * la casilla ignora los comprobantes por completo (Opción A, apagado total).
 *
 * Asignación aditiva a 'pruebasdev' (la línea donde se construyó/probó la
 * conciliación) para que el flujo siga vivo; GUARDADA por existencia → en prod,
 * donde 'pruebasdev' no existe, es no-op. Demás líneas: opt-in desde el panel.
 *
 * Idempotente (updateOrCreate por slug + firstOrCreate del pivote). down() borra
 * la función (y su pivote).
 */
return new class extends Migration
{
    public function up(): void
    {
        $function = WhatsAppFunction::updateOrCreate(
            ['slug' => 'conciliacion'],
            [
                'name'      => 'Conciliación de pagos',
                'exclusive' => true,
                'active'    => true,
                'position'  => 4,
            ]
        );

        // Asignación aditiva a pruebasdev (solo si la línea existe → prod = no-op).
        $instance = WhatsAppInstance::where('slug', 'pruebasdev')->first();
        if ($instance) {
            WhatsAppInstanceFunction::firstOrCreate(
                ['instance_id' => $instance->id, 'function_id' => $function->id],
                ['assigned_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $function = WhatsAppFunction::where('slug', 'conciliacion')->first();
        if ($function) {
            WhatsAppInstanceFunction::where('function_id', $function->id)->delete();
            $function->forceDelete();
        }
    }
};
