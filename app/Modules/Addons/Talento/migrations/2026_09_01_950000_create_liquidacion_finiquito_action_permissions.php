<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 4 (cont.) del item #843 (roadmap #850 → #863). `TalentoLiquidacionController` gatea
 * calcular()/cerrar() con el MISMO permiso de ruta (`talento.liquidation.manage`), pero solo
 * calcular() escribe en talento_ledger_entries (salary_base/overproduction/fund_contribution/
 * loan_repayment vía LiquidationService::calculate()); cerrar() solo cambia el status. Mismo
 * patrón en `TalentoLoanSettlementController`: draftSettlement()/closeSettlement() comparten
 * `talento.settlement.manage`, pero solo closeSettlement() escribe ledger (fund_return vía
 * SettlementService::close()). Se declaran 2 permisos granulares nuevos (convención
 * modulo.recurso.accion, mismo patrón que #850 para ManualPaymentController) para diferenciar
 * esas acciones en el log-only de la Fase 4 (ver ChecksActionPermission) sin tocar los permisos
 * de ruta existentes ni los roles que ya operan con ellos. Aditiva/idempotente: crea los permisos
 * y los asigna a los roles base (super-administrator + DESARROLLADOR). Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permisos = [
            'talento.liquidacion.calcular',
            'talento.finiquito.cerrar',
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
