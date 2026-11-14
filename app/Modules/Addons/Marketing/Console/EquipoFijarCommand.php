<?php

namespace App\Modules\Addons\Marketing\Console;

use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fija el equipo de Marketing de Meganet (CAMBIO DE ALCANCE de Henry, 2026-11-13, punto C de
 * docs/marketing/fase-1-paquetes.md).
 *
 *   php artisan marketing:equipo-fijar <login_user> [<login_user> ...]            → solo muestra qué quedaría
 *   php artisan marketing:equipo-fijar <login_user> [<login_user> ...] --aplicar  → lo aplica
 *
 * Con --aplicar, en una transacción:
 *  - deja SOLO a esas personas como `admin` activas de la empresa principal (Meganet, id 1) y BORRA las
 *    demás filas de esa empresa en `marketing_empresa_usuario` (las de otras empresas no se tocan);
 *  - les da con givePermissionTo (ADITIVO: nunca syncRoles/syncPermissions, no quita nada) TODAS las
 *    facultades de Marketing que existan en `permissions`: los `marketing.*` MÁS todos los declarados en
 *    `module.json → permissions[]` del módulo (video, campañas IA, variantes, nichos, voces, publicación…).
 *    Nada fuera de Marketing; un permiso que no exista no se crea;
 *  - escribe/actualiza la marca `marketing_settings.equipo_fijado_at` (§2.6.2: sin ella nadie entra);
 *  - deja constancia en activity_log (log_name `marketing`, evento `equipo_fijado`).
 * Las personas se dan SIEMPRE por `login_user`, nunca por id (los ids de dev y prod no coinciden). Si
 * alguna no existe, no se cambia nada. En prod lo corre Irving con bitácora previa.
 */
class EquipoFijarCommand extends Command
{
    protected $signature = 'marketing:equipo-fijar
                            {logins* : login_user de cada persona que queda como admin de Marketing}
                            {--aplicar : Aplica el cambio (sin esta opción solo muestra qué quedaría)}';

    protected $description = 'Marketing: deja solo a las personas indicadas (por login_user) como admin de Meganet y les da todos los permisos de Marketing';

    public function handle(): int
    {
        $logins = array_values(array_unique(array_map('trim', (array) $this->argument('logins'))));
        $logins = array_values(array_filter($logins, fn ($l) => $l !== ''));

        if ($logins === []) {
            $this->error('Indica al menos un login_user.');

            return self::FAILURE;
        }

        $empresa = Empresa::query()->find(Empresa::PRINCIPAL_ID);
        if (! $empresa) {
            $this->error('No existe la empresa principal de Marketing (Meganet). No se cambió nada.');

            return self::FAILURE;
        }

        $usuarios = User::query()->whereIn('login_user', $logins)->get()->keyBy('login_user');
        $faltan = array_values(array_diff($logins, $usuarios->keys()->all()));
        if ($faltan !== []) {
            $this->error('No existe ningún usuario con este login_user: ' . implode(', ', $faltan) . '. No se cambió nada.');

            return self::FAILURE;
        }

        $idsEquipo = $usuarios->map(fn (User $u) => (int) $u->getKey())->values()->all();

        $actuales = EmpresaUsuario::query()
            ->with('usuario:id,login_user')
            ->where('company_id', Empresa::PRINCIPAL_ID)
            ->get()
            ->keyBy('user_id');

        $permisos = EquipoMarketing::permisos();

        // ── Plan ──────────────────────────────────────────────────────────────
        $filas = [];
        foreach ($logins as $login) {
            $u = $usuarios[$login];
            $actual = $actuales->get((int) $u->getKey());
            $faltantes = $permisos->reject(fn (Permission $p) => $u->hasPermissionTo($p))->pluck('name')->all();

            $filas[] = [
                $login,
                $actual ? ($actual->rol . ($actual->activo ? '' : ' (inactiva)')) : '—',
                'admin',
                $actual ? ($actual->rol === 'admin' && $actual->activo ? 'se queda' : 'pasa a admin') : 'se agrega',
                count($faltantes),
            ];

            if (($u->estado ?? 'activo') !== 'activo') {
                $this->warn("Aviso: {$login} está inactivo en el sistema; no podrá entrar a Marketing mientras siga así.");
            }
        }

        $quitar = $actuales->reject(fn (EmpresaUsuario $a) => in_array((int) $a->user_id, $idsEquipo, true));
        foreach ($quitar as $a) {
            $filas[] = [$a->usuario?->login_user ?? '(usuario sin login)', $a->rol . ($a->activo ? '' : ' (inactiva)'), '—', 'se quita', 0];
        }

        $this->info('Empresa: ' . $empresa->nombre . ' (principal)');
        $this->table(['login_user', 'hoy', 'queda', 'cambio', 'permisos de Marketing por dar'], $filas);
        $this->line('Permisos de Marketing que se dan (aditivo, no se quita ninguno): ' . ($permisos->isEmpty() ? '(ninguno existe)' : $permisos->pluck('name')->implode(', ')));

        if (! $this->option('aplicar')) {
            $this->comment('Vista previa: no se cambió nada. Para aplicarlo agrega --aplicar.');

            return self::SUCCESS;
        }

        // ── Aplicar ───────────────────────────────────────────────────────────
        $otorgados = [];
        $quitados = $quitar->map(fn (EmpresaUsuario $a) => [
            'login_user' => $a->usuario?->login_user,
            'rol'        => $a->rol,
            'activo'     => (bool) $a->activo,
        ])->values()->all();

        DB::transaction(function () use ($logins, $usuarios, $idsEquipo, $permisos, $quitados, &$otorgados) {
            EmpresaUsuario::query()
                ->where('company_id', Empresa::PRINCIPAL_ID)
                ->whereNotIn('user_id', $idsEquipo)
                ->delete();

            foreach ($logins as $login) {
                $u = $usuarios[$login];

                EmpresaUsuario::query()->updateOrCreate(
                    ['company_id' => Empresa::PRINCIPAL_ID, 'user_id' => (int) $u->getKey()],
                    ['rol' => 'admin', 'activo' => true]
                );

                $otorgados[$login] = EquipoMarketing::darPermisos($u, $permisos);
            }

            // §2.6.2: sin la marca el módulo niega a todos; fijar el equipo la escribe/actualiza.
            EquipoMarketing::marcar();

            activity('marketing')
                ->event('equipo_fijado')
                ->withProperties([
                    'empresa_id'         => Empresa::PRINCIPAL_ID,
                    'admins'             => $logins,
                    'quitados'           => $quitados,
                    'permisos_otorgados' => $otorgados,
                ])
                ->log('Marketing: equipo de Meganet fijado por consola (marketing:equipo-fijar)');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Listo: ' . count($logins) . ' admin(s) en Meganet; ' . count($quitados) . ' fila(s) quitada(s). Quedó en activity_log.');

        return self::SUCCESS;
    }

    /** @return array<int, string> ver EquipoMarketing::permisosDelModulo() */
    public static function permisosDelModulo(): array
    {
        return EquipoMarketing::permisosDelModulo();
    }
}
