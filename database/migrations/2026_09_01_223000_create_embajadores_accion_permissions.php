<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 4 (cont.) del item #843 (roadmap #862, sub-item de #850). Replica en
 * Embajadores/comisiones el mismo patrón ya aplicado a ManualPaymentController:
 * `embajadores.commissions.approve`/`.cancel` YA diferencian por RUTA las
 * acciones de aprobar/cancelar comisiones (config/route_permission.php) — sin
 * hueco ahí. El hueco real está en los controladores de la API móvil
 * (EmbajadorApiController/EmbajadorExtApiController), que no tienen NINGÚN
 * permiso de ruta (solo `auth:sanctum` + self-scoping por cliente) y cubren
 * acciones que sí cambian estado: `activate()` (alta de embajador) y
 * `aplicarRecompensa()` (marca una recompensa como aplicada). Se declaran 2
 * permisos granulares NUEVOS (convención modulo.recurso.accion, decisión q1
 * de Irving en #862 — alcance completo de acciones sensibles) para
 * diferenciarlas en el log-only de la Fase 4 (ver ChecksActionPermission) sin
 * tocar el permiso de ruta existente ni los roles que ya operan con él.
 * Aditiva/idempotente: crea los permisos y los asigna a los roles base
 * (super-administrator + DESARROLLADOR). Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            'embajadores.programa.activar',
            'embajadores.recompensa.aplicar',
        ];

        $sync = app(PermissionSyncService::class);

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $sync->syncPermissionToBaseRoles($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierten permisos. down() vacío a propósito.
    }
};
