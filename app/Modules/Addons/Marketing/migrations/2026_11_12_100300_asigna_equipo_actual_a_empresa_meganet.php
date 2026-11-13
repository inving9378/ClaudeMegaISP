<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 0 — asignación inicial del equipo actual a la empresa 1 (Meganet).
 *
 * ESCRITURA DE DATOS (decisión D1 de Henry, 12-nov). Corre en dev al mezclar y en prod al desplegar.
 * Selector POR NOMBRE DE ROL, nunca por id (los ids de dev y prod no coinciden):
 *   - admin     ← usuarios con super-administrator o DESARROLLADOR
 *   - marketing ← usuarios con Super Administrador o Administrador que no quedaron como admin
 * Solo usuarios con estado = 'activo', que no son cuenta de sistema (is_system) y que NO tienen el
 * rol client (cuentas espejo). Idempotente: insertOrIgnore sobre unique(company_id, user_id).
 * created_by = null marca a las filas de esta migración; down() borra solo esas.
 */
return new class extends Migration
{
    private const EMPRESA_ID = 1;

    private const ROLES_ADMIN = ['super-administrator', 'DESARROLLADOR'];

    private const ROLES_MARKETING = ['Super Administrador', 'Administrador'];

    public function up(): void
    {
        $admins = $this->usuariosConRol(self::ROLES_ADMIN);
        $marketing = array_values(array_diff($this->usuariosConRol(self::ROLES_MARKETING), $admins));

        $asignados = [
            'admin'     => $this->asignar($admins, 'admin'),
            'marketing' => $this->asignar($marketing, 'marketing'),
        ];

        $mensaje = sprintf(
            'Marketing Fase 0: asignación inicial a Meganet — admin: %d nuevas de %d candidatos; marketing: %d nuevas de %d candidatos.',
            $asignados['admin'], count($admins), $asignados['marketing'], count($marketing)
        );

        Log::info($mensaje);

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            echo $mensaje . PHP_EOL;
        }
    }

    public function down(): void
    {
        DB::table('marketing_empresa_usuario')
            ->where('company_id', self::EMPRESA_ID)
            ->whereNull('created_by')
            ->delete();
    }

    /**
     * Ids de usuarios activos, no de sistema y sin rol client que tienen alguno de los roles dados.
     *
     * @param  array<int, string>  $roles
     * @return array<int, int>
     */
    private function usuariosConRol(array $roles): array
    {
        $userClass = 'App\\Models\\User';

        $query = DB::table('users')
            ->where('users.estado', 'activo')
            ->whereExists(function ($q) use ($roles, $userClass) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', $userClass)
                    ->whereIn('roles.name', $roles);
            })
            ->whereNotExists(function ($q) use ($userClass) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', $userClass)
                    ->where('roles.name', 'client');
            });

        if (Schema::hasColumn('users', 'is_system')) {
            $query->where(function ($q) {
                $q->whereNull('users.is_system')->orWhere('users.is_system', 0);
            });
        }

        return $query->orderBy('users.id')->pluck('users.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function asignar(array $userIds, string $rol): int
    {
        if ($userIds === []) {
            return 0;
        }

        $ahora = now();
        $filas = array_map(fn ($id) => [
            'company_id' => self::EMPRESA_ID,
            'user_id'    => $id,
            'rol'        => $rol,
            'activo'     => 1,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $userIds);

        $total = 0;
        foreach (array_chunk($filas, 500) as $lote) {
            $total += DB::table('marketing_empresa_usuario')->insertOrIgnore($lote);
        }

        return $total;
    }
};
