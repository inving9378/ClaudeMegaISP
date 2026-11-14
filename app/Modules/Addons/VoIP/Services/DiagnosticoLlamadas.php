<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\Troncal;
use App\Modules\Core\Voice\AmiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * «Diagnóstico» de VoIP → Troncales: lo que hace falta para saber por qué no entra o no sale
 * una llamada, en texto para copiar y pegar. Existe porque en producción no hay acceso a la
 * consola de Asterisk: todo lo que antes se miraba con `asterisk -rx` se ve aquí.
 *
 * Solo lee. Lo único que cambia es encender y apagar la grabación del tráfico SIP de Asterisk
 * (`pjsip set history`), que se apaga al ver el resultado y nunca dura más de 10 minutos.
 */
class DiagnosticoLlamadas
{
    private const GRABANDO = 'voip.diagnostico.sip_desde';
    private const MAX_LLAMADAS_SIP = 8;

    public function __construct(
        private AmiClient $ami,
        private AsteriskProvisioningService $prov,
    ) {}

    /** Salida de un comando de la consola de Asterisk (por AMI), sin los encabezados. */
    public function cli(string $comando): string
    {
        try {
            $crudo = $this->ami->send('Command', ['Command' => $comando]);
        } catch (\Throwable $e) {
            return "(Asterisk no respondió: {$e->getMessage()})";
        }
        $salida = [];
        foreach (preg_split('/\r?\n/', $crudo) as $linea) {
            if (str_starts_with($linea, 'Output: ')) {
                $salida[] = substr($linea, 8);
            }
        }

        return implode("\n", $salida);
    }

    public function grabar(): void
    {
        $this->cli('pjsip set history clear');
        $this->cli('pjsip set history on');
        Cache::put(self::GRABANDO, now()->format('H:i:s'), now()->addMinutes(10));
    }

    /** Apaga la grabación; lo grabado se queda para leerlo hasta la siguiente. */
    public function detener(): void
    {
        $this->cli('pjsip set history off');
        Cache::forget(self::GRABANDO);
    }

    public function grabandoDesde(): ?string
    {
        $desde = Cache::get(self::GRABANDO);
        if ($desde === null) {
            // Pasaron los 10 minutos: que no se quede grabando para siempre.
            $this->cli('pjsip set history off');
        }

        return $desde;
    }

    public function informe(): string
    {
        $s = [];
        $version = rescue(fn () => DB::table('releases')->orderByDesc('id')->value('version'), null, false);
        $s[] = '== Diagnóstico de llamadas · ' . now()->format('Y-m-d H:i:s') . ' · MegaISP ' . ($version ?: '?');
        $s[] = trim(strtok($this->cli('core show version'), "\n") ?: '(sin versión de Asterisk)');
        $s[] = 'Ahora: ' . trim(preg_replace('/\s+/', ' ', $this->cli('core show channels count')));

        foreach (['troncales', 'plan', 'telefonos', 'registroDeLlamadas', 'traficoSip'] as $seccion) {
            $s = array_merge($s, [''], $this->{$seccion}());
        }

        return implode("\n", $s) . "\n";
    }

    private function troncales(): array
    {
        $s = ['== Troncales'];
        $rt = DB::connection('asterisk_rt');
        $eps = $rt->table('ps_endpoints')->get()->keyBy('id');
        $ips = $rt->table('ps_endpoint_id_ips')->get()->keyBy('endpoint');

        foreach (Troncal::orderBy('id')->get() as $t) {
            $id = $t->endpointId();
            $ep = $eps[$id] ?? null;
            $s[] = sprintf('#%d «%s» %s · %s:%s · %s · dirección %s%s · grupo de entrada %s',
                $t->id, $t->nombre, $t->activo ? 'ACTIVA' : 'apagada', $t->host, $t->puerto ?: '-', $t->tipo,
                $t->direccion, $t->proposito ? " ({$t->proposito})" : '', $t->grupo_entrante_id ?: '—');
            if (! $t->activo) {
                $s[] = '    en Asterisk: ' . ($ep ? 'SÍ (y debería estar quitada)' : 'no (correcto, está apagada)');
                continue;
            }
            $reg = $t->tipo === 'registro' ? (rescue(fn () => $this->prov->verificarRegistro($t), [], false)['status'] ?? '?') : 'por IP';
            $s[] = '    registro con el proveedor: ' . $reg . ' · en Asterisk: ' . ($ep ? 'sí' : 'NO');
            if ($ep) {
                $actual = (string) ($ep->context ?? '');
                $s[] = '    las llamadas que entran van a: ' . ($actual !== '' ? $actual : '(vacío)')
                    . ($actual === $t->contextoEntrante() ? ' ✔' : ' ✘ deberían ir a ' . $t->contextoEntrante());
                $s[] = '    IPs que reconoce como esta troncal: ' . ($ips[$id]->match ?? '(ninguna)')
                    . ' · se identifica por: ' . ($ep->identify_by ?? '?');
            }
        }

        return $s;
    }

    private function plan(): array
    {
        $s = ['== Plan de marcado'];
        $salida = $this->cli('dialplan show _[2-9]XXXXXXXXX@from-internal');
        $s[] = preg_match('/Dial\(([^)]*)\)/', $salida, $m)
            ? "Llamadas a la calle (10 dígitos): Dial({$m[1]}) ✔"
            : 'Llamadas a la calle (10 dígitos): NO HAY REGLA ✘';

        foreach (Troncal::whereNotNull('grupo_entrante_id')->where('activo', true)->orderBy('id')->get() as $t) {
            $entrada = $this->cli("dialplan show s@inbound-trunk-{$t->id}");
            $s[] = "Entrada por «{$t->nombre}» (inbound-trunk-{$t->id}): "
                . (preg_match('/Goto\((grupo-\d+)/', $entrada, $m) ? "va a {$m[1]} ✔" : 'NO EXISTE ✘');
            $grupo = $this->cli("dialplan show s@grupo-{$t->grupo_entrante_id}");
            preg_match_all('/\d+\.\s+([A-Za-z]+)\(/', $grupo, $apps);
            $s[] = "    grupo-{$t->grupo_entrante_id}: " . ($apps[1] ? implode(' → ', $apps[1]) : 'NO EXISTE ✘');
        }

        return $s;
    }

    private function telefonos(): array
    {
        [$conectados, $equipos] = rescue(fn () => $this->prov->conexiones(), [[], []], false);
        $s = ['== Teléfonos conectados a esta central: ' . count($conectados)];
        foreach ($conectados as $ep) {
            $s[] = "    {$ep}" . (! empty($equipos[$ep]) ? " · {$equipos[$ep]}" : '');
        }
        foreach (GrupoTimbrado::with('extensiones')->where('activo', true)->orderBy('id')->get() as $g) {
            $miembros = $g->extensiones->pluck('numero');
            $en = $miembros->filter(fn ($n) => in_array($n, $conectados, true));
            $s[] = "Grupo «{$g->nombre}» (grupo-{$g->id}" . ($g->es_cola ? ', cola' : '') . "): conectados {$en->count()} de {$miembros->count()}"
                . ($en->isNotEmpty() ? ' → ' . $en->implode(', ') : ' ✘ nadie puede contestar');
        }

        return $s;
    }

    private function registroDeLlamadas(): array
    {
        $estado = $this->cli('cdr show status');
        $s = ['== Registro de llamadas · CDR ' . (preg_match('/Logging:\s+(\w+)/', $estado, $m) ? $m[1] : '?')
            . (str_contains($estado, 'Adaptive ODBC') ? ' (a la base)' : ' ✘ sin el respaldo a la base')];
        try {
            $filas = DB::connection('asterisk_rt')->table('voip_llamadas')->orderByDesc('id')->limit(12)
                ->get(['start', 'src', 'dst', 'dcontext', 'channel', 'dstchannel', 'lastapp', 'disposition']);
        } catch (\Throwable $e) {
            return array_merge($s, ["    no se pudo leer voip_llamadas: {$e->getMessage()}"]);
        }
        if ($filas->isEmpty()) {
            $s[] = '    (ninguna llamada registrada)';
        }
        foreach ($filas as $f) {
            $s[] = sprintf('    %s  %s → %s  [%s]  %s  %s  %s → %s', $f->start, $f->src, $f->dst, $f->dcontext,
                $f->lastapp, $f->disposition, $f->channel, $f->dstchannel ?: '-');
        }

        return $s;
    }

    private function traficoSip(): array
    {
        $desde = $this->grabandoDesde();
        $s = ['== Tráfico SIP de llamadas' . ($desde ? " (grabando desde las {$desde})" : ' (lo último grabado)')];

        $invites = $this->entradas('where sip.msg.request.method = INVITE');
        if (! $invites) {
            $s[] = $desde
                ? '    Aún no hay llamadas grabadas: haz la llamada de prueba y luego «Ver resultado».'
                : '    No hay nada grabado. Pulsa «Grabar llamada de prueba», haz la llamada y luego «Ver resultado».';

            return $s;
        }

        $vistas = [];
        foreach (array_reverse(array_keys($invites)) as $n) {
            $detalle = $this->cli("pjsip show history entry {$n}");
            if (! preg_match('/^Call-ID:\s*(\S+)/mi', $detalle, $c) || isset($vistas[$c[1]])) {
                continue;
            }
            $vistas[$c[1]] = true;
            if (count($vistas) > self::MAX_LLAMADAS_SIP) {
                break;
            }
            $de = preg_match('/^From:\s*(.*)$/mi', $detalle, $f) ? 'de ' . trim($f[1]) : '';
            $a  = preg_match('/^To:\s*(.*)$/mi', $detalle, $to) ? 'a ' . trim($to[1]) : '';
            $s[] = '-- Llamada ' . count($vistas) . ' · ' . trim("{$de} {$a}");

            foreach ($this->entradas('where sip.msg.call-id = ' . $c[1]) as $num => $linea) {
                $s[] = '    ' . $linea;
                // De las respuestas de error se trae el motivo que da el otro lado (401/407
                // son el reto de contraseña normal, no un error).
                if (preg_match('/SIP\/2\.0 ([3-6]\d\d)/', $linea, $cod) && ! in_array($cod[1], ['401', '407'], true)) {
                    foreach (preg_split('/\r?\n/', $this->cli("pjsip show history entry {$num}")) as $h) {
                        if (preg_match('/^(Reason|Warning|Server|User-Agent):/i', $h)) {
                            $s[] = '        ' . trim($h);
                        }
                    }
                }
            }
        }

        return $s;
    }

    /**
     * Mensajes del historial SIP como [número => "<== dirección  primera línea"]. Asterisk lo
     * da como lista, pero cuando el filtro deja UN solo mensaje lo da completo: se leen los dos.
     *
     * @return array<int, string>
     */
    private function entradas(string $filtro = ''): array
    {
        $salida = $this->cli(trim("pjsip show history {$filtro}"));
        $lista = [];
        if (preg_match_all('/^(\d{5})\s+\d+\s+\*?\s*(<==|==>)\s+(\S+)\s+(.*)$/m', $salida, $m, PREG_SET_ORDER)) {
            foreach ($m as $e) {
                $lista[(int) $e[1]] = "{$e[2]} {$e[3]}  " . trim($e[4]);
            }
        }
        if (preg_match_all('/^<--- History Entry (\d+) (Received from|Sent to) (\S+) at \d+ --->\s*\R(.*)$/m', $salida, $m, PREG_SET_ORDER)) {
            foreach ($m as $e) {
                $lista[(int) $e[1]] = ($e[2] === 'Sent to' ? '==>' : '<==') . " {$e[3]}  " . trim($e[4]);
            }
        }
        ksort($lista);

        return $lista;
    }
}
