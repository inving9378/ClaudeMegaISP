<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-21 (item roadmap #957, ampliación 2026-10-09) — "potencia de instalación" del NAP: la
 * referencia contra la que se compara la potencia actual más baja de sus ONUs para detectar
 * degradación (fibra sucia, conector dañado, pérdida de empalme nueva). Se autocaptura con la
 * primera lectura real tras la instalación (MapaRedNapHealthService::calcular()) y queda
 * editable a mano para corregirla — decisión de Irving, sesión 2026-10-09.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mapared_layers', 'potencia_instalacion_dbm')) {
            Schema::table('mapared_layers', function (Blueprint $table) {
                $table->decimal('potencia_instalacion_dbm', 5, 2)->nullable()->after('level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mapared_layers', 'potencia_instalacion_dbm')) {
            Schema::table('mapared_layers', function (Blueprint $table) {
                $table->dropColumn('potencia_instalacion_dbm');
            });
        }
    }
};
