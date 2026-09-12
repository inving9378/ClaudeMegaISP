<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 3c (parte 2) de #9990778 — KPI card de cobertura del bridge
 * seller_id -> colaborador_id en el dashboard admin (item #9990964). Solo
 * super-administrator + DESARROLLADOR (política del servicio central):
 * no termina en `.view`, así que syncPermissionToBaseRoles no la reparte
 * al resto de roles.
 */
return new class extends Migration
{
    private const PERMISSION = 'dashboard_view_card_identidad_cobertura';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['description' => 'Ver la tarjeta de cobertura del bridge de identidad (cliente↔colaborador) en el dashboard']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISSION);

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
