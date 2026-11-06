<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\IaSupervisor;
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
            // #1076 — el modelo lo elige Integraciones → Módulos IA («circuito.resumen»).
            $r = IaSupervisor::llamar(IaSupervisor::RESUMEN, [
                'max_tokens' => (int) config('circuito.resumen_natural.max_tokens', 400),
                'system'     => $this->systemPrompt(),
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, $cuerpo)]],
            ], ['item' => $item->id]);

            $texto = trim($r['texto']);

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

    // ─────────────────────────────────────────────────────────────────────────────────────────
    // PIEZA 2 (#652) — LAS PREGUNTAS DEL BRIEF, EN LLANO
    // ─────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Traduce las preguntas del brief y sus opciones, SIN TOCAR `preguntas`.
     *
     * ⚠️ LA REGLA QUE GOBIERNA ESTA PIEZA: la identidad de una opción es su texto
     * (`RoadmapItem::claveOpcion()` = sha1 del texto), y la Torre responde mandando esa clave.
     * Traducir dentro de `preguntas` cambiaría las claves y una decisión ya tomada apuntaría a
     * otra opción. Aquí se devuelve una estructura PARALELA indexada por la clave ORIGINAL.
     *
     * Devuelve null si algo no cuadra. Un brief sin traducir se lee técnico —molesto pero
     * correcto—; uno mal traducido hace que Irving autorice algo que no leyó.
     *
     * @return array<string, array{pregunta:string, opciones:array<string,string>}>|null
     */
    public function generarPreguntas(RoadmapItem $item): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $preguntas = $item->preguntasNormalizadas();
        if (empty($preguntas)) {
            return null;
        }

        // Se manda al modelo con índices explícitos: así la respuesta se puede volver a casar
        // posición por posición, sin depender de que repita textos largos sin equivocarse.
        $payload = [];
        foreach ($preguntas as $qi => $p) {
            $ops = [];
            foreach (($p['opciones'] ?? []) as $oi => $o) {
                $ops[] = ['i' => $oi, 'texto' => mb_strimwidth((string) ($o['texto'] ?? ''), 0, 900, '…')];
            }
            $payload[] = ['q' => $qi, 'pregunta' => (string) ($p['pregunta'] ?? ''), 'opciones' => $ops];
        }

        try {
            // #1076 — mismo modelo que el resumen: se elige en Integraciones → Módulos IA («circuito.resumen»).
            $r = IaSupervisor::llamar(IaSupervisor::RESUMEN, [
                'max_tokens' => (int) config('circuito.resumen_natural.max_tokens_preguntas', 2000),
                'system'     => $this->systemPromptPreguntas(),
                'messages'   => [['role' => 'user', 'content' => json_encode(
                    ['titulo' => $item->title, 'preguntas' => $payload],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )]],
            ], ['item' => $item->id]);

            $texto = (string) $r['texto'];

            // El modelo a veces envuelve el JSON en ```; se extrae el bloque de todos modos.
            if (preg_match('/\[[\s\S]*\]|\{[\s\S]*\}/', $texto, $m)) {
                $texto = $m[0];
            }
            $datos = json_decode(trim($texto), true);

            return $this->casarPreguntas($preguntas, $datos);
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('preguntas-natural: fallo', [
                'item' => $item->id, 'error' => mb_strimwidth($e->getMessage(), 0, 200, '…'),
            ]);

            return null;
        }
    }

    /**
     * EL CANDADO. Casa la respuesta del modelo con las preguntas REALES por índice, y verifica
     * que no se haya inventado, perdido ni fusionado nada. Ante cualquier desajuste devuelve
     * null: se prefiere el brief técnico completo a uno traducido a medias.
     */
    private function casarPreguntas(array $preguntas, $datos): ?array
    {
        if (! is_array($datos) || empty($datos)) {
            return null;
        }
        // Tolera {"preguntas": [...]} además del array pelado.
        if (isset($datos['preguntas']) && is_array($datos['preguntas'])) {
            $datos = $datos['preguntas'];
        }
        if (count($datos) !== count($preguntas)) {
            return null;   // se perdió o se inventó una pregunta
        }

        $out = [];

        foreach ($preguntas as $qi => $p) {
            $t = $datos[$qi] ?? null;
            if (! is_array($t)) {
                return null;
            }

            $opsOrig = array_values($p['opciones'] ?? []);
            $opsTrad = array_values($t['opciones'] ?? []);
            if (count($opsTrad) !== count($opsOrig)) {
                return null;   // el modelo fusionó o partió opciones
            }

            $mapa = [];
            foreach ($opsOrig as $oi => $o) {
                $natural = trim((string) ($opsTrad[$oi]['texto'] ?? $opsTrad[$oi] ?? ''));
                if ($natural === '') {
                    return null;
                }
                // LA CLAVE SALE DEL TEXTO ORIGINAL, SIEMPRE. Es lo que la Torre enviará al responder.
                $clave = $o['clave'] ?? RoadmapItem::claveOpcion((string) ($o['texto'] ?? ''));
                $mapa[$clave] = $natural;
            }

            $preguntaNat = trim((string) ($t['pregunta'] ?? ''));
            if ($preguntaNat === '') {
                return null;
            }

            $out[(string) ($p['id'] ?? $qi)] = ['pregunta' => $preguntaNat, 'opciones' => $mapa];
        }

        return $out;
    }

    /**
     * Genera y guarda la traducción de las preguntas. Devuelve true si escribió.
     *
     * Escribe SOLO en `preguntas_natural`. `preguntas` no se toca aquí ni en ningún otro punto
     * de este servicio: ahí viven las claves con las que la Torre identifica lo que se autoriza.
     */
    public function generarYGuardarPreguntas(RoadmapItem $item): bool
    {
        $nat = $this->generarPreguntas($item);
        if ($nat === null) {
            return false;
        }

        $item->preguntas_natural    = $nat;
        $item->preguntas_natural_at = now();
        $item->save();

        return true;
    }

    private function systemPromptPreguntas(): string
    {
        return <<<'TXT'
Traduces al español llano las preguntas de decisión de un sistema técnico, para el dueño de un
ISP que las responde. No es programador.

ENTRADA: JSON con {titulo, preguntas:[{q, pregunta, opciones:[{i, texto}]}]}.

SALIDA: SOLO un JSON array, sin markdown ni explicación, con EXACTAMENTE la misma cantidad de
preguntas y, dentro de cada una, EXACTAMENTE la misma cantidad de opciones y EN EL MISMO ORDEN:
[{"q":0,"pregunta":"…","opciones":[{"i":0,"texto":"…"},{"i":1,"texto":"…"}]}]

REGLAS QUE NO SE ROMPEN:
1. NO fusiones, dividas, agregues ni elimines opciones. Si la entrada trae 3, devuelves 3.
2. NO cambies el SENTIDO. Esta persona AUTORIZA trabajo con estas respuestas: si tu texto dice
   algo distinto de la opción original, autoriza algo que no leyó. Ante la duda entre claridad y
   fidelidad, elige FIDELIDAD.
3. CONSERVA lo que distingue una opción de otra. Si dos opciones se diferencian sólo en un
   matiz, ese matiz es justo lo que hay que dejar claro, no lo que se puede simplificar.
4. CONSERVA las consecuencias: si una opción dice que algo se borra, se pospone o cuesta más,
   eso va en la traducción.

ESTILO:
· Pregunta: una frase, máximo 25 palabras, terminada en signo de interrogación.
· Cada opción: una o dos frases, máximo 35 palabras. Si la original trae pros y contras,
  consérvalos en llano ("más rápido, pero hay que rehacerlo si crece").
· Sin nombres de clase, archivos, rutas, tablas ni comandos. Sin jerga: nada de "endpoint",
  "refactor", "deploy", "migración", "flag", "caché", "backend". Dilo por lo que hace.
· No empieces las opciones con "Opción 1:" ni numeración: el orden ya se ve en pantalla.
TXT;
    }
}
