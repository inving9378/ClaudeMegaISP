<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990831 (Fase 2 del tablero de pendientes, sub-item de #9990816).
 * DECISIÓN YA TOMADA por Irving (q2 del item, opción recomendada): permiso PROPIO
 * 'talento.documentos.recordar' para la ACCIÓN de enviar el recordatorio por WhatsApp —
 * distinto de 'talento.documentos.ver-todos' (creado en #9990830, Fase 1), que solo gatea el
 * LISTADO. Mismo precedente que esa migración: no existe rol 'RH' en el sistema hoy -> se
 * asigna solo a los roles base (super-administrator + DESARROLLADOR) vía
 * syncPermissionToBaseRoles, sin rol RH. Forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'talento.documentos.recordar', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('talento.documentos.recordar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
