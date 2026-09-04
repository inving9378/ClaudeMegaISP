<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 5b.3 (item roadmap #812) declaró `documentacion-corporativa.entrega.create`
 * como el permiso único para armar/descargar entregas (`EntregaController`),
 * pero nunca quedó una migración que lo creara — el permiso solo existía en la
 * BD compartida de dev (creado a mano durante la verificación de #812), lo que
 * lo dejaba ausente en cualquier instalación fresca / prod. Cerrado aquí
 * (item #834) porque #834 es quien primero trae el código de #812 a `main` y
 * depende de este mismo permiso para su propio `store()`/`index()`.
 * Aditiva/idempotente: crea el permiso y lo asigna a los roles base
 * (super-administrator + DESARROLLADOR), mismo patrón que el resto de
 * `grant_*_permission.php` del módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.entrega.create', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.entrega.create');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
