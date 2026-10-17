<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de Configuración → Portal Cliente. Aditiva/idempotente: crea los tres permisos y los
 * asigna solo a los roles base (super-administrator + DESARROLLADOR). Los nombres no terminan en
 * `.view`, así que el auto-reparto de permisos de lectura a otros roles nunca los toma: a
 * cualquier otro rol se le otorgan a mano desde Administración → Roles.
 *
 * - ver:      abrir el panel, ver borrador/publicada/historial y la vista previa.
 * - editar:   subir imágenes, guardar o descartar el borrador y restaurar versiones al borrador.
 * - publicar: hacer visibles los cambios para los clientes.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'portal-cliente.apariencia.ver',
        'portal-cliente.apariencia.editar',
        'portal-cliente.apariencia.publicar',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            app(PermissionSyncService::class)->syncPermissionToBaseRoles($nombre);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierten permisos (pueden estar asignados a roles). down() vacío a propósito.
    }
};
