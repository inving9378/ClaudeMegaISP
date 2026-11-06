<?php

namespace App\Modules\Core\Auditoria\Services;

use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

/**
 * Item #9990765 (Fase 4c de #9990745, sub-item de #9990721) — PUNTO 4: matriz por rol de
 * permisos asignados (role_has_permissions) cruzada contra cuáles REALMENTE surten efecto.
 *
 * Fuente de "surte_efecto": el CSV de la Fase 4a (item #9990763,
 * docs/auditoria/permisos-punto1-2.csv), que ya clasificó los 770 permisos existentes en
 * usado/huérfano mediante grep de una sola pasada contra app/, resources/, routes/, config/.
 * Reusar ese resultado evita re-grepear el repo completo en cada carga de la vista. Un permiso
 * que no esté en ese CSV (creado DESPUÉS de la Fase 4a) se recalcula aquí mismo, en vivo, con
 * el mismo método — solo para ese permiso puntual, no para los 770 (barato).
 *
 * SOLO LECTURA — no modifica roles, permisos ni asignaciones.
 */
class PermisosMatrizRolesService
{
    public const CSV_ESTADO = 'docs/auditoria/permisos-punto1-2.csv';
    public const CSV_SALIDA = 'docs/auditoria/permisos-matriz-roles.csv';

    private const DIRS = ['app', 'resources', 'routes', 'config'];

    private const EXTENSIONS = ['php', 'blade.php', 'vue', 'js', 'json'];

    /**
     * Permisos de alto riesgo (dinero/auth/prod) — mismo criterio que usa el propio circuito
     * para clasificar items de seguridad/dinero (ver CLAUDE.md, `briefarSeguridad`).
     */
    private const RIESGO_KEYWORDS = [
        'payment', 'pago', 'conciliacion', 'domiciliacion', 'factura', 'invoice',
        'cobranza', 'openpay', 'role', 'permission', 'user_delete', 'credential',
    ];

    /**
     * @return array{
     *     filas: array<int, array{rol:string, permiso:string, guard:string, surte_efecto:?bool, alto_riesgo:bool}>,
     *     resumen_roles: array<int, array{rol:string, usuarios:int, total:int, efectivos:int, huerfanos:int, desconocidos:int, inflado:bool}>,
     *     roles_inflados: array,
     *     alto_riesgo_sin_efecto: array<int, array{rol:string, permiso:string}>,
     *     fuente_csv: bool
     * }
     */
    public function build(): array
    {
        $estado = $this->cargarEstadoCsv();
        $fuenteCsv = ! empty($estado);

        $roles = Role::with('permissions')->orderBy('name')->get();

        $filas = [];
        $resumenRoles = [];
        $altoRiesgoSinEfecto = [];

        foreach ($roles as $role) {
            $total = 0;
            $efectivos = 0;
            $huerfanos = 0;
            $desconocidos = 0;

            foreach ($role->permissions as $permiso) {
                $surteEfecto = $this->surteEfecto($permiso->name, $estado);
                $altoRiesgo = $this->esAltoRiesgo($permiso->name);

                $total++;
                if ($surteEfecto === true) {
                    $efectivos++;
                } elseif ($surteEfecto === false) {
                    $huerfanos++;
                } else {
                    $desconocidos++;
                }

                if ($altoRiesgo && $surteEfecto === false) {
                    $altoRiesgoSinEfecto[] = ['rol' => $role->name, 'permiso' => $permiso->name];
                }

                $filas[] = [
                    'rol'          => $role->name,
                    'permiso'      => $permiso->name,
                    'guard'        => $permiso->guard_name,
                    'surte_efecto' => $surteEfecto,
                    'alto_riesgo'  => $altoRiesgo,
                ];
            }

            $resumenRoles[] = [
                'rol'          => $role->name,
                'usuarios'     => $role->users()->count(),
                'total'        => $total,
                'efectivos'    => $efectivos,
                'huerfanos'    => $huerfanos,
                'desconocidos' => $desconocidos,
                // Rol "inflado" = tiene más permisos que no protegen nada (huérfanos) que
                // permisos que sí surten efecto — falsa sensación de control sobre ese rol.
                'inflado'      => $total > 0 && $huerfanos > $efectivos,
            ];
        }

        $rolesInflados = array_values(array_filter($resumenRoles, fn ($r) => $r['inflado']));

        return [
            'filas'                  => $filas,
            'resumen_roles'          => $resumenRoles,
            'roles_inflados'         => $rolesInflados,
            'alto_riesgo_sin_efecto' => $altoRiesgoSinEfecto,
            'fuente_csv'             => $fuenteCsv,
        ];
    }

    public function escribirCsv(array $filas, ?string $path = null): string
    {
        $path = $path ?: base_path(self::CSV_SALIDA);
        File::ensureDirectoryExists(dirname($path));

        $fh = fopen($path, 'w');
        fputcsv($fh, ['rol', 'permiso', 'guard', 'surte_efecto', 'alto_riesgo']);
        foreach ($filas as $f) {
            fputcsv($fh, [
                $f['rol'],
                $f['permiso'],
                $f['guard'],
                $f['surte_efecto'] === null ? 'desconocido' : ($f['surte_efecto'] ? 'si' : 'no'),
                $f['alto_riesgo'] ? 'si' : 'no',
            ]);
        }
        fclose($fh);

        return $path;
    }

    /** @return array<string, string> nombre de permiso => 'usado'|'huerfano' */
    private function cargarEstadoCsv(): array
    {
        $path = base_path(self::CSV_ESTADO);
        if (! File::exists($path)) {
            return [];
        }

        $out = [];
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        $idxName = array_search('name', $header, true);
        $idxEstado = array_search('estado', $header, true);
        if ($idxName === false || $idxEstado === false) {
            fclose($fh);

            return [];
        }

        while (($row = fgetcsv($fh)) !== false) {
            if (! isset($row[$idxName])) {
                continue;
            }
            $out[$row[$idxName]] = $row[$idxEstado] ?? null;
        }
        fclose($fh);

        return $out;
    }

    /** true = usado, false = huérfano, null = sin dato (ni CSV ni recálculo en vivo pudo determinarlo). */
    private function surteEfecto(string $nombrePermiso, array $estadoCsv): ?bool
    {
        if (isset($estadoCsv[$nombrePermiso])) {
            return $estadoCsv[$nombrePermiso] === 'usado';
        }

        // Permiso ausente del CSV de la Fase 4a (creado después) — recálculo puntual en vivo,
        // mismo método (grep -F literal, excluyendo la propia declaración en module.json).
        return $this->detectarUsoEnVivo($nombrePermiso);
    }

    private function detectarUsoEnVivo(string $nombre): ?bool
    {
        $includeArgs = [];
        foreach (self::EXTENSIONS as $ext) {
            $includeArgs[] = '--include=*.' . $ext;
        }

        $process = new Process(array_merge(
            ['grep', '-rnF', $nombre],
            $includeArgs,
            self::DIRS
        ), base_path());
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful() && $process->getExitCode() > 1) {
            // 2+ = error real de grep (no "sin matches"); no podemos afirmar nada.
            return null;
        }

        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^([^:]+):(\d+):(.*)$/', $line, $m)) {
                continue;
            }
            $esModuleJson = str_ends_with($m[1], 'module.json');
            $esPropiaDeclaracion = $esModuleJson
                && preg_match('/"name"\s*:\s*"' . preg_quote($nombre, '/') . '"/', $m[3]);
            if (! $esPropiaDeclaracion) {
                return true;
            }
        }

        return false;
    }

    private function esAltoRiesgo(string $nombre): bool
    {
        $n = strtolower($nombre);
        foreach (self::RIESGO_KEYWORDS as $kw) {
            if (str_contains($n, $kw)) {
                return true;
            }
        }

        return false;
    }
}
