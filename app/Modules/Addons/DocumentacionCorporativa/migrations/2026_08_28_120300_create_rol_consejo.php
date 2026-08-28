<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Rol `consejo` — el miembro del consejo que consulta el expediente.
 *
 * Ve el módulo y 13 de los 14 apartados: NO el XI (cuentas bancarias, firmas y
 * productos financieros). No descarga documentos, no ve el inventario de accesos
 * y no lee la bitácora — quien es auditado no audita el registro de auditoría.
 *
 * ADITIVA e IDEMPOTENTE: `firstOrCreate` del rol + `givePermissionTo` (que ya es
 * idempotente en Spatie). NUNCA `syncPermissions`/`syncRoles`: los permisos se
 * SUMAN, y un sync aquí borraría lo que otro módulo le haya dado a este rol.
 *
 * Portable dev↔prod: resuelve todo por NOMBRE, jamás por id.
 */
return new class extends Migration
{
    private const ROL = 'consejo';

    /** Apartado XI = bancos. Fuera del alcance del consejo por diseño. */
    private const APARTADOS_EXCLUIDOS = ['xi'];

    private const APARTADOS = [
        'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii',
        'viii', 'ix', 'x', 'xi', 'xii', 'xiii', 'xiv',
    ];

    public function up(): void
    {
        $rol = Role::firstOrCreate(['name' => self::ROL, 'guard_name' => 'web']);

        foreach ($this->permisos() as $nombre) {
            $permiso = Permission::where('name', $nombre)->where('guard_name', 'web')->first();

            // El permiso lo crea el install del módulo (module.json). Si todavía no
            // existe, se omite en silencio en vez de crear un permiso huérfano con
            // nombre inventado: correr de nuevo esta migración lo repara.
            if ($permiso && ! $rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();

        if (! $rol) {
            return;
        }

        foreach ($this->permisos() as $nombre) {
            $permiso = Permission::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($permiso && $rol->hasPermissionTo($permiso)) {
                $rol->revokePermissionTo($permiso);
            }
        }

        // El rol NO se borra: pudo recibir permisos de otros módulos y borrarlo
        // desasignaría a sus usuarios de todo, no sólo de este módulo.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return string[] */
    private function permisos(): array
    {
        $permisos = ['documentacion-corporativa.view'];

        foreach (self::APARTADOS as $clave) {
            if (in_array($clave, self::APARTADOS_EXCLUIDOS, true)) {
                continue;
            }
            $permisos[] = "documentacion-corporativa.apartado.{$clave}.view";
        }

        return $permisos;
    }
};
