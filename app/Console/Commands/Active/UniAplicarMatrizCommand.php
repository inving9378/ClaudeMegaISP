<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * UNI-T B7 (#876) — Aplica en DEV lo INEQUÍVOCO de la matriz de permisos (docs/unificacion/10-matriz-permisos.md).
 *
 * Única acción: retirar permisos DIRECTOS de cuentas que ya los reciben por uno de sus roles
 * (residuo de la copia rol→directo). El acceso efectivo (directos ∪ rol) no cambia: nadie pierde nada.
 * Lo «a decidir» (permisos sin rol que los dé) NO se toca: espera la respuesta de Irving.
 * Sin --aplicar solo simula. Con --aplicar respalda las 3 tablas y guarda las filas retiradas para revertir.
 * Reversión: --revertir=<archivo json>. Nunca syncRoles/syncPermissions.
 */
class UniAplicarMatrizCommand extends Command
{
    /** Decisión 3a (Irving, 30-oct): ver y validar a su equipo, sin configuración. */
    public const PERMISOS_SUPERVISION = [
        'task_view_full_task', 'talento.view', 'talento.validar_orden',
        'talento.work_orders.validate', 'talento.ia_validation.view',
    ];

    public const PERFILES = ['Mostrador', 'Vendedor'];

    protected $signature = 'uni:aplicar-matriz
                            {--aplicar : ejecuta el retiro (sin esto es dry-run)}
                            {--perfil= : Mostrador|Vendedor: además retira task_view_full_task y los directos «a decidir» de ese perfil (decisión 1a/2c)}
                            {--rol-supervision : da al rol SUPERVISOR_MOSTRADOR los permisos de ver y validar a su equipo (decisión 3a)}
                            {--revertir= : ruta del json de reversión generado por --aplicar}
                            {--dir=/var/backups/mysql/preservados/uni : carpeta de respaldos}';

    protected $description = 'UNI-T B7: retira directos redundantes con el rol (dry-run por defecto), con respaldo y reversión.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Solo DEV.');

            return 1;
        }
        if ($f = $this->option('revertir')) {
            return $this->revertir($f);
        }

        $perms = DB::table('permissions')->pluck('name', 'id');
        $roles = DB::table('roles')->pluck('name', 'id');
        $clientId = $roles->search('client');

        $porRol = [];
        foreach (DB::table('role_has_permissions')->get(['role_id', 'permission_id']) as $r) {
            $porRol[$r->role_id][$r->permission_id] = true;
        }
        $rolesDe = [];
        foreach (DB::table('model_has_roles')->where('model_type', 'like', '%User')->get(['role_id', 'model_id']) as $r) {
            $rolesDe[$r->model_id][] = $r->role_id;
        }

        $perfil = $this->option('perfil');
        if ($perfil && ! in_array($perfil, self::PERFILES, true)) {
            $this->error('--perfil debe ser Mostrador o Vendedor.');

            return 1;
        }
        $perfilId = $perfil ? $roles->search($perfil) : null;
        $fullTaskId = $perms->search('task_view_full_task');

        $retirar = [];
        $aDecidir = 0;
        $perfilRetirados = 0;
        foreach (DB::table('model_has_permissions')->where('model_type', 'like', '%User')->get() as $d) {
            $rids = array_diff($rolesDe[$d->model_id] ?? [], [$clientId]);
            if (! $rids || ! isset($perms[$d->permission_id])) {
                continue; // cuentas sin rol de staff: fuera de alcance
            }
            $loDa = false;
            foreach ($rids as $rid) {
                if (isset($porRol[$rid][$d->permission_id])) {
                    $loDa = true;
                    break;
                }
            }
            if ($loDa) {
                $retirar[] = (array) $d;
            } elseif ($perfilId !== null && array_values($rids) === [$perfilId]) { // solo cuentas cuyo único rol de staff es el perfil
                $retirar[] = (array) $d; // 2c: «a decidir» del perfil se retira
                $perfilRetirados++;
            } else {
                $aDecidir++;
            }
        }
        if ($perfilId !== null && $fullTaskId !== false) { // 1a: task_view_full_task directo, aunque el rol lo dé
            $yaEn = array_map('json_encode', $retirar);
            foreach (DB::table('model_has_permissions')->where('model_type', 'like', '%User')->where('permission_id', $fullTaskId)->get() as $d) {
                if (array_values(array_diff($rolesDe[$d->model_id] ?? [], [$clientId])) === [$perfilId] && ! in_array(json_encode((array) $d), $yaEn, true)) {
                    $retirar[] = (array) $d;
                    $perfilRetirados++;
                }
            }
        }

        $dar = [];
        if ($this->option('rol-supervision')) {
            $supId = $roles->search('SUPERVISOR_MOSTRADOR');
            if ($supId === false) {
                $this->error('No existe el rol SUPERVISOR_MOSTRADOR.');

                return 1;
            }
            foreach (self::PERMISOS_SUPERVISION as $n) {
                $pid = $perms->search($n);
                if ($pid !== false && ! isset($porRol[$supId][$pid])) {
                    $dar[] = ['role_id' => $supId, 'permission_id' => $pid];
                }
            }
        }

        $cuentas = count(array_unique(array_column($retirar, 'model_id')));
        $this->info('Directos redundantes con su rol a retirar: '.count($retirar)." en $cuentas cuentas.");
        $this->info("Directos «a decidir» (NO se tocan): $aDecidir.");
        if ($perfil) {
            $this->info("Perfil $perfil: $perfilRetirados filas extra a retirar (task_view_full_task + a decidir).");
        }
        if ($this->option('rol-supervision')) {
            $this->info('SUPERVISOR_MOSTRADOR: '.count($dar).' permisos a dar: '.implode(', ', array_map(fn ($x) => $perms[$x['permission_id']], $dar)));
        }

        if (! $this->option('aplicar')) {
            $this->warn('Dry-run: nada modificado. Usa --aplicar.');

            return 0;
        }

        $dir = rtrim($this->option('dir'), '/');
        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->error("No puedo escribir en $dir; abortado antes de modificar.");

            return 1;
        }
        $sello = date('Ymd-His');
        $sql = "$dir/uni-permisos-$sello.sql";
        $c = config('database.connections.mysql');
        $p = new Process(['mysqldump', '--single-transaction', '-h', $c['host'], '-u', $c['username'], $c['database'],
            'role_has_permissions', 'model_has_permissions', 'model_has_roles'], null, ['MYSQL_PWD' => $c['password']]);
        $p->setTimeout(300)->run();
        if (! $p->isSuccessful() || strlen($p->getOutput()) < 500) {
            $this->error('Respaldo falló; abortado antes de modificar.');

            return 1;
        }
        file_put_contents($sql, $p->getOutput());
        $rev = "$dir/uni-permisos-$sello-revertir.json";
        file_put_contents($rev, json_encode(['retirar' => $retirar, 'dar_rol' => $dar]));

        DB::transaction(function () use ($retirar, $dar) {
            foreach ($retirar as $d) {
                DB::table('model_has_permissions')->where($d)->delete();
            }
            foreach ($dar as $d) {
                DB::table('role_has_permissions')->insertOrIgnore($d);
            }
        });
        $this->call('permission:cache-reset');
        $this->info("Retiradas ".count($retirar)." filas. Respaldo: $sql. Reversión: php artisan uni:aplicar-matriz --revertir=$rev");

        return 0;
    }

    private function revertir(string $f): int
    {
        $json = json_decode((string) @file_get_contents($f), true);
        if (! is_array($json)) {
            $this->error('Archivo de reversión inválido.');

            return 1;
        }
        // Formato viejo (lista plana de directos) o nuevo {retirar, dar_rol}.
        $filas = $json['retirar'] ?? (array_is_list($json) ? $json : []);
        $dar = $json['dar_rol'] ?? [];
        $n = 0;
        DB::transaction(function () use ($filas, $dar, &$n) {
            foreach ($dar as $d) {
                DB::table('role_has_permissions')->where($d)->delete();
            }
            foreach ($filas as $d) {
                if (! DB::table('model_has_permissions')->where($d)->exists()) {
                    DB::table('model_has_permissions')->insert($d);
                    $n++;
                }
            }
        });
        $this->call('permission:cache-reset');
        $this->info("Restauradas $n filas; revocados ".count($dar)." permisos de rol.");

        return 0;
    }
}
