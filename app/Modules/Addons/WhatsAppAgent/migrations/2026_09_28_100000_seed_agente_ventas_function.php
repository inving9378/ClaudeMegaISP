<?php

use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use Illuminate\Database\Migrations\Migration;

/**
 * Agente de Ventas (IA) — función NUEVA y deliberadamente SEPARADA de 'ventas'.
 * Tiene su propio cerebro/prompt (AgenteVentasIAService) y su propio listener
 * (AgenteVentasTextListener): cero código ni prompt compartido con el bot
 * genérico que hoy usan Soporte/Cobranza/Atención/Ventas, para que un ajuste
 * a uno nunca "cruce cables" con el otro (decisión explícita de Irving,
 * 2026-09-28).
 *
 * exclusive=true, mismo criterio que las demás (solo puede vivir en UNA línea
 * a la vez). Sin asignar a ninguna línea por defecto: se asigna desde el panel
 * (/whatsapp/instances) cuando se decida activar el agente. Idempotente
 * (firstOrCreate por slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        WhatsAppFunction::firstOrCreate(
            ['slug' => 'agente_ventas'],
            [
                'name'      => 'Agente de Ventas (IA)',
                'exclusive' => true,
                'active'    => true,
                'position'  => 10,
            ]
        );
    }

    public function down(): void
    {
        WhatsAppFunction::where('slug', 'agente_ventas')->forceDelete();
    }
};
