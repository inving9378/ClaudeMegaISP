<?php

namespace App\Modules\Addons\CobranzaBlaster\Jobs;

use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamadaEvento;
use App\Modules\Addons\CobranzaBlaster\Models\VoipConfiguracion;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaCampanaService;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaTtsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Una pasada del blaster por una campaña activa (el cron cobranza:blast-activas la
 * dispara cada 5 minutos): marca a los que les toca, hasta el tope de canales.
 */
class BlastCampanaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    /**
     * Una llamada «marcando» sin noticias de Asterisk en este tiempo se perdió (el
     * listener estuvo caído): se trata como no contestada para que no se quede ahí ni
     * ocupe un canal para siempre. El Originate se rinde a los 30 s.
     */
    public const MINUTOS_SIN_NOTICIAS = 10;

    /** Si la voz falla tantas veces seguidas en una pasada, se deja para la siguiente. */
    private const FALLAS_DE_VOZ_SEGUIDAS = 3;

    public function __construct(public int $campanaId)
    {
        $this->onQueue('cobranza');
    }

    /**
     * Un solo pase del blaster a la vez, de cualquier campaña (25-nov, segunda revisión).
     * Dos pases juntos (el de la activación y el del cron, o dos campañas en los dos
     * workers) tomaban las mismas llamadas y le marcaban dos veces al mismo cliente, y
     * se saltaban el tope de canales de la troncal. El que no consigue el turno se
     * descarta: el cron vuelve a mandar uno en 5 minutos.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('blaster'))->dontRelease()->expireAfter(300)];
    }

    public function handle(AmiConnectionService $ami, CobranzaTtsService $tts): void
    {
        $campana = CobranzaCampana::find($this->campanaId);

        if (!$campana || $campana->estado !== 'activa') {
            return;
        }

        // Fechas de la campaña (25-nov: antes no se revisaban). Pasada la fecha fin, termina.
        $fechas = self::enFechas($campana->fecha_inicio, $campana->fecha_fin, now());
        if ($fechas === 'termino') {
            $campana->update(['estado' => 'completada']);
            Log::info("BlastCampanaJob: campaña #{$campana->id} completada (pasó su fecha fin).");
            return;
        }
        if ($fechas === 'no_empieza') {
            return;
        }

        $ahora = now()->format('H:i:s');
        if ($ahora < $campana->hora_inicio || $ahora > $campana->hora_fin) {
            return;
        }

        $this->recuperarSinNoticias($campana);

        // Canales simultáneos: los de la campaña o, si no se fijaron, el tope por omisión
        // (antes salían hasta 50 a la vez y la troncal rechazaba las que no cabían). Se
        // cuentan las llamadas en curso de TODAS las campañas: la troncal es una sola.
        $tope    = (int) ($campana->max_canales_simultaneos ?: config('cobranza.canales_por_defecto', 10));
        $enCurso = CobranzaLlamada::where('estado', 'marcando')->count();
        $lote    = max(0, $tope - $enCurso);
        if ($lote === 0) {
            return;
        }

        $llamadas = CobranzaLlamada::where('campana_id', $this->campanaId)
            ->where('estado', 'pendiente')
            ->where(fn ($q) => $q->whereNull('proximo_intento_at')
                ->orWhere('proximo_intento_at', '<=', now()))
            ->with('client.client_main_information')
            ->orderBy('id')
            ->limit($lote)
            ->get();

        if ($llamadas->isEmpty()) {
            // Nadie esperando turno ni en curso: la campaña terminó.
            if (!CobranzaLlamada::where('campana_id', $this->campanaId)->whereIn('estado', ['pendiente', 'marcando'])->exists()) {
                $campana->update(['estado' => 'completada']);
                Log::info("BlastCampanaJob: campaña #{$campana->id} completada (ya no quedan llamadas).");
            }
            return;
        }

        // Todas las campañas salen por la troncal del módulo (Config. VoIP), sin elegir
        // otra (pedido de Irving, 23-nov). Si no está aplicada, no se marca ni se gastan
        // intentos: las llamadas esperan a que se configure.
        $config = VoipConfiguracion::first();
        if ($config?->estado !== 'activa') {
            Log::warning("BlastCampanaJob: la troncal de Campañas/Blaster no está aplicada (Config. VoIP); campaña #{$this->campanaId} en espera.");
            return;
        }
        if (trim((string) $config->callerid_nombre) === '' && trim((string) $config->callerid_numero) === '') {
            Log::warning("BlastCampanaJob: falta el identificador de llamada (Config. VoIP); campaña #{$this->campanaId} en espera.");
            return;
        }

        // Lo que se lee es la nota de la campaña, con su voz, velocidad y tono (pedido de
        // Irving, 24-nov). Con «solo los que deben» (27-nov; antes era el tipo cobranza) la
        // nota lleva {nombre}, {saldo}, {fecha} y {numero_cliente} y sale un audio por llamada;
        // sin eso el texto es el mismo para todos y se genera una sola vez. Las campañas de antes (sin voz
        // elegida) siguen con su mensaje de siempre: la plantilla o audio_mensaje.
        $voz  = $campana->opcionesVoz();
        $nota = $campana->hablaLaNota() ? $campana->notas : null;

        // Sin voz no se marca (25-nov): antes la llamada salía igual y el cliente
        // contestaba a una llamada muda.
        $audioComun = null;
        if (! $campana->solo_deudores) {
            $audioComun = $tts->generateAudioCached('campana_' . $campana->id, (string) ($nota ?? $campana->audio_mensaje), $voz);
            if ($audioComun === '') {
                Log::error("BlastCampanaJob: no se pudo generar la voz de la campaña #{$campana->id}; no se marca a nadie hasta que se pueda.");
                return;
            }
        }

        $tts->limpiarAudiosViejos();

        if (!$ami->connect(false)) {
            Log::error("BlastCampanaJob: no se pudo conectar al AMI para campaña #{$this->campanaId}");
            return;
        }

        $fallasDeVoz = 0;

        foreach ($llamadas as $llamada) {
            if (!$ami->isConnected()) {
                Log::error("BlastCampanaJob: se perdió la conexión con el AMI a media pasada (campaña #{$campana->id}); las que faltan esperan a la siguiente.");
                break;
            }

            $cmi      = $llamada->client?->client_main_information;
            $telefono = CobranzaLlamada::normalizarTelefono($llamada->telefono);

            if (!$cmi || $telefono === null) {
                $llamada->update(['estado' => 'excluida', 'proximo_intento_at' => null]);
                continue;
            }

            if ($campana->solo_deudores) {
                // El que ya pagó no recibe la llamada (25-nov: antes nada marcaba «pagada»
                // y se le seguía llamando). El saldo que se dice es el de hoy.
                $adeudo = CobranzaCampanaService::adeudoVencido((int) $llamada->client_id, (int) $campana->dias_vencimiento);
                if ($adeudo <= 0) {
                    $llamada->update(['estado' => 'pagada', 'proximo_intento_at' => null]);
                    continue;
                }
                if (abs($adeudo - (float) $llamada->monto_vencido) >= 0.01) {
                    $llamada->update(['monto_vencido' => $adeudo]);
                }

                $audioPath = $tts->generateAudio(
                    $llamada->id,
                    $tts->textoCobranza(
                        $nota,
                        $cmi->name,
                        $adeudo,
                        CobranzaTtsService::fechaParaVoz($campana->fecha_fin),
                        (string) $llamada->client_id
                    ),
                    $voz
                );

                if ($audioPath === '') {
                    // Sin gastar intento: se vuelve a probar en un rato.
                    $llamada->update(['proximo_intento_at' => now()->addMinutes(15)]);
                    if (++$fallasDeVoz >= self::FALLAS_DE_VOZ_SEGUIDAS) {
                        Log::error("BlastCampanaJob: la voz falló {$fallasDeVoz} veces seguidas en la campaña #{$campana->id}; se deja para la siguiente pasada.");
                        break;
                    }
                    continue;
                }
                $fallasDeVoz = 0;
            } else {
                $audioPath = $audioComun;
            }

            // Primero la fila y después el Originate: si Asterisk avisa (ocupado, rechazada)
            // antes de que regrese la respuesta, el listener ya encuentra la llamada. Se
            // aparta con un UPDATE condicionado: si otra cosa ya la tomó, no se marca.
            $uniqueid       = 'cob-' . $llamada->id . '-' . time();
            $previos        = (int) $llamada->intentos;
            $nuevosIntentos = $previos + 1;
            $tomada = CobranzaLlamada::whereKey($llamada->id)->where('estado', 'pendiente')->update([
                'estado'             => 'marcando',
                'telefono'           => $telefono,
                'ultimo_intento_at'  => now(),
                'intentos'           => $nuevosIntentos,
                'ami_uniqueid'       => $uniqueid,
                'proximo_intento_at' => null,
            ]);
            if ($tomada === 0) {
                continue;
            }

            try {
                $result = $ami->originate($telefono, $audioPath, $llamada->id, $cmi->name, $uniqueid);
            } catch (\Throwable $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }

            // Se cayó la conexión: la llamada no salió. Vuelve a la fila sin gastar intento.
            if (!$result['success'] && !$ami->isConnected()) {
                CobranzaLlamada::whereKey($llamada->id)->update([
                    'estado' => 'pendiente', 'intentos' => $previos, 'ami_uniqueid' => null, 'proximo_intento_at' => null,
                ]);
                Log::error("BlastCampanaJob: se perdió la conexión con el AMI al marcar la llamada #{$llamada->id}; vuelve a la fila.");
                break;
            }

            $llamada->refresh();
            if ($result['success']) {
                $llamada->update(['ami_channel' => $result['channel'] ?? null]);
            } else {
                $puedeReintentar = $nuevosIntentos < $campana->max_intentos;
                $llamada->update([
                    'estado'             => $puedeReintentar ? 'pendiente' : 'fallida',
                    'ami_channel'        => $result['channel'] ?? null,
                    'proximo_intento_at' => $puedeReintentar ? now()->addMinutes($campana->minutos_entre_intentos) : null,
                ]);
            }

            CobranzaLlamadaEvento::create([
                'llamada_id'  => $llamada->id,
                'evento'      => 'Originate',
                'payload'     => $result,
                'ocurrido_at' => now(),
            ]);
        }

        $ami->disconnect();
    }

    /**
     * ¿Toca marcar hoy? 'si', 'no_empieza' (antes de la fecha inicio) o 'termino' (pasó la
     * fecha fin, que cuenta completa). Pura: probada en tests/Unit.
     */
    public static function enFechas(?Carbon $inicio, ?Carbon $fin, Carbon $hoy): string
    {
        $dia = $hoy->toDateString();

        if ($fin && $dia > $fin->toDateString()) {
            return 'termino';
        }
        if ($inicio && $dia < $inicio->toDateString()) {
            return 'no_empieza';
        }

        return 'si';
    }

    private function recuperarSinNoticias(CobranzaCampana $campana): void
    {
        $perdidas = CobranzaLlamada::where('campana_id', $campana->id)
            ->where('estado', 'marcando')
            ->where('ultimo_intento_at', '<', now()->subMinutes(self::MINUTOS_SIN_NOTICIAS))
            ->get();

        foreach ($perdidas as $llamada) {
            // Antes de volver a marcar, lo que dice el CDR de Asterisk: si se contestó (y el
            // listener no se enteró, p. ej. mientras se reiniciaba), queda contestada.
            $evento = self::eventoSegunCdr(self::disposicionesCdr((string) $llamada->ami_uniqueid));
            $cambio = ProcessCallResultJob::siguienteEstado(
                $evento, 'marcando', (int) $llamada->intentos, (int) $campana->max_intentos
            );
            $llamada->update([
                'estado'             => $cambio['estado'],
                'proximo_intento_at' => $cambio['reintentar'] ? now()->addMinutes($campana->minutos_entre_intentos) : null,
            ]);
            CobranzaLlamadaEvento::create([
                'llamada_id'  => $llamada->id,
                'evento'      => $evento,
                'payload'     => ['motivo' => 'sin noticias de Asterisk; se tomó del CDR', 'minutos' => self::MINUTOS_SIN_NOTICIAS],
                'ocurrido_at' => now(),
            ]);
        }

        if ($perdidas->isNotEmpty()) {
            Log::warning("BlastCampanaJob: {$perdidas->count()} llamada(s) de la campaña #{$campana->id} sin noticias de Asterisk en "
                . self::MINUTOS_SIN_NOTICIAS . ' min; se resolvieron con el CDR. ¿Está corriendo el servicio megaisp-campanas-ami?');
        }
    }

    /** Disposiciones del CDR de esa llamada ([] si no hay CDR o no se pudo leer). */
    private static function disposicionesCdr(string $uniqueid): array
    {
        if ($uniqueid === '') {
            return [];
        }
        try {
            return DB::connection('asterisk_rt')->table('voip_llamadas')
                ->where('uniqueid', $uniqueid)
                ->pluck('disposition')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Evento que corresponde a lo que dejó el CDR. Sin CDR se toma como no contestada.
     * Pura: probada en tests/Unit.
     */
    public static function eventoSegunCdr(array $disposiciones): string
    {
        $d = array_map(fn ($x) => strtoupper(trim((string) $x)), $disposiciones);

        return match (true) {
            in_array('ANSWERED', $d, true)                       => 'ANSWER',
            in_array('BUSY', $d, true)                           => 'BUSY',
            in_array('FAILED', $d, true), in_array('CONGESTION', $d, true) => 'FAILED',
            default                                              => 'NOANSWER',
        };
    }
}
