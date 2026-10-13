<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 5 del dashboard por rol (2026-10-13) — pedido explícito: Mostrador
 * necesita poder crear/gestionar trabajos de campo (órdenes de trabajo para
 * técnicos), "estilo admin pero sin llegar a tener todos los permisos".
 *
 * "Crear tareas" y "realizar pagos" YA funcionan (Mostrador ya tiene
 * task_add_task/task_edit_task y payments_capture_manage desde antes de
 * esta sesión) — lo único que faltaba era "trabajos de campo".
 *
 * Se otorgan SOLO estos 3 permisos de Talento, deliberadamente SIN tocar
 * compensación/liquidaciones/penalizaciones/préstamos/finiquito/académia
 * (esos siguen 100% fuera de alcance de Mostrador, es la parte "sin llegar
 * a tener todos los permisos"):
 *   - talento.view               — gate amplio de middleware (el controller
 *     de cada pantalla sigue exigiendo uno-mismo/supervisor directo/permiso
 *     de staff específico — este permiso NO destapa compensación/
 *     penalizaciones/préstamos/etc. por sí solo, ver config/route_permission.php)
 *   - talento.work_orders.view   — abrir /talento/ordenes
 *   - talento.work_orders.manage — crear/asignar órdenes de trabajo;
 *     TalentoWorkOrderController::esStaffOrdenes() lo trata como "staff"
 *     (ve/gestiona las órdenes de CUALQUIER técnico, no solo un equipo
 *     supervisado, que es justo lo que necesita un "dispatcher")
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Mostrador')->first();
        if ($role) {
            foreach (['talento.view', 'talento.work_orders.view', 'talento.work_orders.manage'] as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Mostrador')->first();
        if ($role) {
            $role->revokePermissionTo('talento.view');
            $role->revokePermissionTo('talento.work_orders.view');
            $role->revokePermissionTo('talento.work_orders.manage');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
