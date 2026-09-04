<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 4 del item #843 (roadmap #850). `ManualPaymentController` gatea
 * create()/buscarCliente()/store()/descargarComprobante() con el MISMO
 * permiso de ruta (`payments_capture_manage`) — pero solo `store()` mueve
 * dinero (aplica el pago vía PaymentApplicationService). Se declara
 * `payments.captura.aplicar` como permiso granular NUEVO (convención
 * modulo.recurso.accion, decisión q3 de Irving) para diferenciar esa acción
 * en el log-only de la Fase 4 (ver ChecksActionPermission) sin tocar el
 * permiso de ruta existente ni los roles que ya operan con él.
 * Aditiva/idempotente: crea el permiso y lo asigna a los roles base
 * (super-administrator + DESARROLLADOR). Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permiso = 'payments.captura.aplicar';

        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles($permiso);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierten permisos. down() vacío a propósito.
    }
};
