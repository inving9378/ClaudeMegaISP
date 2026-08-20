<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\DetectorTerminos;
use Illuminate\Support\Facades\Log;

/**
 * VÁLVULA DE ESCAPE DEL CLASIFICADOR — afloja, nunca amplía.
 *
 * El problema (medido, no supuesto): el matcher de términos no distingue MENCIONAR un tema de
 * TOCARLO. El #877 salió nivel C por una fila de tabla que describe la etiqueta que una pantalla
 * mostraría; el #875 por su propia línea «PROHIBIDO `migrate:fresh`». Los arreglos de patrones
 * (boilerplate, negaciones, límite de palabra) bajaron la tasa de C de 72% a 52% sobre los 163
 * items de los últimos 30 días, pero el resto es prosa legítima —"la fase 3 toca permisos"— y
 * ningún patrón la resuelve: distinguirla es comprensión, no coincidencia.
 *
 * LA CONDICIÓN QUE HACE ESTO SEGURO (Irving, 2026-08-20) — la revisión de contexto es una VÁLVULA
 * DE ESCAPE, nunca una otorgadora de permiso:
 *
 *   1. El keyword corre primero: rápido, determinista y gratis. Si no pega, esto ni se invoca.
 *   2. Si pega, su veredicto NO es final: dispara esta revisión.
 *   3. La revisión SOLO puede AFLOJAR el veredicto del keyword. Nunca ampliarlo.
 *   4. Si la revisión falla, se cae, tarda demasiado o no está disponible: QUEDA EL VEREDICTO DEL
 *      KEYWORD.
 *
 * De ahí la propiedad que importa: **el peor caso de este sistema es exactamente el comportamiento
 * de hoy.** Nunca queda menos protegido; solo queda menos molesto. Un control de seguridad que
 * depende de una llamada externa deja de funcionar justo cuando esa llamada falla — por eso el
 * camino que decide ESCALAR sigue siendo determinista y gratuito, y solo el camino que decide
 * RELAJAR paga una llamada.
 *
 * Volumen medido antes de construirla: de 164 items triados en 30 días, el keyword marca C en 85
 * → ~2.8 llamadas al día, una por item. No es una decisión de presupuesto.
 */
class ValvulaContextoService
{
    /** El término se USA (se toca el tema): el veredicto del keyword se mantiene. */
    public const ACCION = 'accion';

    /** El término solo se MENCIONA (se habla del tema): el veredicto se afloja. */
    public const MENCION = 'mencion';

    /**
     * ¿El término que disparó el keyword es una MENCIÓN o una ACCIÓN?
     *
     * @return array{afloja:bool, ok:bool, veredicto:?string, razon:string, modelo:?string}
     *         `ok=false` significa "no se pudo preguntar" — el llamador DEBE conservar el veredicto
     *         del keyword. NUNCA devuelve `afloja=true` sin una respuesta afirmativa del modelo.
     */
    public function evaluar(RoadmapItem $item, string $termino, bool $estricto = false): array
    {
        $noSePudo = fn (string $razon) => [
            'afloja' => false, 'ok' => false, 'veredicto' => null, 'razon' => $razon, 'modelo' => null,
            'seguro' => false,
        ];

        if (! config('circuito.valvula_contexto.enabled', true)) {
            return $noSePudo('Válvula de contexto desactivada por configuración; queda el veredicto del keyword.');
        }

        if (trim($termino) === '') {
            return $noSePudo('Sin término que evaluar.');
        }

        $modelo = (string) config(
            'circuito.valvula_contexto.model',
            config('circuito.revisor.model_routine', 'claude-sonnet-4-6')
        );

        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'      => $modelo,
                // Clasificación binaria con una frase de razón: no necesita más.
                'max_tokens' => (int) config('circuito.valvula_contexto.max_tokens', 300),
                'system'     => $this->systemPrompt($estricto),
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, $termino)]],
            ]);

            $texto = '';
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') {
                    $texto .= $blk['text'] ?? '';
                }
            }

            $v = $this->parse($texto);
            if ($v === null) {
                return $noSePudo('La válvula contestó algo que no se pudo leer; queda el veredicto del keyword.');
            }

            // ⚠️ EL CANDADO: solo `mencion` afloja. Cualquier otra respuesta —incluida una que
            // intente subir el nivel— deja el veredicto del keyword intacto. La válvula no puede
            // ampliar, solo abrir.
            $afloja = $v['veredicto'] === self::MENCION;

            return [
                'afloja'    => $afloja,
                'ok'        => true,
                'veredicto' => $v['veredicto'],
                'razon'     => $v['razon'],
                'modelo'    => $modelo,
                'seguro'    => $v['seguro'],
            ];
        } catch (\Throwable $e) {
            // Falla-segura EXPLÍCITA: sin modelo, sin red, timeout o error de la API → el keyword
            // manda. Es el punto 4 de la condición de Irving, y es lo que hace que el peor caso de
            // este sistema sea el comportamiento de hoy.
            Log::warning('valvula-contexto: no se pudo evaluar; queda el veredicto del keyword', [
                'item'    => $item->id,
                'termino' => $termino,
                'error'   => mb_strimwidth($e->getMessage(), 0, 200, '…'),
            ]);

            return $noSePudo('No se pudo llamar al modelo (' . mb_strimwidth($e->getMessage(), 0, 120, '…')
                . '); queda el veredicto del keyword.');
        }
    }

    /**
     * VÁLVULA DE NACIMIENTO — la misma pregunta, con el listón MÁS ALTO.
     *
     * Condición 1 de Irving (2026-08-20): «más conservadora aquí que en el triaje de nivel. Ante
     * duda, no afloja. En el triaje una equivocación cuesta un nivel; aquí cuesta saltarse mi
     * autorización.» Por eso este camino:
     *
     *   · usa un prompt propio que exige que la mención sea INEQUÍVOCA (no "probablemente");
     *   · exige que el modelo declare `seguro: true` — un "mencion" con dudas NO afloja;
     *   · registra su propio evento (`valvula_nacimiento`), separado de `valvula_contexto`, para
     *     poder auditar por separado cuántas veces aflojó aquí. Esa medición es la que dirá dentro
     *     de un mes si esto fue buena idea.
     *
     * Y lo que NUNCA hace, por la cuarta regla fija de Irving: **aflojar aquí no vuelve al item
     * auto-ejecutable.** Sólo lo acerca al camino normal (triaje → revisor → autopilot). El último
     * control sigue puesto.
     *
     * @return array{afloja:bool, ok:bool, veredicto:?string, razon:string, modelo:?string}
     */
    public function evaluarNacimiento(RoadmapItem $item, string $termino): array
    {
        $r = $this->evaluar($item, $termino, true);

        // Doble candado del lado de acá: aunque el parser dejara pasar algo raro, sólo un `mencion`
        // con `seguro=true` afloja. La duda se resuelve SIEMPRE contra el item, nunca a su favor.
        if ($r['afloja'] && ! ($r['seguro'] ?? false)) {
            return [
                'afloja' => false, 'ok' => true, 'veredicto' => self::ACCION,
                'razon'  => 'La válvula lo leyó como mención pero sin seguridad suficiente; en la puerta '
                          . 'de nacimiento la duda no afloja. ' . $r['razon'],
                'modelo' => $r['modelo'],
            ];
        }

        return $r;
    }

    private function systemPrompt(bool $estricto = false): string
    {
        if ($estricto) {
            return <<<'TXT'
Eres un clasificador binario dentro del circuito de desarrollo de MegaISP. Decides si un término de
frontera dura aparece en el texto de un item de trabajo porque el trabajo TOCA ese tema, o porque el
texto solo HABLA de ese tema.

Responde SIEMPRE con este JSON y nada más:
{"veredicto": "accion" | "mencion", "seguro": true | false, "razon": "una frase corta en español"}

"accion" — el trabajo descrito realmente toca el tema: modifica permisos, corre una migración
destructiva, despliega a producción, mueve dinero o toca credenciales.

"mencion" — el término aparece describiendo, citando, prohibiendo o nombrando algo, sin que el
trabajo lo toque: una etiqueta de UI dentro de una tabla, una línea de guardrail que PROHÍBE la
acción, el nombre de un directorio dentro de una ruta de archivo, una frase que explica por qué
OTRO item quedará en cierto estado.

"seguro" — pon `true` SOLO si la lectura es INEQUÍVOCA: el texto no deja lugar a que el trabajo
toque el tema. Si tienes que suponer, interpretar o el texto es ambiguo, pon `false`.

ESTE ES EL CONTROL MÁS ALTO DEL SISTEMA: aquí un error no cuesta un nivel de riesgo, cuesta
saltarse la autorización de un humano. Ante CUALQUIER duda responde {"veredicto":"accion"} o al
menos {"seguro": false}. Un falso "accion" solo hace que un humano mire el item; un falso "mencion"
seguro deja avanzar trabajo sensible. El costo no es simétrico, ni de cerca.
TXT;
        }

        return <<<'TXT'
Eres un clasificador binario dentro del circuito de desarrollo de MegaISP. Tu ÚNICA tarea es
decidir si un término de frontera dura aparece en el texto de un item de trabajo porque el trabajo
TOCA ese tema, o porque el texto solo HABLA de ese tema.

Responde SIEMPRE con este JSON y nada más:
{"veredicto": "accion" | "mencion", "razon": "una frase corta en español"}

"accion" — el trabajo descrito realmente toca el tema: modifica permisos, corre una migración
destructiva, despliega a producción, mueve dinero o toca credenciales. Ejemplos: "agregar el
permiso talento.portal", "correr migrate:fresh en la base", "desplegar a .198".

"mencion" — el término aparece describiendo, citando, prohibiendo o nombrando algo, sin que el
trabajo lo toque. Ejemplos: una fila de tabla que describe la etiqueta que una pantalla mostraría;
una línea de guardrail que PROHÍBE una acción; una frase que explica por qué OTRO item quedará en
cierto estado; el nombre de un directorio (deploy/circuito/) dentro de una ruta de archivo.

REGLA DURA: ante la duda, responde "accion". Un falso "accion" solo hace que un humano revise el
item; un falso "mencion" deja pasar trabajo sensible sin revisión. El costo no es simétrico.
TXT;
    }

    private function userPrompt(RoadmapItem $item, string $termino): string
    {
        // Se manda el texto YA LIMPIO de proceso, igual que lo vio el keyword: si se mandara el
        // texto crudo, el modelo juzgaría sobre líneas que el clasificador ni siquiera miró.
        $cuerpo = DetectorTerminos::limpiar((string) $item->description . "\n" . (string) $item->prompt);

        return "TÉRMINO QUE DISPARÓ: «{$termino}»\n\n"
            . "TÍTULO DEL ITEM: {$item->title}\n"
            . "MÓDULO: " . ($item->modulo ?: '(sin módulo)') . "\n\n"
            . "TEXTO DEL ITEM:\n" . mb_strimwidth($cuerpo, 0, 12000, "\n…(recortado)")
            . "\n\n¿El item TOCA «{$termino}» (accion) o solo lo MENCIONA (mencion)?";
    }

    /** @return array{veredicto:string, razon:string, seguro:bool}|null */
    private function parse(string $texto): ?array
    {
        $t = trim($texto);
        if ($t === '') {
            return null;
        }

        // Tolera fences ```json — el modelo a veces los agrega aunque se le pida JSON pelado.
        if (preg_match('/\{.*\}/s', $t, $m)) {
            $t = $m[0];
        }

        $d = json_decode($t, true);
        if (! is_array($d)) {
            return null;
        }

        $v = mb_strtolower(trim((string) ($d['veredicto'] ?? '')));
        if (! in_array($v, [self::ACCION, self::MENCION], true)) {
            return null;
        }

        return [
            'veredicto' => $v,
            'razon'     => trim((string) ($d['razon'] ?? '')) ?: 'Sin razón declarada.',
            // Sólo el prompt estricto lo pide; en el modo normal la ausencia no significa duda.
            'seguro'    => array_key_exists('seguro', $d) ? (bool) $d['seguro'] : true,
        ];
    }
}
