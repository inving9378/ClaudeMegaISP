<?php

namespace App\Console\Commands\Active;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SOLO LECTURA (items #1193 y #1194). Lista a quienes pudieron perder su rol base por el check
 * «Añadir rol de vendedor»: hasta la corrección, el formulario de edición manejaba un solo rol (el
 * de id más bajo) y a un Técnico + Vendedor le mostraba «Vendedor»; al guardar cualquier dato
 * perdía Técnico.
 *
 * Evidencia que cruza, sin escribir nada:
 *   - user_role_change_audits: cambios desde el formulario donde se quitó un rol y quedó Vendedor.
 *   - storage/logs/laravel*.log: líneas «Roles actualizados desde el form de Usuarios (diff dirigido)».
 *   - Hoy solo tiene Vendedor y su ficha de Talento tiene un puesto que corresponde a otro rol
 *     (talento_role_departments).
 *
 * No corrige: la corrección de cada persona la aprueba Irving.
 */
class SospechososRolVendedorCommand extends Command
{
    protected $signature = 'usuarios:sospechosos-rol-vendedor
                            {--todos : incluye también a quienes solo tienen Vendedor sin otra evidencia}
                            {--sin-logs : no revisa storage/logs}
                            {--json : salida en JSON}';

    protected $description = 'SOLO LECTURA: personas que pudieron perder su rol base por el check «Añadir rol de vendedor». No cambia nada.';

    private const MENSAJE_LOG = 'Roles actualizados desde el form de Usuarios (diff dirigido)';

    /** Roles que no cuentan como «rol base» perdido. */
    private const NO_BASE = ['Vendedor', 'client'];

    public function handle(): int
    {
        $morph = (new User())->getMorphClass();
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $morph)
            ->get(['model_has_roles.model_id as user_id', 'roles.name'])
            ->groupBy('user_id')
            ->map(fn ($filas) => $filas->pluck('name')->sort()->values()->all());

        $evidencia = $this->evidenciaDeAuditoria();
        if (! $this->option('sin-logs')) {
            foreach ($this->evidenciaDeLogs() as $userId => $hallazgos) {
                $evidencia[$userId] = array_merge($evidencia[$userId] ?? [], $hallazgos);
            }
        }

        $puestos = Schema::hasTable('talento_colaboradores')
            ? DB::table('talento_colaboradores')->whereNull('deleted_at')->get(['user_id', 'department', 'status'])->keyBy('user_id')
            : collect();
        $rolesDePuesto = Schema::hasTable('talento_role_departments')
            ? DB::table('talento_role_departments')->get(['role_name', 'department'])->groupBy('department')
                ->map(fn ($filas) => $filas->pluck('role_name')->all())
            : collect();

        $candidatos = collect(array_keys($evidencia))
            ->merge($roles->filter(fn ($r) => $r === ['Vendedor'])->keys())
            ->unique();
        $usuarios = DB::table('users')->whereIn('id', $candidatos)->get(['id', 'login_user', 'estado'])->keyBy('id');

        $filas = [];
        foreach ($candidatos as $userId) {
            $usuario = $usuarios->get($userId);
            if (! $usuario) {
                continue;
            }
            $actuales = $roles->get($userId, []);
            $puesto = $puestos->get($userId);

            // Roles que la bitácora dice que perdió y hoy no tiene. Del log solo cuenta si ese
            // cambio le puso Vendedor o si hoy lo tiene (un cambio de puesto normal no es sospechoso).
            $hallazgos = array_values(array_filter(
                $evidencia[$userId] ?? [],
                fn ($h) => $h['con_vendedor'] || in_array('Vendedor', $actuales, true)
            ));
            $perdidos = collect($hallazgos)->pluck('perdio')->flatten()->unique()
                ->reject(fn ($rol) => in_array($rol, $actuales, true))->values()->all();
            $rolDelPuesto = $puesto ? ($rolesDePuesto->get($puesto->department) ?? []) : [];
            $soloVendedor = $actuales === ['Vendedor'];

            $motivos = [];
            if ($perdidos) {
                $motivos[] = 'la bitácora registra que perdió ' . implode(', ', $perdidos);
            }
            if ($soloVendedor && $rolDelPuesto) {
                $motivos[] = "solo tiene Vendedor y su puesto en Talento es «{$puesto->department}»";
            }
            if (! $motivos && $soloVendedor && $this->option('todos')) {
                $motivos[] = 'solo tiene Vendedor (sin otra evidencia)';
            }
            if (! $motivos) {
                continue;
            }

            $filas[] = [
                'id' => (int) $usuario->id,
                'login' => $usuario->login_user,
                'estado' => $usuario->estado,
                'roles_actuales' => implode(' + ', $actuales) ?: '(ninguno)',
                'puesto_talento' => $puesto ? "{$puesto->department} ({$puesto->status})" : '—',
                'rol_base_propuesto' => $perdidos ? implode(' + ', $perdidos) : (implode(' o ', $rolDelPuesto) ?: '—'),
                'fecha' => collect($hallazgos)->pluck('fecha')->filter()->max() ?? '—',
                'evidencia' => implode('; ', $motivos)
                    . ($hallazgos ? ' [' . implode(', ', array_unique(array_column($hallazgos, 'fuente'))) . ']' : ''),
            ];
        }

        usort($filas, fn ($a, $b) => $a['id'] <=> $b['id']);

        if ($this->option('json')) {
            $this->line(json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if (! $filas) {
            $this->info('Sin sospechosos: nadie aparece con un rol base perdido por el check de vendedor.');
        } else {
            $this->table(
                ['id', 'login', 'estado', 'roles actuales', 'puesto en Talento', 'rol base propuesto', 'fecha', 'evidencia'],
                array_map('array_values', $filas)
            );
            $this->line(count($filas) . ' sospechoso(s).');
        }
        $this->line('Solo lectura: no se cambió nada. La corrección de cada persona la aprueba Irving.');

        return self::SUCCESS;
    }

    /** @return array<int, array<int, array{fecha: ?string, perdio: string[], con_vendedor: bool, fuente: string}>> */
    private function evidenciaDeAuditoria(): array
    {
        if (! Schema::hasTable('user_role_change_audits')) {
            return [];
        }

        $evidencia = [];
        foreach (DB::table('user_role_change_audits')->orderBy('id')->get() as $cambio) {
            $antes = json_decode($cambio->roles_antes ?? '[]', true) ?: [];
            $despues = json_decode($cambio->roles_despues ?? '[]', true) ?: [];
            $perdidos = array_values(array_diff($antes, $despues, self::NO_BASE));
            if ($perdidos && in_array('Vendedor', $despues, true)) {
                $evidencia[(int) $cambio->target_user_id][] = [
                    'fecha' => $cambio->created_at,
                    'perdio' => $perdidos,
                    'con_vendedor' => true,
                    'fuente' => "auditoría #{$cambio->id}",
                ];
            }
        }

        return $evidencia;
    }

    /** @return array<int, array<int, array{fecha: ?string, perdio: string[], con_vendedor: bool, fuente: string}>> */
    private function evidenciaDeLogs(): array
    {
        $porLogin = null;
        $evidencia = [];
        foreach (glob(storage_path('logs/laravel*.log')) ?: [] as $archivo) {
            $lector = new \SplFileObject($archivo);
            while (! $lector->eof()) {
                $linea = (string) $lector->fgets();
                $posicion = strpos($linea, self::MENSAJE_LOG);
                if ($posicion === false
                    || ! preg_match('/\{.*\}/', substr($linea, $posicion + strlen(self::MENSAJE_LOG)), $m)) {
                    continue;
                }
                $contexto = json_decode($m[0], true);
                $perdidos = array_values(array_diff((array) ($contexto['quitados'] ?? []), self::NO_BASE));
                if (! $perdidos || empty($contexto['target'])) {
                    continue;
                }
                $porLogin ??= DB::table('users')->pluck('id', 'login_user');
                $userId = $porLogin[$contexto['target']] ?? null;
                if ($userId) {
                    $evidencia[(int) $userId][] = [
                        'fecha' => $contexto['timestamp'] ?? null,
                        'perdio' => $perdidos,
                        'con_vendedor' => in_array('Vendedor', (array) ($contexto['agregados'] ?? []), true),
                        'fuente' => 'log ' . basename($archivo),
                    ];
                }
            }
        }

        return $evidencia;
    }
}
