<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;

/**
 * #652 — EL TICKET, EN LLANO.
 *
 * La bandeja de Irving se lee como una spec: nombres de clase, rutas de archivo, números
 * de item. Él la revisa a diario y decide sobre ella, así que el costo de que esté escrita
 * para la máquina lo paga él, cada vez.
 *
 * Esto NO resume para ahorrar lectura: TRADUCE. El texto técnico se conserva intacto y a un
 * clic — nunca se sustituye. Si el resumen y el detalle se contradijeran, manda el detalle.
 *
 * POR QUÉ NO ES `reporte_coloquial`: ese campo concatena título y descripción y corta a 40
 * palabras (`RoadmapItem::generarReporteColoquial()`), sin modelo. Se llama coloquial y no
 * lo es. Aquí sí hay una reescritura.
 *
 * REGLA DURA DEL PROMPT: no inventar. El modelo sólo puede decir lo que el item ya dice. Si
 * el item no explica qué se rompe, el resumen lo admite en vez de rellenar — un resumen que
 * inventa consecuencias es peor que uno técnico, porque se decide sobre él.
 */
class ResumenNaturalService
{
    /** Tope de entrada: los items más largos (#646 pasa de 17 000 chars) no caben ni hacen falta. */
    private const MAX_ENTRADA = 6000;

    public function enabled(): bool
    {
        return (bool) config('circuito.resumen_natural.enabled', true);
    }

    /**
     * Devuelve el resumen en llano, o null si no se pudo. NUNCA lanza: un fallo aquí deja el
     * item con su título técnico, que es el comportamiento de hoy — no una regresión.
     */
    public function generar(RoadmapItem $item): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $cuerpo = trim((string) $item->description . "\n" . (string) $item->prompt);
        $cuerpo = mb_strimwidth($cuerpo, 0, self::MAX_ENTRADA, "\n…(recortado)");

        if (trim((string) $item->title) === '' && $cuerpo === '') {
            return null;
        }

        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'      => (string) config(
                    'circuito.resumen_natural.model',
                    config('circuito.revisor.model_routine', 'claude-sonnet-4-6')
                ),
                'max_tokens' => (int) config('circuito.resumen_natural.max_tokens', 400),
                'system'     => $this->systemPrompt(),
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, $cuerpo)]],
            ]);

            $texto = '';
            foreach (($resp['content'] ?? []) as $b) {
                if (($b['type'] ?? '') === 'text') {
                    $texto .= $b['text'];
                }
            }
            $texto = trim($texto);

            // Un resumen vacío o de una palabra no es un resumen: mejor null y que caiga al título.
            return mb_strlen($texto) >= 20 ? $texto : null;
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('resumen-natural: fallo', [
                'item' => $item->id, 'error' => mb_strimwidth($e->getMessage(), 0, 200, '…'),
            ]);

            return null;
        }
    }

    /** Genera y guarda. Devuelve true si escribió. */
    public function generarYGuardar(RoadmapItem $item): bool
    {
        $r = $this->generar($item);
        if ($r === null) {
            return false;
        }

        $item->resumen_natural    = $r;
        $item->resumen_natural_at = now();
        $item->save();

        return true;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
Traduces tickets de trabajo técnico al español llano, para el dueño de un ISP que decide
sobre ellos a diario. No es programador: entiende su negocio, no el código.

Devuelves SOLO el texto del resumen. Sin markdown, sin títulos, sin viñetas, sin comillas.

FORMA: 2 o 3 frases. Máximo 60 palabras. Se lee de un vistazo, en una tarjeta.

QUÉ TIENE QUE DECIR, en este orden:
1. Qué está mal hoy, o qué se gana. En términos de lo que pasa, no de dónde pasa.
2. Qué se haría.
3. Si el ticket pide una decisión suya, cuál es — en una frase.

PROHIBIDO:
· Nombres de clase, métodos, archivos, rutas, tablas, columnas o comandos.
· Números de item (#123), nombres de rama, hashes.
· Jerga: "endpoint", "payload", "deploy", "refactor", "flag", "commit", "merge",
  "backend", "frontend", "migración", "caché". Si el concepto hace falta, dilo en llano
  ("la pantalla", "el aviso", "lo que se guarda", "publicar el cambio").
· Empezar con "Este ticket" o "Se trata de".

REGLA QUE MANDA — NO INVENTES:
Sólo puedes decir lo que el ticket ya dice. Si no explica qué se rompe o para qué sirve,
escríbelo así: "El ticket no explica qué problema resuelve." Es información útil: significa
que está mal escrito. Rellenar con una consecuencia plausible es el peor error posible,
porque se decide sobre este texto.
TXT;
    }

    private function userPrompt(RoadmapItem $item, string $cuerpo): string
    {
        return "TÍTULO TÉCNICO: {$item->title}\n\n"
            . 'MÓDULO: ' . ($item->modulo ?: '(sin módulo)') . "\n\n"
            . "TEXTO DEL TICKET:\n" . $cuerpo
            . "\n\nEscribe el resumen en llano.";
    }
}
