<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Item #952 (UNI-T B3c): permisos separados de «ver tareas». Aditivo e idempotente; solo asigna a los
// roles que hoy ejercen la acción (task_edit/delete/add_task → gestionar órdenes; work_orders.validate
// → validar orden), con givePermissionTo puntual, nunca sync.
return new class extends Migration {
    private function otorga(string $nuevo, array $origenes): void
    {
        $perm = Permission::firstOrCreate(['name' => $nuevo, 'guard_name' => 'web']);
        $ids = Permission::whereIn('name', $origenes)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        $roles = DB::table('role_has_permissions')->whereIn('permission_id', $ids)->pluck('role_id')->unique();
        foreach (Role::whereIn('id', $roles)->get() as $rol) {
            if (!$rol->hasPermissionTo($perm)) {
                $rol->givePermissionTo($perm);
            }
        }
    }

    public function up(): void
    {
        $this->otorga('tareas.gestionar_ordenes', ['task_edit_task', 'task_delete_task', 'task_add_task']);
        $this->otorga('talento.validar_orden', ['talento.work_orders.validate']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
    }
};
