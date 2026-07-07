<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * FASE 3a — PASO 1 — Revoca del rol 'client' el ÚLTIMO permiso que todavía
 * ABRE una ruta de admin: 'scheduling_view_calendar'.
 *
 * Prerrequisito del flip (PASO 3): antes de voltear CheckRoutePermission a
 * honrar los permisos de ROL (getAllPermissions = directos ∪ rol), hay que
 * asegurar que el rol 'client' no abra ninguna ruta interna — de lo contrario
 * los 1139 usuarios espejo con rol client ganarían acceso a esa ruta por rol.
 *
 * En Fase 2 este permiso se CONSERVÓ a propósito (se creía inerte para el
 * portal). Auditoría de Fase 3a lo reclasificó: SÍ mapea a ruta en
 * config/route_permission.php → se revoca aquí.
 *
 * NO se tocan los otros 7 permisos del rol client: son inertes (no mapean a
 * ninguna ruta del config) → quedan como están.
 *
 * Idempotente: solo revoca si el rol lo tiene HOY (hasPermissionTo antes de
 * revokePermissionTo); re-ejecutable y portable (no falla si el rol o el
 * permiso no existen en el entorno).
 * down() a propósito NO re-otorga: la limpieza queda aplicada.
 */
return new class extends Migration
{
    private const REVOKE = 'scheduling_view_calendar';

    public function up(): void
    {
        $role = Role::where('name', 'client')->where('guard_name', 'web')->first();
        if (! $role) {
            return; // sin rol 'client' → nada que revocar
        }

        // Resolver contra lo que el rol tiene HOY → revocación 100% idempotente,
        // sin excepción si el permiso no existe como fila en este entorno
        // (hasPermissionTo(string) lanzaría PermissionDoesNotExist; la colección no).
        if ($role->permissions->pluck('name')->contains(self::REVOKE)) {
            $role->revokePermissionTo(self::REVOKE);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito: la limpieza de Fase 3a se queda aplicada.
    }
};
