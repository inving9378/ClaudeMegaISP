<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Marketing IA 360 · Fase 1 (F1-2) — permisos que los controladores y el menú de Marketing ya exigen
 * pero que nunca se crearon. Sin ellos Spatie responde 403 a todos (también a super-administrador) en
 * las APIs de Leads, Formularios y Brand Kit, y esas entradas del menú no se ven para nadie.
 *
 * Patrón de la migración de la Fase 0 (firstOrCreate + reparto ADITIVO con givePermissionTo), con una
 * diferencia a propósito: el reparto se hace aquí mismo y SOLO a super-administrator y DESARROLLADOR, sin
 * PermissionSyncService::syncPermissionToBaseRoles, porque ese servicio reparte los `*.view` a TODOS los
 * roles cuando PERMISSION_SYNC_AUTO_GRANT_VIEW=true, y Henry autorizó solo esos dos roles. A cualquier otra
 * persona se los da Irving/Henry desde el editor de roles. Nunca syncRoles/syncPermissions.
 * Idempotente: correr up() dos veces no duplica nada.
 *
 * No se crea `marketing.templates.manage`: su entrada del menú lleva a Plantillas legado, retiradas (F1-3).
 */
return new class extends Migration
{
    private const PERMISOS = [
        'marketing.leads.view',
        'marketing.leads.create',
        'marketing.leads.update',
        'marketing.leads.delete',
        'marketing.leads.assign',
        'marketing.leads.score',
        'marketing.forms.view',
        'marketing.forms.create',
        'marketing.forms.update',
        'marketing.forms.delete',
        'marketing.brand_kit.configure',
        'marketing.conversations.view',
        'marketing.campaigns.view',
    ];

    private const ROLES = ['super-administrator', 'DESARROLLADOR'];

    public function up(): void
    {
        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', self::ROLES)->get();

        foreach (self::PERMISOS as $nombre) {
            $permiso = Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);

            foreach ($roles as $rol) {
                if (! $rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Catálogo de permisos: no se revierte (mismo criterio que la migración de permisos de la Fase 0).
    }
};
