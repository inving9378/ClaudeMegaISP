<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 5a (item roadmap #758). `documentacion-corporativa.solicitud.manage`
 * gatea crear/editar/eliminar solicitudes de información recibidas (apartado
 * XIV, `dc_solicitudes`). Ver la bandeja usa el permiso del apartado dueño
 * (`.apartado.xiv.view`) — misma doble puerta que ya usan
 * `RegistroEstructuradoController` (`.registro.manage`) y `ConcesionController`
 * (`.concesion.manage`). Se declara NUEVO en vez de reusar `.registro.manage`
 * porque ese permiso es de los 5 recursos genéricos de
 * `RegistroEstructuradoController`; `dc_solicitudes` tiene su propio
 * controlador con reglas y campos distintos.
 * Aditiva/idempotente: crea el permiso y lo asigna a los roles base
 * (super-administrator + DESARROLLADOR) — decisión de Irving en #758 (q2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'documentacion-corporativa.solicitud.manage', 'guard_name' => 'web']);

        app(PermissionSyncService::class)->syncPermissionToBaseRoles('documentacion-corporativa.solicitud.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso. down() vacío a propósito.
    }
};
