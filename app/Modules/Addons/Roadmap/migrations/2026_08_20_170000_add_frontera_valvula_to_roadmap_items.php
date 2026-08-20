<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VÁLVULA DE NACIMIENTO — el veredicto se PERSISTE, porque no lo consume quien lo produce.
 *
 * La frontera dura no es un solo guard: `ThomasService::categoriaFronteraDura()` gobierna cuatro
 * puntos, y el que de verdad retiene los items de Irving es
 * `TorreAutomationPolicy::estadoInicial()` — «(1-4) FRONTERA DURA. Gana siempre, por delante de
 * todo. No se levanta desde ninguna configuración: ni con `autonomo`, ni con `override = auto`.»
 * Un item que sólo MENCIONA «producción» queda forzado a `requiere_irving` para siempre, aunque el
 * triaje ya lo haya leído como B.
 *
 * Por eso el veredicto de la válvula no puede vivir sólo en el momento del alta: se guarda en el
 * item para que ese guard —que corre después, en otro proceso y sin el texto delante— pueda leerlo.
 *
 * Valores: `mencion` (la válvula lo despejó), `accion` (confirmó que sí toca), NULL (no se evaluó,
 * o no se pudo preguntar → manda el keyword, comportamiento de siempre).
 *
 * ⚠️ Lo que esta columna NO hace, por regla de Irving (2026-08-20): **ninguna válvula de contexto
 * puede hacer que un item NAZCA auto-ejecutable.** Aflojar aquí acerca el item al camino normal
 * (triaje → revisor → autopilot); nunca le da `aprobado_irving`. El último control sigue puesto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'frontera_valvula')) {
                // varchar y no enum: es la foto de un veredicto, no un estado del dominio.
                $table->string('frontera_valvula', 16)->nullable()->after('nivel_riesgo_origen');
                $table->index('frontera_valvula');
            }
            if (! Schema::hasColumn('roadmap_items', 'frontera_valvula_at')) {
                $table->timestamp('frontera_valvula_at')->nullable()->after('frontera_valvula');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'frontera_valvula')) {
                $table->dropIndex(['frontera_valvula']);
                $table->dropColumn('frontera_valvula');
            }
            if (Schema::hasColumn('roadmap_items', 'frontera_valvula_at')) {
                $table->dropColumn('frontera_valvula_at');
            }
        });
    }
};
