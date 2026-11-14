<?php

use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Marketing — acceso por ROL (decisión de Irving, 2026-11-14): super-administrator, Administrador, DESARROLLADOR y
 * Mostrador (config `marketing.roles_con_acceso`) entran a Marketing con todo, igual que super-administrador.
 *
 * El middleware ya los deja entrar por su rol; esta migración les da a esos ROLES todos los permisos de Marketing que
 * existan (EquipoMarketing::permisos(): `marketing.*` + los de module.json, nunca `manage-integrations`), para que cada
 * botón y cada API los dejen trabajar. ADITIVO (`givePermissionTo`), nunca syncRoles/syncPermissions. No crea permisos
 * ni roles: un rol que no exista se salta. Idempotente.
 *
 * Reversible: anota en activity_log (log_name `marketing`, event `permisos_por_rol`) exactamente qué par rol→permiso
 * dio; `down()` quita solo esos pares, nunca un permiso que el rol ya tuviera antes.
 */
return new class extends Migration
{
    private const EVENTO = 'permisos_por_rol';

    public function up(): void
    {
        $permisos = EquipoMarketing::permisos();
        if ($permisos->isEmpty()) {
            return;
        }

        $roles = Role::query()->where('guard_name', 'web')->whereIn('name', EquipoMarketing::rolesConAcceso())->get();
        $otorgados = [];

        foreach ($roles as $rol) {
            $faltantes = $permisos->reject(fn ($p) => $rol->hasPermissionTo($p))->values();
            if ($faltantes->isEmpty()) {
                continue;
            }

            $rol->givePermissionTo($faltantes->all());
            $otorgados[$rol->name] = $faltantes->pluck('name')->all();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($otorgados !== [] && Schema::hasTable('activity_log')) {
            activity('marketing')
                ->event(self::EVENTO)
                ->withProperties(['otorgados' => $otorgados])
                ->log('Marketing: permisos de Marketing a los roles con acceso (migración 2026_11_14_120000)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        $registro = DB::table('activity_log')
            ->where('log_name', 'marketing')
            ->where('event', self::EVENTO)
            ->orderByDesc('id')
            ->first();
        if (! $registro) {
            return;
        }

        $otorgados = (array) (json_decode((string) $registro->properties, true)['otorgados'] ?? []);
        foreach ($otorgados as $nombreRol => $nombres) {
            $rol = Role::query()->where('guard_name', 'web')->where('name', $nombreRol)->first();
            foreach ((array) $nombres as $nombre) {
                if ($rol && $rol->hasPermissionTo($nombre)) {
                    $rol->revokePermissionTo($nombre);
                }
            }
        }

        DB::table('activity_log')->where('id', $registro->id)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
