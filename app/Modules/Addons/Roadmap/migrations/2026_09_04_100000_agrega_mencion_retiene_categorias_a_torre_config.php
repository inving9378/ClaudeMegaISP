<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito CC #9990256 (sub-item de #9990210). Expone `mencion_retiene_categorias` (hasta hoy
 * SÓLO en `config('circuito.mencion_retiene_categorias')`, leída directo por `JarvisService`) en
 * `torre_config`, mismo patrón que `autopilot_max_nivel`/`paralelo_mismo_modulo`: NULL = lo
 * gobierna `config/circuito.php` (estado de fábrica, sin cambio de comportamiento al migrar); un
 * array JSON puesto desde la pantalla manda — incluido `[]`, que es la decisión EXPLÍCITA de que
 * ninguna categoría retiene una mención (ver `MencionFrontera::retiene()`).
 *
 * La lectura real (`JarvisService::fronteraDuraDeItemDetalle()`) se re-cablea aparte, en el mismo
 * item, para resolver vía `TorreConfig::mencionRetieneCategorias()` — aquí solo se siembra la
 * columna, aditiva y sin efecto hasta que algo la lea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (! Schema::hasColumn('torre_config', 'mencion_retiene_categorias')) {
                $table->json('mencion_retiene_categorias')->nullable()->after('auditor_gasto_reintento_activo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('torre_config', function (Blueprint $table) {
            if (Schema::hasColumn('torre_config', 'mencion_retiene_categorias')) {
                $table->dropColumn('mencion_retiene_categorias');
            }
        });
    }
};
