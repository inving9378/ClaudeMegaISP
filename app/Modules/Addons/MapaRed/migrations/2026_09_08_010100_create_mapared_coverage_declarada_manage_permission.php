<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * MR-22 Fase 2c-2 (item roadmap #9990540). Las rutas `/mapa-red/api/cobertura-declarada/**`
 * ya quedan gateadas a nivel URL por el permiso de ruta `mapa_red_view` (patrón
 * `/mapa-red/**` de config/route_permission.php, un solo permiso por patrón — igual que el
 * resto del módulo). Para diferenciar lectura de escritura se declara este permiso granular
 * NUEVO (nombre distinto del namespace de MR-26, que usa `mapared.cobertura.*` si lo tuviera)
 * y se verifica INLINE en el controller (`CoberturaDeclaradaController::store/update/destroy`),
 * mismo patrón de defensa en profundidad que `OLTsOnuController` (item #287) y
 * `payments.captura.aplicar` (item #843/#850). Aditiva/idempotente, forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permiso = 'mapared.cobertura_declarada.manage';

        Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles($permiso);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierten permisos. down() vacío a propósito.
    }
};
