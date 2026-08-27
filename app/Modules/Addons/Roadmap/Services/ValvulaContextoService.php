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
    public function evaluar(RoadmapItem $item, string $termino, bool $estricto = false, ?string $categoria = null): array
    {
        $noSePudo = fn (string $razon) => [
            'afloja' => false, 'ok' => false, 'veredicto' => null, 'razon' => $razon, 'modelo' => null,
            'seguro' => false, 'guarda' => null,
        ];

        $cfg = app(TorreConfigService::class)->get();

        // Dos interruptores, uno de código y uno de pantalla. La perilla de la Torre manda sobre la
        // de config: es la que Irving puede ver y mover, y un panel cuyo interruptor no gobierna
        // enseña a desconfiar del panel entero.
        if (! config('circuito.valvula_contexto.enabled', true) || ! $cfg->valvula_activa) {
            return $noSePudo('Válvula de contexto apagada; queda el veredicto del keyword.');
        }

        if (trim($termino) === '') {
            return $noSePudo('Sin término que evaluar.');
        }

        /*
         * ── GUARDA 1 · LA PREGUNTA TIENE QUE ESTAR BIEN FORMADA (2026-08-27, #646/#648) ──────────
         *
         * Determinista, ANTES del modelo, y por delante de la guarda de la razón.
         *
         * QUÉ CIERRA. Hasta hoy al modelo se le pasaba la CATEGORÍA de la frontera ('dinero',
         * 'credenciales') bajo la etiqueta «TÉRMINO QUE DISPARÓ». En los DOS items que llegaron a
         * sellarse —#182 y #191, 2 de 2— esa palabra ni siquiera aparecía en el texto. Y preguntar
         * «¿este item TOCA "dinero"?» sobre un documento donde esa palabra no existe empuja la
         * respuesta a «mención»: no era un error de criterio del modelo, era otra pregunta. El
         * #182 quedó exento de la única capa determinista mientras implementaba control de acceso
         * real con Spatie; el propio modelo lo dijo en su razón: «el término no aparece en el texto».
         *
         * LA REGLA. Una válvula sólo puede aflojar sobre algo que ESTÁ AHÍ. Si el término no
         * aparece en el texto que se le va a mostrar al modelo, la pregunta está mal formada: no se
         * pregunta y NO se afloja. Sin involucrar a ningún modelo — habría atrapado los dos casos.
         *
         * Se comprueba contra lo que el modelo VA A VER (`textoVisible()`), no contra el texto
         * crudo: si el término vive en una línea que `limpiar()` quita, el modelo no puede juzgarlo,
         * y juzgar sobre lo que no se ve es exactamente el defecto que esto cierra.
         */
        if ($cfg->valvula_guarda_termino && ! self::terminoPresente(self::textoVisible($item), $termino)) {
            return array_merge($noSePudo(
                "PREGUNTA MAL FORMADA: el término «{$termino}» no aparece en el texto que vería el modelo. "
                . 'Una válvula sólo puede aflojar sobre algo que está ahí, así que no se consulta y NO se afloja: '
                . 'queda el veredicto del keyword.'
            ), ['guarda' => 'termino_ausente']);
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
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, $termino, $categoria)]],
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

            /*
             * ── GUARDA 2 · LA RAZÓN TIENE QUE REFERIRSE AL TÉRMINO (guarda «(c)» de #646) ────────
             *
             * Mismo criterio de procedencia que se le exige a Thomas —toda afirmación con su cita—
             * aplicado al control de seguridad: si la razón que da el modelo no menciona el término
             * por el que se le preguntó, contestó sobre otra cosa y su «mención» no sostiene nada.
             *
             * Sólo puede QUITAR un aflojo, nunca crearlo. Nace APAGADA a propósito: es nueva y sin
             * medir, y encenderla antes de tener número endurecería a ciegas. Se enciende desde la
             * pantalla, cuando el contador diga qué tan seguido pasa.
             */
            if ($afloja && $cfg->valvula_guarda_razon
                && ! self::terminoPresente($v['razon'], $termino)) {
                return [
                    'afloja'    => false,
                    'ok'        => false,
                    'veredicto' => $v['veredicto'],
                    'razon'     => "La válvula leyó «mención», pero su razón no se refiere a «{$termino}» "
                                 . '(contestó sobre otra cosa): no afloja. Dijo: ' . $v['razon'],
                    'modelo'    => $modelo,
                    'seguro'    => false,
                    'guarda'    => 'razon_no_menciona_termino',
                ];
            }

            return [
                'afloja'    => $afloja,
                'ok'        => true,
                'veredicto' => $v['veredicto'],
                'razon'     => $v['razon'],
                'modelo'    => $modelo,
                'seguro'    => $v['seguro'],
                'guarda'    => null,
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
    public function evaluarNacimiento(RoadmapItem $item, string $termino, ?string $categoria = null): array
    {
        $r = $this->evaluar($item, $termino, true, $categoria);

        // Doble candado del lado de acá: aunque el parser dejara pasar algo raro, sólo un `mencion`
        // con `seguro=true` afloja. La duda se resuelve SIEMPRE contra el item, nunca a su favor.
        if ($r['afloja'] && ! ($r['seguro'] ?? false)) {
            return [
                'afloja' => false, 'ok' => true, 'veredicto' => self::ACCION,
                'razon'  => 'La válvula lo leyó como mención pero sin seguridad suficiente; en la puerta '
                          . 'de nacimiento la duda no afloja. ' . $r['razon'],
                'modelo' => $r['modelo'],
                'seguro' => false,
                'guarda' => $r['guarda'] ?? null,
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

    /**
     * El texto que el modelo VA A VER — exactamente los mismos trozos que arma `userPrompt()`.
     *
     * Se mantiene junto al prompt a propósito: si un día el prompt cambia lo que muestra, la guarda
     * tiene que cambiar con él, y tenerlos separados es cómo se llega a comprobar una cosa y
     * preguntar otra — que es el defecto que la guarda existe para cerrar.
     */
    public static function textoVisible(RoadmapItem $item): string
    {
        return (string) $item->title . "\n" . (string) $item->modulo . "\n"
            . DetectorTerminos::limpiar((string) $item->description . "\n" . (string) $item->prompt);
    }

    /**
     * ¿El término APARECE en este texto? Presencia, no disparo.
     *
     * Deliberadamente `apariciones()` y no `dispara()`: un término negado («no toca producción»)
     * SÍ está en el texto, así que la pregunta está bien formada y le toca al modelo juzgarla. Lo
     * que esta guarda ataja es el otro caso — preguntar por una palabra que no existe en el
     * documento.
     *
     * Anclado al inicio de palabra y admitiendo flexión, el modo más permisivo de `DetectorTerminos`:
     * al ser una guarda que sólo puede ENDURECER, conviene que su falso positivo sea «deja pasar la
     * pregunta al modelo» y no «bloquea una consulta legítima».
     */
    public static function terminoPresente(string $texto, string $termino): bool
    {
        $heno = mb_strtolower(preg_replace('/[ \t]+/', ' ', $texto));

        return DetectorTerminos::apariciones($heno, mb_strtolower(trim($termino)), false) !== [];
    }

    private function userPrompt(RoadmapItem $item, string $termino, ?string $categoria = null): string
    {
        // Se manda el texto YA LIMPIO de proceso, igual que lo vio el keyword: si se mandara el
        // texto crudo, el modelo juzgaría sobre líneas que el clasificador ni siquiera miró.
        $cuerpo = DetectorTerminos::limpiar((string) $item->description . "\n" . (string) $item->prompt);

        // El TÉRMINO es la palabra que `DetectorTerminos::dispara` hizo saltar; la CATEGORÍA es la
        // familia a la que pertenece. Hasta el 2026-08-27 aquí se interpolaba la CATEGORÍA bajo la
        // etiqueta «TÉRMINO QUE DISPARÓ», y el término real nunca llegaba: en #191 se preguntó por
        // «dinero» —palabra ausente del texto— cuando lo que había disparado era «contratar».
        // Preguntar por una palabra que no está en el documento empuja la respuesta a «mención».
        $cat = $categoria !== null && $categoria !== ''
            ? "CATEGORÍA DE FRONTERA DURA: «{$categoria}»\n"
            : '';

        return "TÉRMINO QUE DISPARÓ: «{$termino}»\n"
            . $cat . "\n"
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
