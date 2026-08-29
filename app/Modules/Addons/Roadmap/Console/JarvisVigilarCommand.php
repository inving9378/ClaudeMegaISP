<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Support\FrenoCircuito;
use App\Modules\Addons\Roadmap\Support\GraciaDeArranque;
use App\Modules\Addons\Roadmap\Support\RegistroPids;
use App\Modules\Addons\Roadmap\Support\JarvisVigilia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * LA VIGILIA DE JARVIS — entrega A: modo mínimo, cron propio, hombre muerto.
 *
 * ── QUÉ HACE Y QUÉ NO ───────────────────────────────────────────────────────────────────────
 * MIDE y DEJA CONSTANCIA. No aísla, no baja concurrencia, no comprime, no trunca, no mata y no
 * pausa. Esa autoridad llega en entregas posteriores y por separado, sobre el registro de PIDs
 * que este comando estrena. Un vigilante que mide bien y no puede hacer nada es incompleto; uno
 * que puede hacer cosas sin medir bien es peligroso, y ya sabemos cuál de los dos duele más.
 *
 * ── POR QUÉ NO PUEDE DEPENDER DE LA BASE ────────────────────────────────────────────────────
 * Todo lo demás de Jarvis vive en MySQL, así que el 22-ago se cayó con ella. Aquí el orden está
 * invertido a propósito: PRIMERO se mide todo lo que se puede leer del sistema de archivos y de
 * /proc —disco, memoria, logs, procesos, registro, freno—, y sólo AL FINAL, dentro de un
 * try/catch, se intenta la base. Si falla, el modo queda en `minimo`, se dice con esas palabras
 * y la vuelta se guarda igual. La ausencia de la base es un dato, no una excepción.
 *
 * ── EL LOG QUE NO SE MEDÍA ──────────────────────────────────────────────────────────────────
 * La sonda de compuertas mide UN `laravel.log`: el del checkout principal (39.9 MB el 25-ago).
 * Pero cada worktree tiene su `storage/` REAL, así que hay SIETE creciendo — y el de wt-2 pesaba
 * 1.86 GB sin que ninguna pantalla lo dijera. Medir el archivo equivocado no es un problema de
 * limpieza: es falsa calma, que es el peor modo de fallo de un vigilante. Aquí se recorren todos
 * con glob, para que un worktree nuevo entre solo.
 */
class JarvisVigilarCommand extends Command
{
    protected $signature = 'circuito:jarvis-vigilar
        {--print : imprime la medición en pantalla además de guardarla}
        {--seco : mide e imprime SIN escribir el estado (para inspeccionar sin tocar el latido)}';

    protected $description = 'Vigilia de Jarvis: mide disco, memoria, logs, procesos y registro SIN depender de la base.';

    public function handle(): int
    {
        if (! config('circuito.jarvis.vigilia.enabled', true)) {
            $this->warn('La vigilia está apagada (circuito.jarvis.vigilia.enabled=false).');

            return self::SUCCESS;
        }

        $ps = $this->psCrudo();
        $anterior = JarvisVigilia::estado();

        $estado = [
            'medido_en' => date('c'),
            'medido_ts' => time(),
            'hostname'  => gethostname(),
            'modo'      => 'minimo',            // se sube a 'completo' si la base contesta
            'disco'     => $this->medirDisco(),
            'memoria'   => $this->medirMemoria(),
            'carga'     => $this->medirCarga(),
            'logs'      => $this->medirLogs(),
            'procesos'  => $this->medirProcesos($ps),
            'registro'  => $this->medirRegistro(),
            'freno'     => $this->medirFreno(),
            'sonda'     => $this->medirSonda(),
        ];

        $estado['bd_integra'] = $this->medirBdIntegra($anterior);

        // FRENAR ANTES DE AVISAR (item #228). El 25-ago el freno lo puso una persona 21 minutos
        // tarde; el gate humano va en QUÉ se construye, no en CUÁNDO se detiene el daño. Se llama
        // aquí, antes de `guardar()` (lo que hace visible el aviso en la Torre/CLI), para que el
        // orden en disco sea siempre freno→aviso. Si ya estaba puesto (por un humano o por una
        // vuelta anterior) no se toca: no se pisa su motivo/quién/cuándo original.
        if (! $this->option('seco') && $estado['bd_integra']['escalon'] === 'critico' && ! FrenoCircuito::activo()) {
            try {
                FrenoCircuito::poner($this->motivoBdIntegra($estado['bd_integra']), 'jarvis:bd_integra');
            } catch (\Throwable $e) {
                FrenoCircuito::registrarFallo('jarvis:bd_integra', $e);
            }
        }

        $estado['base'] = $this->medirBase($estado);
        $estado['modo'] = $estado['base']['responde'] ? 'completo' : 'minimo';
        $estado['alertas'] = $this->alertas($estado);

        if (! $this->option('seco')) {
            try {
                JarvisVigilia::guardar($estado);
            } catch (\Throwable $e) {
                // Si no puede guardar, NO finge que midió: el latido viejo se queda viejo y el
                // hombre muerto de la Torre se dispara solo. Eso es exactamente lo que debe pasar.
                $this->error('No se pudo guardar la vigilia: ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('print') || $this->option('seco')) {
            $this->line(json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }

        foreach ($estado['alertas'] as $a) {
            $this->warn(strtoupper($a['nivel']) . ' · ' . $a['texto']);
        }

        return self::SUCCESS;
    }

    // ── SISTEMA DE ARCHIVOS Y MEMORIA ───────────────────────────────────────────────────────

    /** `df -P` (POSIX) y no `df -h`: columnas estables y en bloques, sin sufijos que parsear. */
    private function medirDisco(): array
    {
        $raw = $this->sh("df -P / 2>/dev/null | tail -1");
        $c   = preg_split('/\s+/', trim((string) $raw));

        $pct = isset($c[4]) ? (int) rtrim($c[4], '%') : 0;
        $esc = (array) config('umbrales_disco.escalones', []);

        return [
            'uso_pct'      => $pct,
            'total_kb'     => (int) ($c[1] ?? 0),
            'usado_kb'     => (int) ($c[2] ?? 0),
            'libre_kb'     => (int) ($c[3] ?? 0),
            'libre_legible' => $this->humano(((int) ($c[3] ?? 0)) * 1024),
            'escalon'      => $this->escalonDisco($pct, $esc),
            'escalones'    => $esc,
        ];
    }

    /** Escalón alcanzado. Devuelve el NOMBRE del peldaño, no una acción: aquí nadie actúa. */
    private function escalonDisco(int $pct, array $esc): string
    {
        foreach (['pausa', 'trunca', 'comprime', 'avisa'] as $nombre) {
            if (isset($esc[$nombre]) && $pct >= (int) $esc[$nombre]) {
                return $nombre;
            }
        }

        return 'ok';
    }

    /** RAM y SWAP: el encargo pide vigilar las dos. Hoy nadie del circuito las mira. */
    private function medirMemoria(): array
    {
        $info = [];
        foreach (explode("\n", (string) @file_get_contents('/proc/meminfo')) as $l) {
            if (preg_match('/^(\w+):\s+(\d+)/', $l, $m)) {
                $info[$m[1]] = (int) $m[2];   // kB
            }
        }
        $swapTotal = $info['SwapTotal'] ?? 0;
        $swapUsado = $swapTotal - ($info['SwapFree'] ?? 0);

        return [
            'ram_total_kb'     => $info['MemTotal'] ?? 0,
            'ram_disponible_kb' => $info['MemAvailable'] ?? 0,
            'ram_disponible_pct' => ($info['MemTotal'] ?? 0) > 0
                ? (int) round(100 * ($info['MemAvailable'] ?? 0) / $info['MemTotal']) : 0,
            'swap_total_kb'    => $swapTotal,
            'swap_usado_kb'    => $swapUsado,
            'swap_usado_pct'   => $swapTotal > 0 ? (int) round(100 * $swapUsado / $swapTotal) : 0,
        ];
    }

    private function medirCarga(): array
    {
        $c = preg_split('/\s+/', trim((string) @file_get_contents('/proc/loadavg')));

        return ['1min' => (float) ($c[0] ?? 0), '5min' => (float) ($c[1] ?? 0), '15min' => (float) ($c[2] ?? 0)];
    }

    /** LOS SIETE `laravel.log`, no uno. Ver la nota de cabecera. */
    private function medirLogs(): array
    {
        $rutas = [];
        $principal = (string) config('circuito.jarvis.vigilia.log_principal');
        if ($principal !== '') {
            $rutas['principal'] = $principal;
        }
        $raiz = rtrim((string) config('circuito.jarvis.vigilia.raiz_worktrees'), '/');
        foreach (glob($raiz . '/*/storage/logs/laravel.log') ?: [] as $r) {
            $rutas[basename(dirname($r, 3))] = $r;   // .../wt-2/storage/logs/laravel.log → wt-2
        }

        $archivos = [];
        $total = 0;
        foreach ($rutas as $etiqueta => $ruta) {
            clearstatcache(true, $ruta);
            $b = is_readable($ruta) ? (int) @filesize($ruta) : 0;
            $total += $b;
            $archivos[] = [
                'donde'    => $etiqueta,
                'ruta'     => $ruta,
                'bytes'    => $b,
                'legible'  => $this->humano($b),
                'mtime'    => is_readable($ruta) ? date('c', (int) @filemtime($ruta)) : null,
            ];
        }
        usort($archivos, fn ($a, $b) => $b['bytes'] <=> $a['bytes']);

        return [
            'cuantos'      => count($archivos),
            'total_bytes'  => $total,
            'total_legible' => $this->humano($total),
            'mayor'        => $archivos[0] ?? null,
            'archivos'     => $archivos,
        ];
    }

    // ── PROCESOS ────────────────────────────────────────────────────────────────────────────

    private function psCrudo(): array
    {
        $raw = (string) $this->sh('ps -eo pid,ppid,etimes,tty,args --no-headers 2>/dev/null');
        $filas = [];
        foreach (explode("\n", $raw) as $l) {
            if (! preg_match('/^\s*(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(.*)$/', $l, $m)) {
                continue;
            }
            $filas[] = ['pid' => (int) $m[1], 'ppid' => (int) $m[2], 'seg' => (int) $m[3], 'tty' => $m[4], 'cmd' => $m[5]];
        }

        return $filas;
    }

    /**
     * Tres poblaciones que hoy se confunden y NO son la misma cosa:
     *  · vueltas    — `vuelta.sh`, el ejecutor del circuito.
     *  · agentes    — `timeout N claude -p`, el trabajo headless de una vuelta.
     *  · interactivos — `claude` con TTY: sesiones de humano. NUNCA se tocan. El 25-ago había
     *    cuatro de 41 días colgando de un `tmux` y una de ellas era la sesión viva de Irving.
     */
    private function medirProcesos(array $ps): array
    {
        $vueltas = $agentes = $interactivos = [];

        foreach ($ps as $p) {
            if (str_contains($p['cmd'], 'deploy/circuito/vuelta.sh')) {
                // `huerfano` (PPID=1) se conserva porque describe un hecho, pero NO es la alarma:
                // el cron lanza toda vuelta desprendida, así que es el estado normal. Lo que delata
                // a la del 22-ago (1d21h viva) es haber pasado su propio timeout — ver `colgado`.
                $vueltas[] = ['pid' => $p['pid'], 'ppid' => $p['ppid'], 'seg' => $p['seg'],
                    'huerfano' => $p['ppid'] === 1,
                    'colgado'  => $p['seg'] > (int) config('circuito.vuelta_colgada_seg', 3600), ];
                continue;
            }
            if (preg_match('/timeout\s+\d+\s+claude\s+-p/', $p['cmd'])) {
                $agentes[] = ['pid' => $p['pid'], 'ppid' => $p['ppid'], 'seg' => $p['seg']];
                continue;
            }
            if (preg_match('/(^|\/)claude(\s|$)/', $p['cmd']) && $p['tty'] !== '?') {
                $interactivos[] = ['pid' => $p['pid'], 'tty' => $p['tty'], 'seg' => $p['seg'], 'ppid' => $p['ppid']];
            }
        }

        $viejo = (int) config('circuito.jarvis.vigilia.claude_viejo_seg', 86400);

        return [
            'vueltas'            => count($vueltas),
            'vueltas_huerfanas'  => count(array_filter($vueltas, fn ($v) => $v['huerfano'])),
            'vueltas_colgadas'   => count(array_filter($vueltas, fn ($v) => $v['colgado'])),
            'vueltas_detalle'    => $vueltas,
            'agentes_claude'     => count($agentes),
            'interactivos'       => count($interactivos),
            // Se REPORTAN, jamás se tocan: pueden ser la sesión con la que Irving trabaja ahora.
            'interactivos_viejos' => array_values(array_filter($interactivos, fn ($i) => $i['seg'] > $viejo)),
        ];
    }

    private function medirRegistro(): array
    {
        $todos = RegistroPids::todos();

        return [
            'dir'          => RegistroPids::dir(),
            'entradas'     => count($todos),
            'vivos'        => count(array_filter($todos, fn ($e) => $e['vivo'])),
            'colgados'     => array_values(array_filter($todos, fn ($e) => ! $e['vivo'])),
            'pasados_timeout' => RegistroPids::pasadasDeTimeout(),
            'detalle'      => $todos,
        ];
    }

    private function medirFreno(): array
    {
        // Lectura de ARCHIVO (`test -e`), nunca `isPaused()`: ése consulta la base y aquí no
        // podemos depender de ella. El centinela manda sobre el flag por diseño (#170).
        $activo = FrenoCircuito::activo();

        return [
            'centinela_puesto' => $activo,
            'detalle'          => $activo ? FrenoCircuito::detalle() : null,
        ];
    }

    private function medirSonda(): array
    {
        $ruta = '/var/www/megaisp/storage/app/' . CompuertasSondaCommand::SNAPSHOT;
        clearstatcache(true, $ruta);
        if (! is_readable($ruta)) {
            return ['disponible' => false, 'edad_seg' => null];
        }
        $j = json_decode((string) @file_get_contents($ruta), true);

        return [
            'disponible' => is_array($j),
            'edad_seg'   => is_array($j) ? max(0, time() - (int) ($j['medido_ts'] ?? 0)) : null,
        ];
    }

    /**
     * CHEQUEO bd_integra (item #228) — cuenta las tablas de `megaisp` y las compara contra el
     * último conteo BUENO. El umbral y ese conteo viven en el estado en ARCHIVO (el `$anterior`
     * que se recibe, leído de `estado.json` antes de tocar la base): si la base es el problema,
     * leer el umbral DESDE la base es el mismo error que se está corrigiendo.
     *
     * Al revés que el resto de la vigilia, aquí "no pude medir" NO es el modo mínimo legítimo:
     * es el peor caso. Un chequeo que no puede medir no reporta `ok` — sería indistinguible de
     * "medí y está bien", que es exactamente la falsa calma del 25-ago. Por eso una excepción de
     * conexión cae en `escalon => 'critico'`, igual que 0 tablas.
     */
    private function medirBdIntegra(?array $anterior): array
    {
        $conteoBueno = $anterior['bd_integra']['ultimo_conteo_bueno'] ?? null;
        $conteoBueno = is_int($conteoBueno) && $conteoBueno > 0 ? $conteoBueno : null;

        try {
            $esquema = (string) config('database.connections.mysql.database');
            $fila    = DB::selectOne(
                'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = ?',
                [$esquema]
            );
            $tablas = (int) ($fila->n ?? 0);
        } catch (\Throwable $e) {
            // GRACIA DE ARRANQUE (incidente 2026-08-28). "No pude conectarme" sigue siendo el peor
            // caso... salvo en los primeros segundos de vida del box, donde la explicación
            // abrumadoramente probable es que MySQL todavía no levanta. Sin esto, TODO reinicio del
            // servidor frenaba el circuito: el 28-ago el freno cayó 66 s después del boot, con la
            // base perfecta, y las seis terminales quedaron una hora paradas hasta que un humano lo
            // soltó a mano. Ojo con lo que NO se ablanda: la rama de "medí y las tablas cayeron"
            // (abajo) queda idéntica, así que una base vaciada de verdad frena igual que siempre.
            $uptime = GraciaDeArranque::uptimeSegundos();
            $gracia = (int) config('circuito.jarvis.vigilia.gracia_arranque_seg', 180);

            return [
                // `arranque` NO es `ok`: no frena, pero sigue saliendo como aviso en la Torre. Un
                // chequeo que no pudo medir jamás debe reportar salud (esa es la falsa calma que
                // #228 vino a matar); lo único que cambia aquí es que no dispara el freno todavía.
                'medido' => false,
                'escalon' => GraciaDeArranque::enGracia($uptime, $gracia) ? 'arranque' : 'critico',
                'tablas' => null,
                'ultimo_conteo_bueno' => $conteoBueno, 'caida_pct' => null,
                'error' => substr($e->getMessage(), 0, 200),
                'uptime_seg' => $uptime !== null ? (int) $uptime : null,
                'gracia_seg' => $gracia,
            ];
        }

        if ($tablas === 0) {
            return [
                'medido' => true, 'escalon' => 'critico', 'tablas' => 0,
                'ultimo_conteo_bueno' => $conteoBueno, 'caida_pct' => $conteoBueno ? 100.0 : null, 'error' => null,
            ];
        }

        if ($conteoBueno === null) {
            // Sin baseline previa (primera vuelta, o la última fue mala): esta medición se
            // vuelve la baseline. No hay caída que evaluar todavía.
            return [
                'medido' => true, 'escalon' => 'ok', 'tablas' => $tablas,
                'ultimo_conteo_bueno' => $tablas, 'caida_pct' => 0.0, 'error' => null,
            ];
        }

        $caida = $tablas < $conteoBueno ? round(100 * ($conteoBueno - $tablas) / $conteoBueno, 1) : 0.0;
        $escalon = $caida > 50 ? 'critico' : ($caida > 10 ? 'alerta' : 'ok');

        return [
            'medido' => true,
            'escalon' => $escalon,
            'tablas' => $tablas,
            // El conteo bueno SOLO avanza en 'ok': si se queda quieto durante una caída, la
            // próxima vuelta sigue comparando contra el piso sano, no contra uno ya erosionado.
            'ultimo_conteo_bueno' => $escalon === 'ok' ? $tablas : $conteoBueno,
            'caida_pct' => $caida,
            'error' => null,
        ];
    }

    private function motivoBdIntegra(array $bd): string
    {
        if ($bd['medido'] === false) {
            return "Freno automático (item #228): la vigilia no pudo contar las tablas de la base ({$bd['error']}).";
        }

        $antes = $bd['ultimo_conteo_bueno'] !== null ? $bd['ultimo_conteo_bueno'] : 'sin dato previo';
        $caida = $bd['caida_pct'] !== null ? ", caída {$bd['caida_pct']}%" : '';

        return "Freno automático (item #228): la base quedó en {$bd['tablas']} tabla(s) "
            . "(antes {$antes}{$caida}).";
    }

    /**
     * Lo único que necesita MySQL, y por eso va al final y entre try/catch. Si no responde, se
     * dice —no se omite ni se rellena con ceros, que es como un panel en ceros se vuelve
     * indistinguible de un sistema sano (la lección del 1038 en `roadmap_items`).
     */
    private function medirBase(array $estado): array
    {
        try {
            return [
                'responde'       => true,
                'en_progreso'    => (int) DB::table('roadmap_items')->where('estado_aprobacion', 'en_progreso')->count(),
                'requiere_irving' => (int) DB::table('roadmap_items')->where('estado_aprobacion', 'requiere_irving')->count(),
                'consultas_vivas' => (int) DB::table('roadmap_items')
                    ->whereNotNull('consulta_supervisor')->whereNull('consulta_respuesta')->count(),
            ];
        } catch (\Throwable $e) {
            return [
                'responde' => false,
                'error'    => substr($e->getMessage(), 0, 200),
            ];
        }
    }

    /**
     * Las alertas de esta entrega son OBSERVACIONES, no acciones: nombran el nivel del encargo
     * que le tocaría a cada una para que cuando se otorgue la autoridad no haya que reinterpretar
     * nada. Ninguna dispara nada hoy.
     */
    private function alertas(array $e): array
    {
        $a = [];

        $esc = $e['disco']['escalon'] ?? 'ok';
        if ($esc !== 'ok') {
            $a[] = [
                'clave' => 'disco', 'nivel' => $esc === 'pausa' ? 'alarma' : 'actua_y_avisa',
                'texto' => "Disco al {$e['disco']['uso_pct']} % — escalón «{$esc}» ({$e['disco']['libre_legible']} libres).",
            ];
        }
        if (($e['memoria']['swap_usado_pct'] ?? 0) >= 80) {
            $a[] = ['clave' => 'swap', 'nivel' => 'me_pregunta',
                'texto' => "Swap al {$e['memoria']['swap_usado_pct']} %.", ];
        }
        if (($e['logs']['mayor']['bytes'] ?? 0) > 500 * 1024 * 1024) {
            $m = $e['logs']['mayor'];
            $a[] = ['clave' => 'log_grande', 'nivel' => 'actua_y_avisa',
                'texto' => "El log de {$m['donde']} pesa {$m['legible']} ({$m['ruta']}).", ];
        }
        if (($e['procesos']['vueltas_colgadas'] ?? 0) > 0) {
            $umbral = (int) config('circuito.vuelta_colgada_seg', 3600);
            $a[] = ['clave' => 'colgadas', 'nivel' => 'actua_y_avisa',
                'texto' => "{$e['procesos']['vueltas_colgadas']} vuelta(s) viva(s) por encima de su timeout "
                    . "(> {$umbral}s): ya no van a terminar solas y el cron no las alcanza.", ];
        }
        if (count($e['procesos']['interactivos_viejos'] ?? []) > 0) {
            $n = count($e['procesos']['interactivos_viejos']);
            $a[] = ['clave' => 'claude_viejos', 'nivel' => 'me_pregunta',
                'texto' => "{$n} sesión(es) interactiva(s) de claude más viejas que el umbral. NO se tocan: pueden ser tuyas.", ];
        }
        if (count($e['registro']['pasados_timeout'] ?? []) > 0) {
            $n = count($e['registro']['pasados_timeout']);
            $a[] = ['clave' => 'timeout', 'nivel' => 'actua_y_avisa',
                'texto' => "{$n} vuelta(s) pasada(s) de su propio timeout.", ];
        }
        if (! ($e['base']['responde'] ?? false)) {
            $a[] = ['clave' => 'base', 'nivel' => 'alarma',
                'texto' => 'Estoy en modo mínimo: la base no responde. Esto es lo que sé desde archivo.', ];
        }
        $bd = $e['bd_integra'] ?? [];
        if (($bd['escalon'] ?? 'ok') === 'critico') {
            $a[] = ['clave' => 'bd_integra', 'nivel' => 'alarma',
                'texto' => 'CRÍTICO — ' . $this->motivoBdIntegra($bd) . ' Freno puesto automáticamente.', ];
        } elseif (($bd['escalon'] ?? 'ok') === 'arranque') {
            $up = $bd['uptime_seg'] ?? '?';
            $a[] = ['clave' => 'bd_integra', 'nivel' => 'me_pregunta',
                'texto' => "No pude contar las tablas y el box lleva {$up} s encendido: lo trato como "
                    . 'base todavía arrancando, NO como base perdida. No freno por esto. Si sigue '
                    . 'sin responder pasada la gracia, la próxima corrida sí frena.', ];
        } elseif (($bd['escalon'] ?? 'ok') === 'alerta') {
            $a[] = ['clave' => 'bd_integra', 'nivel' => 'me_pregunta',
                'texto' => "La base cayó a {$bd['tablas']} tabla(s) (antes {$bd['ultimo_conteo_bueno']}, "
                    . "-{$bd['caida_pct']}%). Todavía no es crítico (>50%), pero ya no es ruido.", ];
        }
        if (($e['sonda']['edad_seg'] ?? null) !== null && $e['sonda']['edad_seg'] > 180) {
            $a[] = ['clave' => 'sonda', 'nivel' => 'me_pregunta',
                'texto' => "El snapshot del SO tiene {$e['sonda']['edad_seg']} s: la Torre está midiendo con datos viejos.", ];
        }

        return $a;
    }

    private function humano(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($u) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $u[$i];
    }

    private function sh(string $cmd): ?string
    {
        $out = @shell_exec($cmd);

        return $out === null ? null : trim($out);
    }
}
