<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9991221 (decisión de Irving 2026-09-18, q1 opción 1). Hallazgo original
 * (#9991219): TextTemplate.vue (botón "Previsualizar" del generador de contratos) llamaba
 * a la ruta de Administración (`/administracion/document_template/show_content_template`,
 * permiso `documentos.view`/`config_view_system`) desde Clientes (`PlantillasClientes.vue`)
 * y CRM (`CrmTemplate.vue`) — no solo desde el módulo Administración.
 *
 * Verificado ANTES de este fix (q3 del item, "¿fuga de permisos o solo bug de UX?"):
 * NINGÚN rol operativo (Vendedor 26 usuarios, Mostrador, TECNICO, Almacen, Socio,
 * SUPERVISOR_MOSTRADOR) tenía `documentos.view`/`config_view_system` — solo los roles
 * admin-tier (super-administrator, Super Administrador, Administrador, DESARROLLADOR,
 * ADMINISTRADOR_COMPLETO) los tenían. Conclusión: NO había fuga de permisos (nadie veía
 * algo que no debía) — era un bug funcional real: cualquier Vendedor/Mostrador/TECNICO/
 * Almacen que intentara "Previsualizar" una plantilla desde la ficha de un cliente o de
 * un lead CRM recibía 403 silencioso.
 *
 * Fix (routes.php de core-documentos + config/route_permission.php, mismo commit): ruta
 * neutral `/plantillas/preview` reusando el MISMO controller/método
 * (DocumentTemplateController::showContentTemplate, sin duplicar lógica), gateada por este
 * permiso nuevo `plantillas.preview`. La ruta de Administración queda intacta (la sigue
 * usando TemplateManager.vue).
 *
 * Roles a los que se asigna: los mismos que hoy tienen `client_edit_client` y/o
 * `crm_edit_crm` (los que de verdad usan las pantallas de Clientes/CRM) — medido en BD:
 * super-administrator, Super Administrador, Administrador, DESARROLLADOR (ya cubiertos
 * por syncPermissionToBaseRoles, full-access), Mostrador, Vendedor, TECNICO, Almacen,
 * Socio, ADMINISTRADOR_COMPLETO, SUPERVISOR_MOSTRADOR.
 */
return new class extends Migration
{
    private const PERMISSION = 'plantillas.preview';

    private const ROLES_OPERATIVOS = [
        'Mostrador',
        'Vendedor',
        'TECNICO',
        'Almacen',
        'Socio',
        'ADMINISTRADOR_COMPLETO',
        'SUPERVISOR_MOSTRADOR',
        'Super Administrador',
        'Administrador',
    ];

    public function up(): void
    {
        $perm = Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['description' => 'Previsualizar una plantilla de documento desde Clientes o CRM (ruta neutral, separada de Administración)']
        );

        // super-administrator + DESARROLLADOR (full-access) — siempre.
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISSION);

        // No termina en ".view": syncPermissionToBaseRoles no lo reparte a los demás roles
        // base. Se asigna explícito a los roles que de verdad usan Clientes/CRM.
        foreach (self::ROLES_OPERATIVOS as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $perm = Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
