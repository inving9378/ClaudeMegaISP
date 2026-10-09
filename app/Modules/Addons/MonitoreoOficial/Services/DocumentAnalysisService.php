<?php

namespace App\Modules\Addons\MonitoreoOficial\Services;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\MonitoreoOficial\Models\MonitoringCaseDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fase 5 ("IA"): lee el contenido de un PDF subido a un expediente, vía el
 * adaptador IA COMPARTIDO del sistema (App\Modules\Addons\IA\Services\IA) —
 * nunca un cliente HTTP propio ni una clave propia (regla "servicios
 * compartidos únicos" de CLAUDE.md). Clave del módulo ante el Hub:
 * config('monitoreo.service_key') — la MISMA que ya usa CapabilityDetector
 * para decidir si "IA" sale disponible/sin_config en el panel.
 *
 * Nunca inventa: si la IA no está configurada o la llamada falla, el
 * resultado queda con ok=false y un error legible — jamás un resumen
 * fabricado.
 */
class DocumentAnalysisService
{
    private const SYSTEM_PROMPT = <<<'TXT'
        Eres un asistente que lee documentos oficiales (declaraciones, resoluciones,
        comprobantes) de autoridades gubernamentales mexicanas para un sistema de
        monitoreo de expedientes. Responde ÚNICAMENTE con el JSON solicitado, en
        español, sin explicaciones fuera del JSON. Nunca inventes datos: si algo no
        se puede leer con certeza, dilo explícitamente en el campo correspondiente
        en vez de adivinar.
        TXT;

    /** @return array{ok:bool, resumen:?string, cambio_relevante:?bool, que_cambio:?string, error:?string} */
    public function analizar(MonitoringCaseDocument $documento, ?MonitoringCaseDocument $anterior): array
    {
        $clave = config('monitoreo.service_key', 'monitoreo-oficial');

        if (! Storage::disk('local')->exists($documento->stored_path)) {
            return $this->fallo('El archivo del documento ya no existe en el servidor.');
        }

        $bytes = Storage::disk('local')->get($documento->stored_path);
        $imagenes = [['mime' => 'application/pdf', 'data' => base64_encode($bytes)]];

        $comparando = $anterior && Storage::disk('local')->exists($anterior->stored_path);
        if ($comparando) {
            $imagenes[] = ['mime' => 'application/pdf', 'data' => base64_encode(Storage::disk('local')->get($anterior->stored_path))];
        }

        $prompt = $comparando
            ? "Te adjunto DOS documentos PDF: el PRIMERO es el más reciente (recién subido), el SEGUNDO "
                . "es la versión anterior del mismo expediente. Responde con este JSON exacto: "
                . '{"resumen": "resumen breve (2-3 líneas) del documento más reciente", '
                . '"cambio_relevante": true o false (¿el documento nuevo representa un cambio de fondo '
                . 'respecto al anterior, como un nuevo estatus legal, resolución, fecha o monto? '
                . 'diferencias puramente de formato/escaneo NO cuentan), '
                . '"que_cambio": "si cambio_relevante es true, describe en 1-2 líneas QUÉ cambió; si es false, cadena vacía"}'
            : 'Te adjunto UN documento PDF (es el primero que se sube para este expediente, no hay versión '
                . 'anterior con qué comparar). Responde con este JSON exacto: '
                . '{"resumen": "resumen breve (2-3 líneas) del documento", "cambio_relevante": false, "que_cambio": ""}';

        try {
            $r = IA::enviar($clave, $prompt, $imagenes, self::SYSTEM_PROMPT, [], ['json' => true]);
            $datos = IA::json((string) ($r['texto'] ?? ''));

            if (! $datos || ! isset($datos['resumen'])) {
                return $this->fallo('La IA respondió en un formato inesperado; no se pudo interpretar.');
            }

            return [
                'ok' => true,
                'resumen' => (string) $datos['resumen'],
                'cambio_relevante' => (bool) ($datos['cambio_relevante'] ?? false),
                'que_cambio' => (string) ($datos['que_cambio'] ?? ''),
                'error' => null,
            ];
        } catch (IANoConfigurada $e) {
            return $this->fallo($e->getMessage());
        } catch (Throwable $e) {
            Log::warning('[MonitoreoOficial] DocumentAnalysisService falló', [
                'document_id' => $documento->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallo('No se pudo analizar el documento con la IA: ' . $e->getMessage());
        }
    }

    private function fallo(string $mensaje): array
    {
        return ['ok' => false, 'resumen' => null, 'cambio_relevante' => null, 'que_cambio' => null, 'error' => $mensaje];
    }
}
