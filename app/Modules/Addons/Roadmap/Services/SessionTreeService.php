<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Árbol de sesiones `claude` vivas en el box de dev (#345), SOLO LECTURA de sistema operativo.
 *
 * POR QUÉ: la causa raíz del incidente 2026-07-11 fue dos sesiones de Claude Code pisándose en
 * el mismo repo, sin forma de verlo de un vistazo. Este servicio lista los procesos `claude`
 * reales (ps + /proc/{pid}/cwd — lo mismo que usó el diagnóstico manual del incidente), deriva
 * su worktree (#334) y los cruza con el registro de latido del circuito (trabajandoAhora) para
 * enriquecer las sesiones autónomas con su item/fase en curso. Marca colisión cuando 2+ sesiones
 * comparten EXACTAMENTE el mismo cwd — exactamente el escenario del incidente.
 *
 * NUNCA mata ni toca procesos. Nunca expone el cmdline completo (trae el prompt entero, con
 * detalle interno) — solo pid/cwd/tipo/tiempos derivados.
 */
class SessionTreeService
{
    public function __construct(private RoadmapCircuitoService $svc)
    {
    }

    /** Árbol completo para el futuro panel de la Torre + banner de colisión. */
    public function arbol(): array
    {
        $procesos  = $this->procesosClaude();
        $liveBySid = collect($this->svc->trabajandoAhora()['sesiones'] ?? [])->keyBy('sid');

        $sesiones = [];
        foreach ($procesos as $p) {
            $sid  = $this->sidDeCwd($p['cwd']);
            $live = $sid ? $liveBySid->get($sid) : null;

            $sesiones[] = [
                'pid'                => $p['pid'],
                'tipo'               => $p['tipo'],            // circuito_autonomo | cc_interactivo
                'cwd'                => $p['cwd'],
                'worktree'           => $sid,                  // wt-K si vive bajo /circuito/wt-K, si no null
                'arrancado_hace_seg' => $p['etimes'],
                'item'               => $live['item'] ?? null,
                'fase_actual'        => $live['fase_actual'] ?? null,
                'estado'             => $live ? ($live['stale'] ? 'ocioso' : 'activo') : 'desconocido',
                'hace_latido_seg'    => $live['segundos_desde_latido'] ?? null,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'sesiones'     => $sesiones,
            'colision'     => $this->detectaColision($sesiones),
        ];
    }

    /** Lista procesos `claude` vivos (ps + cwd vía /proc). Best-effort: nunca truena la Torre. */
    private function procesosClaude(): array
    {
        try {
            $proc = Process::fromShellCommandline('ps -eo pid,etimes,cmd --no-headers');
            $proc->setTimeout(5);
            $proc->run();
            if (! $proc->isSuccessful()) {
                return [];
            }
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('session-tree-ps-fallo', ['error' => $e->getMessage()]);

            return [];
        }

        $out = [];
        foreach (explode("\n", $proc->getOutput()) as $line) {
            $line = trim($line);
            if ($line === '' || ! preg_match('/^(\d+)\s+(\d+)\s+(.*)$/', $line, $m)) {
                continue;
            }
            [, $pid, $etimes, $cmd] = $m;

            // Solo el binario `claude` EN SÍ, como primer token del cmd — evita `grep claude`,
            // `circuito:vivo --watch`, editores, Y el wrapper `timeout 600 claude -p ...` (que es
            // el PADRE del proceso real y duplicaría cada sesión si se contara también).
            if (! preg_match('/^claude(\s|$)/', $cmd)) {
                continue;
            }

            $cwd = $this->cwdDePid((int) $pid);
            if ($cwd === null) {
                continue;   // murió entre el ps y el readlink, o sin permiso de lectura
            }

            $out[] = [
                'pid'    => (int) $pid,
                'etimes' => (int) $etimes,
                // `claude -p "..."` (con prompt, headless) = vuelta autónoma del circuito;
                // `claude` interactivo (sin -p) = sesión de desarrollo de un humano.
                'tipo'   => preg_match('/^claude\s+-p\b/', $cmd) ? 'circuito_autonomo' : 'cc_interactivo',
                'cwd'    => $cwd,
            ];
        }

        return $out;
    }

    private function cwdDePid(int $pid): ?string
    {
        $link = @readlink("/proc/{$pid}/cwd");

        return $link !== false && $link !== '' ? $link : null;
    }

    /** Deriva el sid `wt-K` del cwd si la sesión vive bajo el árbol de worktrees del circuito (#334). */
    private function sidDeCwd(string $cwd): ?string
    {
        return preg_match('#/circuito/(wt-\d+)(/|$)#', $cwd, $m) ? $m[1] : null;
    }

    /** Colisión = 2+ sesiones EXACTAMENTE en el mismo cwd — el escenario real del incidente 2026-07-11. */
    private function detectaColision(array $sesiones): array
    {
        $porCwd = [];
        foreach ($sesiones as $s) {
            $porCwd[$s['cwd']][] = $s['pid'];
        }

        $choques = [];
        foreach ($porCwd as $cwd => $pids) {
            if (count($pids) > 1) {
                $choques[] = ['cwd' => $cwd, 'pids' => $pids];
            }
        }

        return [
            'hay_colision' => ! empty($choques),
            'detalle'      => $choques,
        ];
    }
}
