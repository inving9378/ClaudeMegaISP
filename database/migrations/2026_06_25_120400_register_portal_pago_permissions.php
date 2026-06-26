<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;

/**
 * Permisos del Portal de Pago Meganet.
 *
 * Fuente canónica = module.json (permissions:sync-roles los reparte). Esta
 * migración es defensa en profundidad: garantiza que existan también en una
 * BD recién migrada sin haber corrido module:lifecycle install. Idempotente.
 *
 * Regla de reparto (vía PermissionSyncService):
 *   - super-administrator y DESARROLLADOR reciben TODOS.
 *   - cualquier permiso .view se reparte a todos los demás roles base.
 */
return new class extends Migration
{
    private array $permissions = [
        'pagos.view'           => 'Ver Portal de Pago: bandeja de conciliación, cuentas de cobro y ligas (lectura)',
        'pagos.conciliar'      => 'Aprobar o rechazar conciliaciones manualmente desde la bandeja de revisión',
        'pagos.cuentas.manage' => 'Crear, editar y activar/desactivar cuentas de cobro (CLABEs Meganet)',
        'pagos.links.manage'   => 'Generar, reenviar y expirar ligas de pago de facturas',
    ];

    public function up(): void
    {
        $sync = app(PermissionSyncService::class);

        foreach ($this->permissions as $name => $description) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if (! $permission) {
                try {
                    Permission::create([
                        'name'        => $name,
                        'guard_name'  => 'web',
                        'description' => $description,
                    ]);
                } catch (PermissionAlreadyExists $e) {
                    // carrera/duplicado: ya existe, continuar.
                }
            }

            $sync->syncPermissionToBaseRoles($name);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->permissions) as $name) {
            // keep_data:true — no eliminamos permisos Spatie al revertir.
            // Dejar explícito el motivo en vez de borrar y romper roles.
        }
    }
};
