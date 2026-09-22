<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El addon "Demo" (app/Modules/Addons/Demo/) es una plantilla de ejemplo para
 * probar el sistema modular — su propio module.json declara "active": false
 * como default de paquete, y su routes.php solo registra el endpoint de API
 * (/api/demo/items), sin ninguna ruta de página para /demo ni /demo/items.
 *
 * Quedó activado en la fila de module_registries desde una prueba del
 * 2026-05-29 que nunca se revirtió — así que el link "Demo" del sidebar
 * dinámico (visible para cualquiera con el permiso demo_view) apuntaba a una
 * URL sin ruta de página real, dando 404.
 *
 * Aditiva/reversible: solo apaga la bandera `active`; no borra el módulo ni
 * sus permisos — instalarlo de nuevo para pruebas sigue siendo tan simple
 * como volver a poner active=true.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registries')
            ->where('slug', 'addon-demo')
            ->update(['active' => false]);
    }

    public function down(): void
    {
        // Intencionalmente vacío: reactivar el módulo Demo es una decisión de
        // quien quiera probar el kit modular, no algo que este rollback deba
        // hacer solo.
    }
};
