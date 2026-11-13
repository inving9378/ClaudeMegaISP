<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Marketing IA 360 · Fase 0 — permiso para administrar empresas (marcas) y su equipo.
 *
 * Se crea por migración (la versión del módulo no se sube a mitad del plan) y se reparte de forma
 * ADITIVA (givePermissionTo, vía PermissionSyncService) solo a super-administrator y DESARROLLADOR.
 * A cualquier otra persona se lo da Irving/Henry desde el editor de roles.
 */
return new class extends Migration
{
    private const PERMISO = 'marketing.empresas.gestionar';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        // Catálogo de permisos: no se revierte (mismo criterio que el resto de migraciones de permisos del proyecto).
    }
};
