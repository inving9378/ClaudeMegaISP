<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAMemoriaProyecto;
use App\Modules\Addons\IA\Models\IAMensaje;
use App\Modules\Addons\IA\Models\IAProveedor;
use Illuminate\Support\Facades\Log;

/**
 * Memoria persistente del proyecto MegaISP.
 *
 * - extraerHechos(): pide al primer proveedor IA activo que parsee una
 *   conversación reciente y devuelva hechos clave en JSON.
 * - construirContexto(): produce el bloque Markdown que se inyecta como
 *   system prompt al iniciar nuevas conversaciones.
 * - limpiarObsoletos(): identifica con IA contradicciones entre hechos
 *   actuales y marca los más viejos como obsoletos.
 * - limpiarAntiguos(): elimina hechos vencidos de N días que ya estaban obsoletos.
 *
 * Toda la memoria es global del proyecto — no se segmenta por user_id.
 */
class MemoriaService
{
    /** Cantidad máxima de mensajes recientes a inspeccionar al extraer. */
    protected const MAX_MENSAJES_VENTANA = 40;

    /** Cantidad máxima de hechos a inyectar en el system prompt. */
    public const MAX_HECHOS_INYECTAR = 20;

    /**
     * Devuelve un proveedor IA activo con API key, o null si no hay ninguno.
     */
    protected function proveedorActivo(): ?IAProveedor
    {
        return IAProveedor::query()
            ->where('activo', true)
            ->whereNotNull('api_key')
            ->orderBy('id')
            ->first();
    }

    /**
     * Construye el bloque de memoria como Markdown listo para inyectar.
     * Devuelve string vacío si no hay memoria viva.
     */
    public function construirContexto(int $limit = self::MAX_HECHOS_INYECTAR): string
    {
        $hechos = IAMemoriaProyecto::vigentes()
            ->porRelevancia()
            ->limit($limit)
            ->get();

        if ($hechos->isEmpty()) {
            return '';
        }

        $etiquetas = [
            'hecho' => 'Hechos',
            'decision' => 'Decisiones',
            'avance' => 'Avances',
            'pendiente' => 'Pendientes activos',
            'error_resuelto' => 'Errores ya resueltos',
        ];
        $orden = array_keys($etiquetas);
        $agrupados = $hechos->groupBy('tipo');

        $fecha = now()->format('Y-m-d');
        $lineas = ["## Memoria persistente del proyecto MegaISP (al {$fecha})"];

        foreach ($orden as $tipo) {
            if (!$agrupados->has($tipo)) continue;
            $lineas[] = "### {$etiquetas[$tipo]}";
            foreach ($agrupados->get($tipo) as $m) {
                $mod = $m->modulo_relacionado ? " *[{$m->modulo_relacionado}]*" : '';
                $rel = "(rel:{$m->relevancia})";
                $lineas[] = "- {$m->contenido}{$mod} {$rel}";
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * Extrae hechos clave de una conversación mediante el proveedor IA activo.
     * Crea los registros y devuelve la colección de IAMemoriaProyecto creados.
     *
     * @return \Illuminate\Support\Collection<int, IAMemoriaProyecto>
     */
    public function extraerHechos(IAConversacion $conv): \Illuminate\Support\Collection
    {
        $proveedor = $this->proveedorActivo();
        if (!$proveedor) {
            Log::info('MemoriaService::extraerHechos saltado — no hay proveedor IA activo.');
            return collect();
        }

        $mensajes = IAMensaje::where('ia_conversacion_id', $conv->id)
            ->orderBy('id')
            ->take(self::MAX_MENSAJES_VENTANA)
            ->get(['rol', 'contenido']);

        if ($mensajes->count() < 2) {
            return collect();
        }

        $textoConv = $mensajes->map(function ($m) {
            $rol = $m->rol === 'user' ? 'Usuario' : 'Asistente';
            return "[{$rol}]\n{$m->contenido}";
        })->implode("\n\n");

        $instr = "Eres un extractor de hechos para un proyecto de software (MegaISP, sistema ISP). "
               . "Analiza la conversación entre el desarrollador y el asistente y extrae los hechos "
               . "duraderos que valen la pena recordar en futuras sesiones.";

        $prompt = "Analiza la siguiente conversación y extrae HASTA 5 hechos clave. Responde "
                . "EXCLUSIVAMENTE con un array JSON válido, sin texto adicional ni markdown. "
                . "Formato de cada elemento:\n"
                . "{\"tipo\":\"hecho|avance|decision|pendiente|error_resuelto\",\"contenido\":\"texto en una línea (max 300 chars)\",\"modulo_relacionado\":\"nombre o null\",\"relevancia\":1-10}\n\n"
                . "Reglas:\n"
                . "- Sólo extrae lo realmente importante para el futuro del proyecto.\n"
                . "- Evita cosas obvias o repetitivas.\n"
                . "- Si la conversación no aporta nada memorable, responde [].\n\n"
                . "Conversación:\n\n{$textoConv}";

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);
            $resp = $adaptador->enviarMensaje(
                historial: [],
                mensaje: $prompt,
                imagenes: [],
                systemPrompt: $instr
            );
            $texto = (string) ($resp['texto'] ?? '');
            $json = $this->extraerArrayJSON($texto);
            $hechos = json_decode($json, true);
            if (!is_array($hechos)) {
                Log::warning('MemoriaService::extraerHechos JSON inválido: ' . substr($texto, 0, 200));
                return collect();
            }

            $creados = collect();
            foreach ($hechos as $h) {
                if (empty($h['contenido']) || empty($h['tipo'])) continue;
                $tipo = in_array($h['tipo'], IAMemoriaProyecto::TIPOS, true) ? $h['tipo'] : 'hecho';
                $rel = (int) ($h['relevancia'] ?? 5);
                $rel = max(1, min(10, $rel));
                $modulo = !empty($h['modulo_relacionado']) && $h['modulo_relacionado'] !== 'null'
                    ? mb_substr((string) $h['modulo_relacionado'], 0, 100)
                    : null;

                $m = IAMemoriaProyecto::create([
                    'tipo' => $tipo,
                    'contenido' => mb_substr((string) $h['contenido'], 0, 500),
                    'modulo_relacionado' => $modulo,
                    'relevancia' => $rel,
                    'ia_conversacion_id' => $conv->id,
                ]);
                $creados->push($m);
            }
            return $creados;
        } catch (\Throwable $e) {
            Log::warning('MemoriaService::extraerHechos error: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Detecta hechos contradictorios entre los actuales vigentes y marca como
     * obsoletos los que la IA considere superados por hechos más recientes.
     *
     * @return int cantidad de hechos marcados como obsoletos
     */
    public function limpiarObsoletos(): int
    {
        $proveedor = $this->proveedorActivo();
        if (!$proveedor) {
            return 0;
        }

        $hechos = IAMemoriaProyecto::vigentes()->orderByDesc('updated_at')->get();
        if ($hechos->count() < 2) {
            return 0;
        }

        $lista = $hechos->map(function ($m) {
            $mod = $m->modulo_relacionado ? "[{$m->modulo_relacionado}] " : '';
            return "{id: {$m->id}, fecha: {$m->updated_at->format('Y-m-d')}, tipo: {$m->tipo}} {$mod}{$m->contenido}";
        })->implode("\n");

        $prompt = "Lista de hechos del proyecto MegaISP, ordenados del más reciente al más viejo. "
                . "Identifica los hechos VIEJOS que están contradichos o superados por hechos más nuevos. "
                . "Responde EXCLUSIVAMENTE con un array JSON con los IDs a marcar como obsoletos. "
                . "Ejemplo: [3, 17, 22]. Si no hay contradicciones responde [].\n\nHechos:\n\n{$lista}";

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);
            $resp = $adaptador->enviarMensaje(
                historial: [],
                mensaje: $prompt,
                imagenes: [],
                systemPrompt: 'Eres un detector de contradicciones. Responde solo JSON válido.'
            );
            $texto = (string) ($resp['texto'] ?? '');
            $json = $this->extraerArrayJSON($texto);
            $ids = json_decode($json, true);
            if (!is_array($ids)) return 0;
            $ids = array_filter(array_map('intval', $ids));
            if (empty($ids)) return 0;

            return IAMemoriaProyecto::whereIn('id', $ids)->update(['obsoleto' => true]);
        } catch (\Throwable $e) {
            Log::warning('MemoriaService::limpiarObsoletos error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Elimina hechos obsoletos creados hace más de N días (default 90).
     */
    public function limpiarAntiguos(int $dias = 90): int
    {
        return IAMemoriaProyecto::where('obsoleto', true)
            ->where('created_at', '<', now()->subDays($dias))
            ->delete();
    }

    /**
     * Extrae el primer array JSON encontrado en el texto, robusto frente a
     * respuestas con texto antes/después o envuelto en code fences.
     */
    protected function extraerArrayJSON(string $text): string
    {
        $text = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/i', '$1', $text);
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start === false || $end === false || $end <= $start) {
            return '[]';
        }
        return substr($text, $start, $end - $start + 1);
    }
}
