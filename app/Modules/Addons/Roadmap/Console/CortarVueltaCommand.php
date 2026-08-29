<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Support\RegistroPids;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Cortar una vuelta A MANO, sin repetir el incidente del item #215.
 *
 * ── EL INCIDENTE ────────────────────────────────────────────────────────────────────────────
 * 2026-08-25 15:47: para cortar una vuelta se corrió `pkill -TERM -f 'claude -p Eres un EJECUTOR
 * ON-BOX'`. El PROPIO shell que ejecuta ese `pkill` trae el patrón LITERAL en su línea de comando
 * (es el argumento que le estás pasando) → `pkill -f`, que matchea contra la cmdline completa, se
 * mató a sí mismo. Agravante medido el mismo día: el latido `circuito:vivo --watch` (hijo en
 * background de `vuelta.sh`, mismo grupo de procesos) NO murió con la vuelta — quedó huérfano con
 * PPID=1, porque `pkill -f` sólo alcanza cmdlines que contienen el patrón, y la de `--watch` es
 * otra.
 *
 * ── EL PATRÓN SEGURO QUE ESTE COMANDO APLICA ───────────────────────────────────────────────────
 * 1. Nunca busca por patrón de cmdline. Identifica el PID (y su grupo) por el REGISTRO PROPIO del
 *    circuito (`RegistroPids`, identidad = PID + starttime — inmune a reciclado de PID).
 * 2. Mata por PGID (grupo de procesos), no por PID suelto: así el heartbeat `--watch`, que nace en
 *    el mismo grupo que `vuelta.sh` (ver deploy/circuito/vuelta.sh), cae CON la vuelta.
 * 3. Verifica APARTE, después de matar, que no sobrevive ningún proceso de ese grupo — nunca
 *    asume que la señal alcanzó a todos.
 * Dry-run por default: sin `--confirmar` sólo MUESTRA lo que hay en el registro para ese sid, para
 * que el operador lo revise ANTES de matar nada (regla 2 del item #215).
 */
class CortarVueltaCommand extends Command
{
    protected $signature = 'circuito:cortar-vuelta
        {--sid= : sid de la vuelta a cortar (wt-K). Sin esto, lista lo que hay registrado}
        {--confirmar : ejecuta la señal. Sin esto es dry-run: sólo muestra}
        {--senal=TERM : señal a enviar (TERM por default; KILL si TERM no bastó)}';

    protected $description = 'Corta una vuelta por PID/PGID del registro propio del circuito — nunca por pkill -f (#215).';

    public function handle(): int
    {
        $sid = trim((string) $this->option('sid'));

        if ($sid === '') {
            $this->error('Falta --sid=wt-K. Esto es lo que hay registrado ahora mismo:');
            $this->mostrarRegistro();

            return self::INVALID;
        }

        $entradas = array_values(array_filter(
            RegistroPids::todos(),
            fn (array $e) => $e['sid'] === $sid
        ));

        if ($entradas === []) {
            $this->info("No hay registro para sid={$sid}. No hay nada que este comando pueda cortar.");
            $this->line('Si algo sigue corriendo ahí, es AJENO al registro del circuito — investígalo antes de matarlo a ciegas.');

            return self::SUCCESS;
        }

        $entrada = $entradas[0];

        $this->line("Registro encontrado para sid={$sid}:");
        $this->line('  pid:    ' . $entrada['pid']);
        $this->line('  pgid:   ' . ($entrada['pgid'] ?: '(sin pgid registrado — sólo se tocará el pid)'));
        $this->line('  item:   ' . ($entrada['item'] ?? '?'));
        $this->line('  log:    ' . ($entrada['log'] ?? '?'));
        $this->line('  edad:   ' . (($entrada['edad_seg'] ?? null) !== null ? $entrada['edad_seg'] . 's' : '?'));
        $this->line('  estado: ' . ($entrada['vivo'] ? 'VIVO' : 'no vivo') . ' — ' . $entrada['motivo']);

        if (! $entrada['vivo']) {
            $this->warn('El registro dice que ya no está vivo. No mato nada.');
            $this->line('(Si el archivo quedó colgado de una vuelta muerta, bórralo a mano: ' . $entrada['archivo'] . ')');

            return self::SUCCESS;
        }

        if (! $this->option('confirmar')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se envió ninguna señal. Revisa los datos de arriba y repite con --confirmar.');

            return self::SUCCESS;
        }

        return $this->cortar($sid, $entrada, strtoupper((string) $this->option('senal')));
    }

    private function cortar(string $sid, array $entrada, string $senal): int
    {
        $senalConst = 'SIG' . $senal;
        if (! defined($senalConst)) {
            $this->error("Señal desconocida: {$senal}");

            return self::INVALID;
        }

        $pid = (int) $entrada['pid'];
        $pgid = $entrada['pgid'] !== null && $entrada['pgid'] !== '' ? (int) $entrada['pgid'] : null;
        $objetivo = $pgid ? -$pgid : $pid;

        $this->line('Enviando SIG' . $senal . ' a ' . ($pgid ? "el GRUPO de procesos {$pgid} (incluye el heartbeat --watch)" : "el PID {$pid} suelto (sin pgid registrado)") . '…');

        if (! @posix_kill($objetivo, constant($senalConst))) {
            $this->warn('posix_kill devolvió error (errno ' . posix_get_last_error() . ': ' . posix_strerror(posix_get_last_error()) . '). Puede que ya no exista.');
        }

        // Verificación APARTE: nunca se asume que la señal alcanzó a todo el grupo.
        sleep(2);
        $sobrevive = $this->procesosVivosDelGrupo($pgid ?? $pid);

        if ($sobrevive !== []) {
            $this->warn('Tras la señal SIGUEN vivos estos PIDs del mismo grupo (huérfano probable, ej. circuito:vivo --watch):');
            foreach ($sobrevive as $p) {
                $this->line("  pid {$p['pid']}: {$p['cmd']}");
            }
            $this->line('Si hace falta, repite con --senal=KILL. Sigue matando por PID exacto — nunca por patrón.');
        } else {
            $this->info('Verificado: ningún proceso del grupo sigue vivo (sin huérfanos).');
        }

        // Cierra el estado en vivo para que la Torre deje de mostrar "corriendo".
        try {
            Artisan::call('circuito:vivo', ['--end' => true, '--sid' => $sid]);
        } catch (\Throwable $e) {
            $this->warn('No pude cerrar el estado en vivo: ' . $e->getMessage());
        }

        // Limpia el archivo de registro por si el trap EXIT de vuelta.sh no llegó a correr.
        if (! empty($entrada['archivo']) && is_file($entrada['archivo'])) {
            @unlink($entrada['archivo']);
        }

        $this->info("Listo. sid={$sid} cortado.");

        return self::SUCCESS;
    }

    /** Escanea /proc buscando procesos cuyo PGID coincida — nunca por nombre/patrón de cmdline. */
    private function procesosVivosDelGrupo(int $pgid): array
    {
        $propio = getmypid();
        $out = [];

        foreach (glob('/proc/[0-9]*') ?: [] as $dir) {
            $pid = (int) basename($dir);
            if ($pid === $propio) {
                continue;
            }
            $stat = @file_get_contents($dir . '/stat');
            if ($stat === false) {
                continue;
            }
            $cierre = strrpos($stat, ')');
            if ($cierre === false) {
                continue;
            }
            $campos = preg_split('/\s+/', trim(substr($stat, $cierre + 1)));
            // Tras el ')', campo 0 = state(3), 1 = ppid(4), 2 = pgrp(5).
            $pgrp = isset($campos[2]) ? (int) $campos[2] : null;
            if ($pgrp !== $pgid) {
                continue;
            }
            $cmd = @file_get_contents($dir . '/cmdline');
            $cmd = $cmd !== false ? trim(str_replace("\0", ' ', $cmd)) : '?';
            $out[] = ['pid' => $pid, 'cmd' => $cmd !== '' ? $cmd : '?'];
        }

        return $out;
    }

    private function mostrarRegistro(): void
    {
        $todos = RegistroPids::todos();
        if ($todos === []) {
            $this->line('  (registro vacío — no hay vueltas registradas)');

            return;
        }
        foreach ($todos as $e) {
            $this->line(sprintf(
                '  sid=%s pid=%s pgid=%s item=%s %s',
                $e['sid'],
                $e['pid'],
                $e['pgid'] ?? '-',
                $e['item'] ?? '?',
                $e['vivo'] ? 'VIVO' : 'no vivo (' . $e['motivo'] . ')'
            ));
        }
    }
}
