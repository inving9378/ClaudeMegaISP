<?php

namespace App\Modules\Addons\Marketing\Support;

use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Equipo de Marketing de Meganet (Fase 1, cambio de alcance de Henry, puntos B/C y §2.6.2 de
 * docs/marketing/fase-1-paquetes.md). Lo comparten:
 *  - la migración 2026_11_13_100200 (restringe el equipo a la lista MARKETING_EQUIPO_LOGINS al aplicar la versión),
 *  - el comando `marketing:equipo-fijar`,
 *  - el candado de `ResolverEmpresaActiva` (sin la MARCA nadie entra).
 *
 * La MARCA es la fila `marketing_settings` (company_id 1, key `equipo_fijado_at`, group `acceso`). Solo la escriben
 * la migración y el comando; mientras no exista, el módulo responde 403 `sin_acceso` a todos los que no tengan uno
 * de los roles de `marketing.roles_con_acceso` (desde el 2026-11-14 el acceso es por rol; ver rolesConAcceso()).
 * Nunca ids: las personas se dan por `login_user`.
 */
class EquipoMarketing
{
    public const CLAVE_MARCA = 'equipo_fijado_at';

    public const GRUPO_MARCA = 'acceso';

    public const VARIABLE_LISTA = 'MARKETING_EQUIPO_LOGINS';

    /**
     * Permisos que declara el módulo pero que NO son solo de Marketing: `manage-integrations` es el permiso del Hub
     * de Integraciones de TODO el sistema (todas las llaves de API y la IA por módulo); nunca se reparte aquí.
     */
    public const FUERA_DE_MARKETING = ['manage-integrations'];

    // ── Marca ────────────────────────────────────────────────────────────────

    public static function hayMarca(): bool
    {
        return DB::table('marketing_settings')
            ->where('company_id', Empresa::PRINCIPAL_ID)
            ->where('key', self::CLAVE_MARCA)
            ->whereNull('deleted_at')
            ->exists();
    }

    /** Escribe (o actualiza) la marca con la fecha de ahora. Revive una marca con borrado lógico. */
    public static function marcar(): void
    {
        $donde = ['company_id' => Empresa::PRINCIPAL_ID, 'key' => self::CLAVE_MARCA];
        $valores = [
            'value'      => now()->toIso8601String(),
            'encrypted'  => 0,
            'group'      => self::GRUPO_MARCA,
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if (DB::table('marketing_settings')->where($donde)->exists()) {
            DB::table('marketing_settings')->where($donde)->update($valores);
        } else {
            DB::table('marketing_settings')->insert($donde + $valores + ['created_at' => now()]);
        }
    }

    /** Borra la marca (con eso nadie entra). */
    public static function quitarMarca(): void
    {
        DB::table('marketing_settings')
            ->where('company_id', Empresa::PRINCIPAL_ID)
            ->where('key', self::CLAVE_MARCA)
            ->delete();
    }

    // ── Permisos ─────────────────────────────────────────────────────────────

    /** @return array<int, string> `module.json → permissions[]` del módulo, sin los de FUERA_DE_MARKETING */
    public static function permisosDelModulo(): array
    {
        $manifiesto = json_decode((string) file_get_contents(dirname(__DIR__) . '/module.json'), true);

        return array_values(array_filter(array_map(
            fn ($p) => is_array($p) ? ($p['name'] ?? null) : null,
            (array) ($manifiesto['permissions'] ?? [])
        ), fn ($n) => is_string($n) && $n !== '' && ! in_array($n, self::FUERA_DE_MARKETING, true)));
    }

    /**
     * Todas las facultades de Marketing que EXISTEN en `permissions`: `marketing.*` + `module.json → permissions[]`,
     * sin `manage-integrations`. Un permiso que no exista no se crea.
     *
     * @return Collection<int, Permission>
     */
    public static function permisos(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('name', 'like', 'marketing.%')->orWhereIn('name', self::permisosDelModulo());
            })
            ->whereNotIn('name', self::FUERA_DE_MARKETING)
            ->orderBy('name')
            ->get();
    }

    /** Da (aditivo, givePermissionTo) los permisos que le falten. @return array<int, string> los que se dieron */
    public static function darPermisos(User $usuario, ?Collection $permisos = null): array
    {
        $faltantes = ($permisos ?? self::permisos())
            ->reject(fn (Permission $p) => $usuario->hasPermissionTo($p))
            ->values();

        if ($faltantes->isNotEmpty()) {
            $usuario->givePermissionTo($faltantes->all());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $faltantes->pluck('name')->all();
    }

    // ── Lista del .env ───────────────────────────────────────────────────────

    /**
     * Logins de `MARKETING_EQUIPO_LOGINS` (separados por coma; se recortan; vacíos y repetidos fuera).
     * Ausente o vacía → [] (nadie entra).
     *
     * Se lee con Env::get (lo mismo que env()) y, si la configuración estuviera cacheada (con ella Laravel no carga
     * el .env y Env::get daría null), directo del archivo .env. Solo lo usa la migración 2026_11_13_100200, que corre
     * una vez por consola; NO es una lectura en tiempo de petición.
     *
     * @return array<int, string>
     */
    public static function loginsDeLaLista(): array
    {
        $crudo = Env::get(self::VARIABLE_LISTA);

        if ($crudo === null && app()->configurationIsCached() && is_file(base_path('.env'))) {
            $crudo = Dotenv::parse((string) file_get_contents(base_path('.env')))[self::VARIABLE_LISTA] ?? null;
        }

        return self::partirLista(is_string($crudo) ? $crudo : '');
    }

    /** @return array<int, string> */
    public static function partirLista(string $lista): array
    {
        $logins = array_map('trim', explode(',', $lista));

        return array_values(array_unique(array_filter($logins, fn ($l) => $l !== '')));
    }

    // ── Restricción a la lista (la usa la migración) ─────────────────────────

    /**
     * Deja como equipo de la empresa principal SOLO a los logins dados: desactiva (no borra: reversible) toda otra
     * fila de la empresa 1, deja a cada login existente como `admin` activa con todos los permisos de Marketing,
     * ignora los logins que no existen, escribe la marca y deja constancia en activity_log (solo conteos).
     *
     * @param  array<int, string>  $logins
     * @return array{en_lista: int, encontrados: int, ignorados: array<int, string>, filas_desactivadas: int}
     */
    public static function restringirALista(array $logins): array
    {
        $logins = self::partirLista(implode(',', $logins));

        return DB::transaction(function () use ($logins) {
            $usuarios = $logins === []
                ? collect()
                : User::query()->whereIn('login_user', $logins)->get()->keyBy('login_user');
            $ids = $usuarios->map(fn (User $u) => (int) $u->getKey())->values()->all();
            $ignorados = array_values(array_diff($logins, $usuarios->keys()->all()));

            $desactivadas = EmpresaUsuario::query()
                ->where('company_id', Empresa::PRINCIPAL_ID)
                ->where('activo', true)
                ->when($ids !== [], fn ($q) => $q->whereNotIn('user_id', $ids))
                ->update(['activo' => false, 'updated_at' => now()]);

            $permisos = self::permisos();
            foreach ($usuarios as $usuario) {
                EmpresaUsuario::query()->updateOrCreate(
                    ['company_id' => Empresa::PRINCIPAL_ID, 'user_id' => (int) $usuario->getKey()],
                    ['rol' => 'admin', 'activo' => true]
                );
                self::darPermisos($usuario, $permisos);
            }

            self::marcar();

            $resumen = [
                'en_lista'           => count($logins),
                'encontrados'        => $usuarios->count(),
                'ignorados'          => $ignorados,
                'filas_desactivadas' => (int) $desactivadas,
            ];

            activity('marketing')
                ->event('equipo_restringido')
                ->withProperties([
                    'empresa_id'         => Empresa::PRINCIPAL_ID,
                    'en_lista'           => $resumen['en_lista'],
                    'encontrados'        => $resumen['encontrados'],
                    'ignorados'          => count($ignorados),
                    'filas_desactivadas' => $resumen['filas_desactivadas'],
                ])
                ->log('Marketing: equipo de Meganet restringido a la lista MARKETING_EQUIPO_LOGINS (migración 2026_11_13_100200)');

            return $resumen;
        });
    }

    // ── Acceso por rol (decisión de Irving, 2026-11-14) ─────────────────────

    /** @return array<int, string> roles que entran a Marketing (config `marketing.roles_con_acceso`). */
    public static function rolesConAcceso(): array
    {
        return array_values(array_filter(
            (array) config('marketing.roles_con_acceso', []),
            fn ($r) => is_string($r) && $r !== ''
        ));
    }

    /** ¿Tiene alguno de los roles que entran a Marketing? (guard web) */
    public static function tieneRolConAcceso(?User $usuario): bool
    {
        $roles = self::rolesConAcceso();

        return $usuario !== null && $roles !== [] && $usuario->hasAnyRole($roles);
    }

    // ── Acceso (Fase 2, menú lateral §2.11) ─────────────────────────────────

    /**
     * ¿El usuario entra a Marketing? Es la MISMA regla que aplica `ResolverEmpresaActiva`: usuario activo y
     *  - uno de los roles de `marketing.roles_con_acceso` (la regla principal desde el 2026-11-14), o
     *  - (opcional, regla de la Fase 1) marca `equipo_fijado_at` presente y fila activa con rol `admin` en Meganet.
     * La usa el submenú lateral de Marketing para no mostrarse a nadie más. Se calcula una vez por petición.
     */
    public static function puedeEntrar(?User $usuario): bool
    {
        if (! $usuario || ($usuario->estado ?? 'activo') !== 'activo') {
            return false;
        }

        static $cache = [];
        $clave = (int) $usuario->getKey();
        if (! app()->runningUnitTests() && array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }

        $puede = self::tieneRolConAcceso($usuario)
            || (self::hayMarca()
                && \App\Modules\Addons\Marketing\Middleware\ResolverEmpresaActiva::asignacionesActivas($clave)
                    ->contains(fn (EmpresaUsuario $a) => (int) $a->company_id === Empresa::PRINCIPAL_ID && $a->rol === 'admin'));

        return $cache[$clave] = $puede;
    }
}
