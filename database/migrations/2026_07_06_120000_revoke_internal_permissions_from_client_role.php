<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * FASE 2 — Revoca del rol 'client' los permisos de STAFF INTERNO indebidos
 * (CRM + tareas + panel de vendedor). Afecta a los usuarios que portan el rol.
 *
 * Se CONSERVAN a propósito (NO se tocan): calendar_filter_*, package_view_package,
 * scheduling_view_calendar, scheduling_view_scheduling — lo que un cliente
 * legítimamente necesita para su portal / MegaFamilia.
 *
 * Idempotente: solo revoca lo que el rol tiene hoy; re-ejecutable sin error y
 * portable (no falla si el rol o algún permiso no existe en el entorno).
 * down() a propósito NO re-otorga: la limpieza queda aplicada.
 *
 * Prerrequisito (ya aplicado, commit b9effb5f): 'client' está en
 * config/permission_sync.php > view_excluded_roles, así que un reparto
 * automático de .view no reañade permisos a este rol.
 */
return new class extends Migration
{
    /** Permisos REVOCAR confirmados en Fase 2 PASO 0 (22 = 11 CRM + 8 seller + 3 task). */
    private const REVOKE = [
        // CRM interno (11)
        'crm_add_crm',
        'crm_document',
        'crm_document_add_crm',
        'crm_document_edit_crm',
        'crm_document_view_crm',
        'crm_document_view_tab_crm',
        'crm_edit_crm',
        'crm_information',
        'crm_information_geolocation_crm',
        'crm_information_view_tab_crm',
        'crm_view_crm',
        // Panel de vendedor (8)
        'seller_follow_payment_client',
        'seller_view_all_payments_for_seller',
        'seller_view_all_transactions_for_seller',
        'seller_view_billing',
        'seller_view_panel',
        'seller_view_prospects',
        'seller_view_sales',
        'seller_view_statics',
        // Tareas internas (3)
        'task_add_task',
        'task_edit_task',
        'task_view_task',
    ];

    public function up(): void
    {
        $role = Role::where('name', 'client')->where('guard_name', 'web')->first();
        if (! $role) {
            return; // sin rol 'client' → nada que revocar
        }

        // Resolver por nombre contra lo que el rol tiene HOY (evita excepción si el
        // permiso no existe y hace la revocación 100% idempotente).
        $current = $role->permissions->pluck('name')->all();

        foreach (self::REVOKE as $permName) {
            if (in_array($permName, $current, true)) {
                $role->revokePermissionTo($permName);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op a propósito: la limpieza de Fase 2 se queda aplicada.
    }
};
