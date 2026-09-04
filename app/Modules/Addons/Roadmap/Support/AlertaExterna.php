<?php

namespace App\Modules\Addons\Roadmap\Support;

use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use Illuminate\Support\Facades\Log;

/**
 * CANAL DE ALERTA FUERA DE LA TORRE (#707, sub-item de #208, parte 3/3).
 *
 * `circuito:jarvis-vigilar` YA mide y guarda hallazgos en archivo; lo que faltaba es que una
 * alarma de verdad SALGA del box aunque nadie esté mirando la Torre — ese es el objetivo real
 * del vigilante on-box (#208): detectar incluso si la propia Torre está caída. Reusa el gateway
 * WhatsApp ÚNICO ya designado (`EvolutionApiService`, ver CLAUDE.md §"SERVICIOS COMPARTIDOS
 * ÚNICOS") — nunca se monta un cliente HTTP propio.
 *
 * SOLO AVISA. No corrige, no aísla, no pausa nada — esa frontera la puso el propio item #707 a
 * propósito ("Fuera de alcance: el vigilante NO corrige nada, solo avisa").
 *
 * Apagado por default: sin `jarvis.vigilia.alerta_externa.enabled=true` Y un `destino` no vacío
 * en `config/circuito.php`, este código nunca manda un mensaje (mismo patrón de kill-switch que
 * `PAYMENTS_AUTO_APPLY_ENABLED`/`DOMICILIACION_COBRO_LIVE_ENABLED`).
 *
 * DEDUP/COOLDOWN EN ARCHIVO, no BD (mismo principio que el resto de la vigilia: si la base es el
 * problema, no puede ser también el árbitro de si ya se avisó). Sin esto, un hallazgo que sigue
 * activo cada minuto (el cron de la vigilia) mandaría un WhatsApp por minuto — el propio revisor
 * del #208 pidió ese margen explícitamente.
 */
class AlertaExterna
{
    private const ARCHIVO_ENVIADAS = 'alertas-externas-enviadas.json';

    /** Solo el nivel más alto de la escala de `JarvisVigilarCommand::alertas()` dispara el canal externo. */
    private const NIVEL_DISPARA = 'alarma';

    /** @param array<int,array{clave?:string,nivel?:string,texto?:string}> $alertas */
    public static function avisar(array $alertas): void
    {
        if (! (bool) config('circuito.jarvis.vigilia.alerta_externa.enabled', false)) {
            return;
        }
        $destino = trim((string) config('circuito.jarvis.vigilia.alerta_externa.destino', ''));
        if ($destino === '') {
            return;
        }

        $altas = array_values(array_filter($alertas, fn ($a) => ($a['nivel'] ?? null) === self::NIVEL_DISPARA));
        if ($altas === []) {
            return;
        }

        $cooldown = max(60, (int) config('circuito.jarvis.vigilia.alerta_externa.cooldown_seg', 1800));
        $enviadas = self::leerEnviadas();
        $ahora    = time();
        $cambio   = false;

        foreach ($altas as $a) {
            $clave  = (string) ($a['clave'] ?? 'desconocido');
            $ultimo = (int) ($enviadas[$clave] ?? 0);
            if ($ahora - $ultimo < $cooldown) {
                continue; // dedup: ya se avisó de esto hace menos del cooldown
            }

            if (self::enviar($destino, $a)) {
                $enviadas[$clave] = $ahora;
                $cambio            = true;
            }
        }

        if ($cambio) {
            self::guardarEnviadas($enviadas);
        }
    }

    /** @param array{clave?:string,nivel?:string,texto?:string} $alerta */
    private static function enviar(string $destino, array $alerta): bool
    {
        try {
            $companyId = (int) config('circuito.jarvis.vigilia.alerta_externa.company_id', 1);
            $texto = "🚨 JARVIS — " . gethostname() . "\n"
                . (string) ($alerta['texto'] ?? ('Hallazgo: ' . ($alerta['clave'] ?? 'desconocido')));

            (new EvolutionApiService($companyId))->sendText($destino, $texto);

            return true;
        } catch (\Throwable $e) {
            // El vigilante avisa; si el propio aviso falla, se registra y se sigue midiendo. Un
            // canal de alerta que puede tumbar la medición sería peor que no tenerlo.
            Log::warning('AlertaExterna (JARVIS): no se pudo enviar el aviso de WhatsApp', [
                'clave' => $alerta['clave'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function rutaEnviadas(): string
    {
        return JarvisVigilia::dir() . '/' . self::ARCHIVO_ENVIADAS;
    }

    /** @return array<string,int> clave => timestamp del último aviso enviado */
    private static function leerEnviadas(): array
    {
        $ruta = self::rutaEnviadas();
        clearstatcache(true, $ruta);
        if (! is_readable($ruta)) {
            return [];
        }
        $j = json_decode((string) @file_get_contents($ruta), true);

        return is_array($j) ? $j : [];
    }

    /** @param array<string,int> $enviadas */
    private static function guardarEnviadas(array $enviadas): void
    {
        $ruta = self::rutaEnviadas();
        @mkdir(dirname($ruta), 0775, true);
        $tmp  = $ruta . '.tmp.' . getmypid();
        $json = json_encode($enviadas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false || @file_put_contents($tmp, $json . "\n") === false || ! @rename($tmp, $ruta)) {
            @unlink($tmp);

            return; // no tirar la vigilia por no poder guardar el dedup; la próxima vuelta reintenta
        }
        @chmod($ruta, 0664);
    }
}
