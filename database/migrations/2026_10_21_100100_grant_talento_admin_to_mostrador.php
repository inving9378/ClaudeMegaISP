<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mostrador = administrador de Talento sin dar de alta ni eliminar perfiles de colaborador
 * (decisión 2026-10-21; reemplaza el "admin-lite" acotado del 2026-10-13). Recibe todos los
 * permisos `talento.*` que existen al correr esta migración, menos
 * `talento.colaboradores.create` y `talento.colaboradores.delete`. El resto del sistema
 * (roles, usuarios, clientes...) no cambia.
 *
 * Un permiso `talento.*` que se cree después no llega solo: hay que dárselo al rol.
 * Solo agrega (givePermissionTo); idempotente; rol por nombre (dev y prod no coinciden).
 */
return new class extends Migration
{
    private const EXCLUIDOS = [
        'talento.colaboradores.create',
        'talento.colaboradores.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where('name', 'Mostrador')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        $agregados = [];
        $permisos = Permission::where('guard_name', 'web')
            ->where('name', 'like', 'talento.%')
            ->whereNotIn('name', self::EXCLUIDOS)
            ->get();
        foreach ($permisos as $permission) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
                $agregados[] = $permission->name;
            }
        }
        if ($agregados) {
            Log::info('Talento: permisos de administrador dados al rol Mostrador', ['permisos' => $agregados]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * No se revierte: quitar permisos a un rol en uso al hacer rollback dejaría al
     * mostrador sin las pantallas que ya usa.
     */
    public function down(): void
    {
    }
};
