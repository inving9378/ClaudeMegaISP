<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990651 (Fase 2 de #9990647 — "Completar documento"). Nombre exacto y decisión
 * (permiso Spatie nuevo, no reusar uno existente) aprobados por Irving en el brief del item
 * (pregunta q3, opción recomendada). Gate del nuevo endpoint POST .../documentos/{docId}/completar
 * — mismo patrón que 2026_09_05_220000_create_talento_expediente_documentos_permissions.php
 * (crea el permiso + lo asigna a los roles base super-administrator + DESARROLLADOR).
 */
return new class extends Migration
{
    private const PERMISO = 'talento.documentos.completar';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
