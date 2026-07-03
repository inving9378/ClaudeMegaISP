<?php

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 0 · SP1a — Permiso base del Portal de Colaborador.
 *
 * Aditivo/idempotente: crea 'portal.colaborador' (guard web), lo asigna a los roles base
 * (super-administrator + DESARROLLADOR, para no perder acceso cuando el gate del portal cambie
 * a este permiso más adelante) y hace el BACKFILL inicial a los USERS de los colaboradores
 * ACTIVOS actuales (permiso DIRECTO por usuario, opción B).
 *
 * La asignación automática futura (crear/activar/baja) la maneja TalentoColaboradorObserver (SP1b).
 * NO cambia el gate del portal (sigue can:talento.portal_tecnico) — Bloques 1-2 intactos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'portal.colaborador', 'guard_name' => 'web']);

        // Regla del proyecto: super-administrator + DESARROLLADOR reciben el permiso.
        app(PermissionSyncService::class)->syncPermissionToBaseRoles('portal.colaborador');

        // Backfill: colaboradores ACTIVOS actuales -> permiso directo a su user (idempotente).
        TalentoColaborador::where('status', 'active')->get()->each(function (TalentoColaborador $c) {
            $user = User::find($c->user_id);
            if ($user && ! $user->hasDirectPermission('portal.colaborador')) {
                $user->givePermissionTo('portal.colaborador');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Forward-only: no se revierte el permiso (estado real de acceso). down() vacío a propósito.
    }
};
