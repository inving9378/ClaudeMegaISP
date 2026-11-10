<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Extension;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Historial de llamadas y rendimiento por trabajador, desde el registro que escribe
 * Asterisk (CDR → voip_llamadas, base realtime).
 *
 * ─── POR QUÉ DEL CDR Y NO DE voip_queue_log ────────────────────────────────
 *
 * El CDR lo escribe Asterisk al colgar, sin intermediarios: está al día siempre.
 * voip_queue_log depende de un importador que corre cada 5 minutos desde el
 * scheduler (y que en un servidor sin cron no corre nunca). El tablero de
 * /voip/kpis sigue usándolo; este no.
 *
 * ─── UNA LLAMADA = UN linkedid ─────────────────────────────────────────────
 *
 * Asterisk escribe una fila por cada par de canales: la troncal que entra, la
 * cola, cada agente al que le timbró (ringall timbra a todos), el que contestó.
 * Todas comparten `linkedid`. normalizar() las junta y decide:
 *   - dirección: entra por una troncal (o por el contexto de una troncal/grupo)
 *     → entrante; sale hacia una troncal → saliente; si no → interna.
 *   - quién contestó: la fila ANSWERED cuyo destino es una extensión. Si la
 *     contestó el asistente de voz (AudioSocket) → «asistente».
 *   - a quién le timbró sin que nadie contestara: filas NO ANSWER hacia
 *     extensiones de una entrante que quedó sin atender.
 */
class HistorialLlamadasService
{
    /** Ventana para contar un ticket como «hecho después de la llamada». */
    public const MINUTOS_TICKET = 15;

    /** Tope del rango consultable (evita barrer meses de CDR de golpe). */
    public const MAX_DIAS = 93;

    /** Contextos de pruebas del asistente que no son llamadas reales. */
    private const CONTEXTOS_IGNORADOS = ['bot-voz-test'];

    // ── Lectura ──────────────────────────────────────────────────────────────

    /** ¿Hay central con su registro de llamadas? (en un servidor sin Asterisk, no) */
    public function centralDisponible(): bool
    {
        try {
            DB::connection('asterisk_rt')->getPdo();

            return DB::connection('asterisk_rt')->getSchemaBuilder()->hasTable('voip_llamadas');
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Llamadas del rango, ya juntadas por linkedid, enriquecidas (trabajador y
     * cliente) y ordenadas de la más reciente a la más vieja.
     */
    public function llamadas(Carbon $desde, Carbon $hasta): Collection
    {
        $filas = DB::connection('asterisk_rt')->table('voip_llamadas')
            ->whereBetween('start', [$desde, $hasta])
            ->whereNotIn('dcontext', self::CONTEXTOS_IGNORADOS)
            ->orderBy('start')
            ->orderBy('id')
            ->get(['id', 'uniqueid', 'linkedid', 'src', 'dst', 'dcontext', 'channel', 'dstchannel',
                   'lastapp', 'disposition', 'duration', 'billsec', 'grabacion', 'start', 'answer', 'end'])
            ->map(fn ($f) => (array) $f);

        $llamadas = $filas
            ->groupBy(fn ($f) => $f['linkedid'] ?: $f['uniqueid'])
            ->map(fn (Collection $grupo) => self::normalizar($grupo->values()->all()))
            ->values();

        return $this->enriquecer($llamadas)->sortByDesc('inicio')->values();
    }

    // ── Normalización (pura: sin BD, probada en tests/Unit) ───────────────────

    /** PJSIP/1001-0000001a → 1001 ; PJSIP/web1003-… → web1003 ; Local/… → null */
    public static function endpointDe(?string $canal): ?string
    {
        if ($canal && preg_match('#^PJSIP/(.+)-[0-9a-f]+$#i', $canal, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function esTroncal(?string $endpoint): bool
    {
        return $endpoint !== null && str_starts_with($endpoint, 'trunk_');
    }

    /**
     * @param array<int, array<string, mixed>> $filas filas CDR de un mismo linkedid, en orden
     * @return array<string, mixed>
     */
    public static function normalizar(array $filas): array
    {
        $primera = $filas[0];
        $inicio  = Carbon::parse($primera['start']);
        $fin     = collect($filas)->map(fn ($f) => Carbon::parse($f['end'] ?? $f['start']))->max();

        $entrante = false;
        $saliente = null;   // fila que salió por una troncal
        foreach ($filas as $f) {
            $origen  = self::endpointDe($f['channel']);
            $destino = self::endpointDe($f['dstchannel']);
            if (self::esTroncal($origen)
                || str_starts_with((string) $f['dcontext'], 'inbound-trunk-')
                || str_starts_with((string) $f['dcontext'], 'grupo-')) {
                $entrante = true;
            }
            if (self::esTroncal($destino) && $saliente === null) {
                $saliente = $f;
            }
        }
        $direccion = $entrante ? 'entrante' : ($saliente ? 'saliente' : 'interna');

        // Quién contestó: la primera fila contestada hacia una extensión.
        $contestada = null;
        foreach ($filas as $f) {
            $destino = self::endpointDe($f['dstchannel']);
            if ($f['disposition'] === 'ANSWERED' && $destino !== null && ! self::esTroncal($destino)) {
                $contestada = $f;
                break;
            }
        }
        $asistente = null;
        if (! $contestada && $direccion === 'entrante') {
            foreach ($filas as $f) {
                if ($f['disposition'] === 'ANSWERED' && $f['lastapp'] === 'AudioSocket') {
                    $asistente = $f;
                    break;
                }
            }
        }

        $extension = null;     // la extensión del trabajador de esta llamada
        $numero    = null;     // el otro lado (cliente o compañero)
        $estado    = 'no_atendida';
        $segundos  = 0;
        $espera    = null;

        if ($direccion === 'entrante') {
            // El número del que llama sale de la fila de la cola (o la que contestó):
            // la primera fila puede ser el otro lado de un puente, cuyo CallerID
            // Asterisk cambia al del agente cuando contesta.
            $filaCliente = $contestada
                ?? collect($filas)->first(fn ($f) => str_starts_with((string) $f['dcontext'], 'grupo-'))
                ?? $primera;
            $numero = self::numeroExterno($filaCliente['src']);
            if ($contestada) {
                $extension = self::endpointDe($contestada['dstchannel']);
                $estado    = 'contestada';
                $segundos  = (int) $contestada['billsec'];
                $espera    = max(0, Carbon::parse($contestada['start'])->diffInSeconds($inicio)
                    + (int) $contestada['duration'] - (int) $contestada['billsec']);
            } elseif ($asistente) {
                $estado   = 'asistente';
                $segundos = (int) $asistente['billsec'];
            } else {
                $espera = (int) $fin->diffInSeconds($inicio);
            }
        } elseif ($direccion === 'saliente') {
            $extension = self::endpointDe($saliente['channel']);
            $numero    = self::numeroExterno($saliente['dst']);
            $estado    = $saliente['disposition'] === 'ANSWERED' ? 'contestada' : 'sin_respuesta';
            $segundos  = (int) $saliente['billsec'];
        } else {
            // Interna: «origen → número marcado»; el trabajador es quien contestó.
            $extension = $contestada ? self::endpointDe($contestada['dstchannel']) : null;
            $numero    = $primera['dst'];
            $estado    = $contestada ? 'contestada' : 'sin_respuesta';
            $segundos  = $contestada ? (int) $contestada['billsec'] : 0;
        }
        $origen = match ($direccion) {
            'entrante' => $numero,
            'saliente' => $extension,
            default    => self::endpointDe($primera['channel']) ?? $primera['src'],
        };

        // A quién le timbró una entrante que nadie contestó.
        $timbro = [];
        if ($direccion === 'entrante' && $estado === 'no_atendida') {
            foreach ($filas as $f) {
                $destino = self::endpointDe($f['dstchannel']);
                if ($destino !== null && ! self::esTroncal($destino) && $f['disposition'] !== 'ANSWERED') {
                    $timbro[$destino] = true;
                }
            }
        }

        $conGrabacion = collect($filas)->first(fn ($f) => ! empty($f['grabacion']));

        return [
            'id'          => $primera['linkedid'] ?: $primera['uniqueid'],
            'inicio'      => $inicio->toDateTimeString(),
            'fin'         => $fin->toDateTimeString(),
            'direccion'   => $direccion,
            'origen'      => $origen,
            'numero'      => $numero,
            'estado'      => $estado,
            'extension'   => $extension,
            'segundos'    => $segundos,
            'espera'      => $espera,
            // strval: PHP vuelve entero la llave "1003" de un arreglo.
            'timbro_a'    => array_map('strval', array_keys($timbro)),
            'grabacion'   => $conGrabacion['uniqueid'] ?? null,
        ];
    }

    /** Número de teléfono tal como lo marcaría alguien en México: sin +52 / 521 ni nada que no sea dígito. */
    public static function numeroExterno(?string $numero): ?string
    {
        $d = preg_replace('/\D/', '', (string) $numero);
        if ($d === '') {
            return $numero ?: null;
        }
        if ((strlen($d) === 12 && str_starts_with($d, '52')) || (strlen($d) === 13 && str_starts_with($d, '521'))) {
            return substr($d, -10);
        }

        return $d;
    }

    // ── Enriquecimiento (trabajador y cliente) ────────────────────────────────

    private function enriquecer(Collection $llamadas): Collection
    {
        $trabajadores = $this->trabajadoresPorExtension(
            $llamadas->pluck('extension')->merge($llamadas->pluck('timbro_a')->flatten())->filter()->unique()->values()
        );
        $clientes = $this->clientesPorTelefono(
            $llamadas->where('direccion', '!=', 'interna')->pluck('numero')->filter()->unique()->values()
        );

        return $llamadas->map(function (array $l) use ($trabajadores, $clientes) {
            $t = $l['extension'] ? ($trabajadores[$l['extension']] ?? null) : null;
            $l['trabajador_id'] = $t['user_id'] ?? null;
            $l['trabajador']    = $t['nombre'] ?? null;
            $l['timbro_a_ids']  = collect($l['timbro_a'])
                ->map(fn ($ext) => $trabajadores[$ext]['user_id'] ?? null)->filter()->unique()->values()->all();

            $c = ($l['direccion'] !== 'interna' && $l['numero']) ? ($clientes[substr($l['numero'], -10)] ?? null) : null;
            $l['cliente_id'] = $c['id'] ?? null;
            $l['cliente']    = $c['nombre'] ?? null;

            return $l;
        });
    }

    /**
     * Extensión del CDR → trabajador. La gemela WebRTC (web1001) es del mismo
     * dueño que la de escritorio (1001); si no está registrada se busca la base.
     *
     * @return array<string, array{user_id: int|null, nombre: string}>
     */
    private function trabajadoresPorExtension(Collection $endpoints): array
    {
        if ($endpoints->isEmpty()) {
            return [];
        }
        $numeros = $endpoints->merge($endpoints->map(fn ($e) => preg_replace('/^web/', '', $e)))->unique()->values();
        $ext = Extension::query()
            ->leftJoin('users as u', 'u.id', '=', 'voip_extensiones.user_id')
            ->whereIn('voip_extensiones.numero', $numeros)
            ->get(['voip_extensiones.numero', 'voip_extensiones.nombre', 'voip_extensiones.user_id',
                   'u.name as u_name', 'u.father_last_name as u_apellido'])
            ->keyBy('numero');

        $mapa = [];
        foreach ($endpoints as $e) {
            $fila = $ext[$e] ?? $ext[preg_replace('/^web/', '', $e)] ?? null;
            $nombre = $fila && $fila->user_id
                ? trim("{$fila->u_name} {$fila->u_apellido}")
                : ($fila->nombre ?? null);
            $mapa[$e] = [
                'user_id' => $fila->user_id ?? null,
                'nombre'  => ($nombre ?: 'Extensión') . ' (' . preg_replace('/^web/', '', $e) . ')',
            ];
        }

        return $mapa;
    }

    /**
     * Teléfono → cliente, con la misma regla que identifica al que llama en el
     * mini-teléfono (IABotCustomerService): los últimos 10 dígitos de phone,
     * phone2 o phone3. Una sola consulta para todo el lote.
     *
     * @return array<string, array{id: int, nombre: string}> por últimos 10 dígitos
     */
    private function clientesPorTelefono(Collection $numeros): array
    {
        $ultimos = $numeros->map(fn ($n) => substr(preg_replace('/\D/', '', $n), -10))
            ->filter(fn ($n) => strlen($n) >= 7)->unique()->values()->all();
        if (! $ultimos) {
            return [];
        }

        $filas = collect();
        foreach (['phone', 'phone2', 'phone3'] as $col) {
            $expr = "RIGHT(REGEXP_REPLACE(cmi.{$col}, '[^0-9]', ''), 10)";
            $filas = $filas->merge(DB::table('client_main_information as cmi')
                ->join('clients as c', 'c.id', '=', 'cmi.client_id')
                ->whereNull('c.deleted_at')
                ->whereIn(DB::raw($expr), $ultimos)
                ->get(['c.id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name', DB::raw("{$expr} as tel")]));
        }

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[$f->tel] ??= [
                'id'     => (int) $f->id,
                'nombre' => trim("{$f->name} {$f->father_last_name} {$f->mother_last_name}") ?: 'Cliente',
            ];
        }

        return $mapa;
    }

    // ── Agregados del tablero ─────────────────────────────────────────────────

    public function resumen(Collection $llamadas): array
    {
        $entrantes   = $llamadas->where('direccion', 'entrante');
        $contestadas = $entrantes->where('estado', 'contestada');
        $noAtendidas = $entrantes->where('estado', 'no_atendida');
        $total       = $entrantes->count();

        return [
            'entrantes'      => $total,
            'contestadas'    => $contestadas->count(),
            'asistente'      => $entrantes->where('estado', 'asistente')->count(),
            'no_atendidas'   => $noAtendidas->count(),
            'abandono_pct'   => $total ? round($noAtendidas->count() * 100 / $total, 1) : 0,
            'espera_prom'    => self::promedio($contestadas->pluck('espera')),
            'duracion_prom'  => self::promedio($contestadas->pluck('segundos')),
            'salientes'      => $llamadas->where('direccion', 'saliente')->count(),
            'internas'       => $llamadas->where('direccion', 'interna')->count(),
            'con_grabacion'  => $llamadas->whereNotNull('grabacion')->count(),
        ];
    }

    /** Entrantes por día del rango (también los días sin llamadas, en cero). */
    public function porDia(Collection $llamadas, Carbon $desde, Carbon $hasta): array
    {
        $porFecha = $llamadas->where('direccion', 'entrante')
            ->groupBy(fn ($l) => substr($l['inicio'], 0, 10));

        $dias = [];
        for ($d = $desde->copy()->startOfDay(); $d->lte($hasta); $d->addDay()) {
            $grupo = $porFecha[$d->toDateString()] ?? collect();
            $dias[] = [
                'fecha'        => $d->toDateString(),
                'contestadas'  => $grupo->where('estado', 'contestada')->count(),
                'asistente'    => $grupo->where('estado', 'asistente')->count(),
                'no_atendidas' => $grupo->where('estado', 'no_atendida')->count(),
            ];
        }

        return $dias;
    }

    /**
     * Rendimiento por trabajador: llamadas de clientes (entrantes y salientes; las
     * internas entre compañeros no cuentan) y si hizo ticket después de atender.
     */
    public function porTrabajador(Collection $llamadas): array
    {
        $externas = $llamadas->whereIn('direccion', ['entrante', 'saliente']);
        $ids = $externas->pluck('trabajador_id')
            ->merge($externas->pluck('timbro_a_ids')->flatten())
            ->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $atendidas = $externas->where('direccion', 'entrante')->where('estado', 'contestada');
        $tickets   = $this->ticketsDe($ids, $atendidas);
        $conTicket = [];   // id de llamada => true
        foreach ($tickets as $id => $creados) {
            $suyas = $atendidas->where('trabajador_id', $id)->map(fn ($l) => ['id' => $l['id'], 'fin' => $l['fin']])->all();
            foreach (self::emparejarTickets($suyas, $creados) as $llamadaId) {
                $conTicket[$llamadaId] = true;
            }
        }
        $nombres   = DB::table('users')->whereIn('id', $ids)->get(['id', 'name', 'father_last_name'])->keyBy('id');

        return $ids->map(function ($id) use ($externas, $atendidas, $conTicket, $nombres) {
            $suyas       = $atendidas->where('trabajador_id', $id);
            $salientes   = $externas->where('direccion', 'saliente')->where('trabajador_id', $id);
            $hablado     = $suyas->sum('segundos') + $salientes->sum('segundos');
            $conTicketN  = $suyas->filter(fn ($l) => isset($conTicket[$l['id']]))->count();
            $sinAtender  = $externas->where('estado', 'no_atendida')
                ->filter(fn ($l) => in_array($id, $l['timbro_a_ids'], true))->count();
            $n = $nombres[$id] ?? null;

            return [
                'user_id'        => $id,
                'nombre'         => $n ? trim("{$n->name} {$n->father_last_name}") : "Usuario {$id}",
                'contestadas'    => $suyas->count(),
                'no_contestadas' => $sinAtender,
                'salientes'      => $salientes->count(),
                'segundos_total' => $hablado,
                'segundos_prom'  => self::promedio($suyas->pluck('segundos')),
                'con_ticket'     => $conTicketN,
                'ticket_pct'     => $suyas->count() ? round($conTicketN * 100 / $suyas->count()) : null,
            ];
        })->sortByDesc('contestadas')->values()->all();
    }

    /** @return array<int, array<int, string>> created_at de los tickets que levantó cada trabajador */
    private function ticketsDe(Collection $ids, Collection $atendidas): array
    {
        if ($atendidas->isEmpty()) {
            return [];
        }
        $desde = Carbon::parse($atendidas->min('fin'));
        $hasta = Carbon::parse($atendidas->max('fin'))->addMinutes(self::MINUTOS_TICKET);

        return DB::table('tickets')
            ->whereIn('reporter_id', $ids)
            ->whereBetween('created_at', [$desde, $hasta])
            ->get(['reporter_id', 'created_at'])
            ->groupBy('reporter_id')
            ->map(fn ($g) => $g->pluck('created_at')->all())
            ->all();
    }

    /**
     * Cada ticket cuenta para UNA sola llamada: se recorren las llamadas en el orden
     * en que terminaron y cada una toma el primer ticket libre creado entre su fin y
     * MINUTOS_TICKET después.
     *
     * @param array<int, array{id: string, fin: string}> $llamadas
     * @param array<int, string> $creados created_at de los tickets del trabajador
     * @return array<int, string> ids de las llamadas que sí tienen ticket
     */
    public static function emparejarTickets(array $llamadas, array $creados): array
    {
        usort($llamadas, fn ($a, $b) => strcmp($a['fin'], $b['fin']));
        $libres = array_map(fn ($c) => Carbon::parse($c), $creados);
        usort($libres, fn ($a, $b) => $a <=> $b);

        $con = [];
        foreach ($llamadas as $l) {
            $fin    = Carbon::parse($l['fin']);
            $limite = $fin->copy()->addMinutes(self::MINUTOS_TICKET);
            foreach ($libres as $i => $t) {
                if ($t->gte($fin) && $t->lte($limite)) {
                    $con[] = $l['id'];
                    unset($libres[$i]);
                    break;
                }
            }
        }

        return $con;
    }

    private static function promedio(Collection $valores): ?int
    {
        $v = $valores->filter(fn ($x) => $x !== null);

        return $v->isEmpty() ? null : (int) round($v->avg());
    }
}
