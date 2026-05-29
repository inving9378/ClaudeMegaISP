<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view-publishing-dashboard',
            'manage-publishing-channels',
            'connect-meta-account',
            'publish-content',
            'schedule-publications',
            'manage-publication-queue',
            'view-publication-metrics',
        ];

        $existing = DB::table('permissions')->whereIn('name', $permissions)->pluck('name')->toArray();

        foreach ($permissions as $permission) {
            if (!in_array($permission, $existing)) {
                DB::table('permissions')->insert([
                    'name'       => $permission,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Asignar permisos al rol DESARROLLADOR
        $devRole = DB::table('roles')->where('name', 'DESARROLLADOR')->first();
        if ($devRole) {
            $permIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $devRole->id)
                ->whereIn('permission_id', $permIds)
                ->pluck('permission_id')
                ->toArray();

            foreach ($permIds as $permId) {
                if (!in_array($permId, $existing)) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permId,
                        'role_id'       => $devRole->id,
                    ]);
                }
            }
        }

        // Crear rol PUBLICADOR si no existe
        $pubRole = DB::table('roles')->where('name', 'PUBLICADOR')->first();
        if (!$pubRole) {
            $pubRoleId = DB::table('roles')->insertGetId([
                'name'       => 'PUBLICADOR',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pubPermissions = [
                'view-publishing-dashboard',
                'publish-content',
                'schedule-publications',
                'manage-publication-queue',
                'view-publication-metrics',
            ];

            $pubPermIds = DB::table('permissions')->whereIn('name', $pubPermissions)->pluck('id');
            foreach ($pubPermIds as $permId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permId,
                    'role_id'       => $pubRoleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissions = [
            'view-publishing-dashboard', 'manage-publishing-channels', 'connect-meta-account',
            'publish-content', 'schedule-publications', 'manage-publication-queue', 'view-publication-metrics',
        ];

        $permIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
        DB::table('permissions')->whereIn('name', $permissions)->delete();
    }
};
