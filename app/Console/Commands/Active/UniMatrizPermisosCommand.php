<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UNI-T B2 (#869) — Matriz de permisos efectivos (rol + directo) con su uso real. SOLO LECTURA.
 *
 * Exporta a storage/app/uni/permisos/ (con ids, fuera del repo) y, con --doc, imprime los
 * conteos agregados (sin nombres de personas) que alimentan docs/unificacion/10-matriz-permisos.md.
 * No modifica ningún permiso ni rol.
 */
class UniMatrizPermisosCommand extends Command
{
    protected $signature = 'uni:matriz-permisos
                            {--dir=app/uni/permisos : carpeta bajo storage/ donde exportar}
                            {--doc : imprime además los conteos agregados en markdown}';

    protected $description = 'UNI-T B2: exporta la matriz cuenta × permiso (origen rol/directo), rutas por permiso y lectura→escritura. Solo lectura.';

    private const PERFILES = [
        'dirección' => ['super-administrator', 'Super Administrador', 'DESARROLLADOR'],
        'administrador' => ['Administrador', 'ADMINISTRADOR_COMPLETO'],
        'supervisión' => ['SUPERVISOR_MOSTRADOR'],
        'mostrador' => ['Mostrador'],
        'vendedor' => ['Vendedor'],
        'técnico' => ['TECNICO'],
        'almacén' => ['Almacen'],
        'cliente' => ['client'],
    ];

    private const ESCRITURA = '/(store|update|destroy|delete|add|create|crear|editar|edit|guardar|save|remove|eliminar|asignar|assign|import|sync|apply|aplicar|cobrar|pagar|validar|aprobar|upload|toggle|reset|cancel|cerrar|close)/i';

    private const LECTURA = ['view', 'ver', 'list', 'show', 'index', 'read', 'listar'];

    private const ESCRIBE_TOKENS = ['add', 'edit', 'delete', 'create', 'manage', 'update', 'destroy', 'assign', 'apply', 'approve', 'validate', 'capture', 'emitir', 'gestionar'];

    public function handle(): int
    {
        $dir = storage_path(trim($this->option('dir'), '/'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $perms = DB::table('permissions')->pluck('name', 'id');
        $roles = DB::table('roles')->pluck('name', 'id');

        // rol -> [permiso...]
        $porRol = [];
        foreach (DB::table('role_has_permissions')->get(['role_id', 'permission_id']) as $r) {
            if (isset($perms[$r->permission_id], $roles[$r->role_id])) {
                $porRol[$r->role_id][$r->permission_id] = $perms[$r->permission_id];
            }
        }

        // cuenta -> roles / directos
        $rolesDeUser = [];
        foreach (DB::table('model_has_roles')->where('model_type', 'like', '%User')->get(['role_id', 'model_id']) as $r) {
            $rolesDeUser[$r->model_id][] = $r->role_id;
        }
        $directos = [];
        foreach (DB::table('model_has_permissions')->where('model_type', 'like', '%User')->get(['permission_id', 'model_id']) as $r) {
            if (isset($perms[$r->permission_id])) {
                $directos[$r->model_id][$r->permission_id] = $perms[$r->permission_id];
            }
        }

        $clientId = $roles->search('client');
        $logins = DB::table('users')->pluck('login_user', 'id');

        // 1) matriz cuenta × permiso con origen + 5) clasificación de directos
        $fM = fopen("$dir/matriz_cuenta_permiso.csv", 'w');
        fputcsv($fM, ['user_id', 'login_user', 'permiso', 'origen']);
        $fD = fopen("$dir/directos_clasificacion.csv", 'w');
        fputcsv($fD, ['user_id', 'login_user', 'permiso', 'clasificacion', 'roles_que_lo_dan']);

        $cuentas = array_unique(array_merge(array_keys($rolesDeUser), array_keys($directos)));
        sort($cuentas);
        $nCuentasStaff = 0;
        $nDirectos = ['residuo_copia_rol_a_directo' => 0, 'a_decidir' => 0];
        $directosPorPerfil = [];
        foreach ($cuentas as $uid) {
            $rids = $rolesDeUser[$uid] ?? [];
            $soloCliente = $rids && array_diff($rids, [$clientId]) === [] && empty($directos[$uid] ?? []);
            if ($soloCliente || (! $rids && empty($directos[$uid] ?? []))) {
                continue;
            }
            $nCuentasStaff++;
            $origen = [];
            foreach ($rids as $rid) {
                foreach ($porRol[$rid] ?? [] as $pid => $pn) {
                    $origen[$pn]['roles'][] = $roles[$rid];
                }
            }
            foreach ($directos[$uid] ?? [] as $pn) {
                $origen[$pn]['directo'] = true;
            }
            ksort($origen);
            foreach ($origen as $pn => $o) {
                $partes = [];
                foreach (array_unique($o['roles'] ?? []) as $rn) {
                    $partes[] = "rol:$rn";
                }
                if (! empty($o['directo'])) {
                    $partes[] = 'directo';
                }
                fputcsv($fM, [$uid, $logins[$uid] ?? '', $pn, implode('+', $partes)]);
                if (! empty($o['directo'])) {
                    $dan = array_unique($o['roles'] ?? []);
                    $clase = $dan ? 'residuo_copia_rol_a_directo' : 'a_decidir';
                    $nDirectos[$clase]++;
                    fputcsv($fD, [$uid, $logins[$uid] ?? '', $pn, $clase, implode('|', $dan)]);
                    $perfil = $this->perfilDe($rids, $roles);
                    $directosPorPerfil[$perfil][$clase] = ($directosPorPerfil[$perfil][$clase] ?? 0) + 1;
                }
            }
        }
        fclose($fM);
        fclose($fD);

        // 2) rutas por permiso desde config/route_permission.php
        $mapa = config('route_permission', []);
        $fR = fopen("$dir/permiso_rutas.csv", 'w');
        fputcsv($fR, ['permiso', 'n_rutas', 'es_de_lectura_por_nombre', 'rutas_de_escritura_por_patron', 'rutas']);
        $lecturaAbreEscritura = [];
        foreach ($mapa as $perm => $rutas) {
            if (! is_array($rutas)) {
                continue;
            }
            $esc = array_values(array_filter($rutas, fn ($r) => is_string($r) && preg_match(self::ESCRITURA, $r)));
            $lec = $this->esLectura($perm);
            fputcsv($fR, [$perm, count($rutas), $lec ? 'si' : 'no', count($esc), implode(' ', $rutas)]);
            if ($lec && $esc) {
                $lecturaAbreEscritura[$perm] = $esc;
            }
        }
        fclose($fR);

        $fL = fopen("$dir/lectura_abre_escritura.csv", 'w');
        fputcsv($fL, ['permiso', 'rutas_de_escritura']);
        foreach ($lecturaAbreEscritura as $p => $esc) {
            fputcsv($fL, [$p, implode(' ', $esc)]);
        }
        fclose($fL);

        // 3) conteos por rol (verificables contra role_has_permissions)
        $fC = fopen("$dir/rol_conteos.csv", 'w');
        fputcsv($fC, ['rol', 'permisos', 'cuentas', 'permisos_lectura', 'permisos_escritura']);
        $resumenRol = [];
        foreach ($roles as $rid => $rn) {
            $ps = array_values($porRol[$rid] ?? []);
            $l = count(array_filter($ps, fn ($p) => $this->esLectura($p)));
            $e = count(array_filter($ps, fn ($p) => $this->esEscritura($p)));
            $cuentasRol = count(array_filter($rolesDeUser, fn ($rs) => in_array($rid, $rs)));
            $resumenRol[$rn] = [count($ps), $cuentasRol, $l, $e];
            fputcsv($fC, [$rn, count($ps), $cuentasRol, $l, $e]);
        }
        fclose($fC);

        // verificación: conteos del export vs tablas
        $totRolPerm = DB::table('role_has_permissions')->count();
        $totDirectos = DB::table('model_has_permissions')->where('model_type', 'like', '%User')->count();
        $this->info("Export en $dir");
        $this->line("role_has_permissions filas: $totRolPerm | model_has_permissions (User) filas: $totDirectos");
        $this->line("cuentas staff/con directos exportadas: $nCuentasStaff | directos residuo: {$nDirectos['residuo_copia_rol_a_directo']} | a decidir: {$nDirectos['a_decidir']}");
        $this->line('permisos de lectura que abren rutas de escritura (por patrón): '.count($lecturaAbreEscritura));

        if ($this->option('doc')) {
            $this->line("\n### Conteos por rol");
            $this->line('| Rol | Cuentas | Permisos | de lectura | de escritura |');
            $this->line('|---|---|---|---|---|');
            foreach ($resumenRol as $rn => [$p, $c, $l, $e]) {
                $this->line("| $rn | $c | $p | $l | $e |");
            }
            $this->line("\n### Directos por perfil");
            $this->line('| Perfil | Residuo rol→directo | A decidir |');
            $this->line('|---|---|---|');
            foreach ($directosPorPerfil as $pf => $c) {
                $this->line("| $pf | ".($c['residuo_copia_rol_a_directo'] ?? 0).' | '.($c['a_decidir'] ?? 0).' |');
            }
            $this->line("\n### Lectura que abre escritura");
            foreach ($lecturaAbreEscritura as $p => $esc) {
                $this->line("- `$p` → ".count($esc).' ruta(s) de escritura');
            }
        }

        return self::SUCCESS;
    }

    private function perfilDe(array $rids, $roles): string
    {
        $nombres = array_map(fn ($r) => $roles[$r] ?? '', $rids);
        foreach (self::PERFILES as $perfil => $rs) {
            if (array_intersect($rs, $nombres) && $perfil !== 'cliente') {
                return $perfil;
            }
        }

        return 'sin rol de staff';
    }

    private function tokens(string $perm): array
    {
        return preg_split('/[_.\-]+/', strtolower($perm)) ?: [];
    }

    private function esLectura(string $perm): bool
    {
        $t = $this->tokens($perm);

        return (bool) array_intersect($t, self::LECTURA) && ! array_intersect($t, self::ESCRIBE_TOKENS);
    }

    private function esEscritura(string $perm): bool
    {
        return (bool) array_intersect($this->tokens($perm), self::ESCRIBE_TOKENS);
    }
}
