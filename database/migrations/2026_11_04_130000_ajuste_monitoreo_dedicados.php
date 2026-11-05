<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * #988 — Interruptor «Monitoreo de dedicados» de Configuración, encendido de inicio, y su permiso
 * (solo super-administrator y DESARROLLADOR). El deploy no corre permissions:sync-roles: sin esta
 * migración el permiso no existiría. No pisa un ajuste que ya exista.
 */
return new class extends Migration
{
    private const AJUSTE = 'metricas_monitoreo';

    private const PERMISO = 'metricas.monitoreo.configurar';

    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => self::AJUSTE, 'value' => json_encode(['activo' => true]), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Encender o apagar el monitoreo de servicios dedicados en Configuración']
        );
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::AJUSTE)->delete();
        Permission::where('name', self::PERMISO)->delete();
    }
};
