<?php

namespace App\Modules\Addons\Roadmap\Support;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\JarvisService;

/**
 * QUÉ LE FALTA A ESTE ITEM PARA PODER DECIDIRSE — determinista, sin IA.
 *
 * ── POR QUÉ ─────────────────────────────────────────────────────────────────────────────────────
 *
 * Un item que cae en la bandeja diciendo sólo «requiere revisión» le cuesta a Irving el tiempo dos
 * veces: una para abrirlo y otra para averiguar qué se esperaba de él. La regla es que **ningún
 * item llegue mudo**: si el circuito no pudo decidirlo, tiene que decir con qué frase concreta se
 * desbloquea («no dice si toca producción», «el alcance abarca tres módulos y no dice cuál
 * primero»).
 *
 * ── POR QUÉ DETERMINISTA Y SEPARADO DEL BRIEF ───────────────────────────────────────────────────
 *
 * El brief de `RevisorService::proponerPreguntas()` son PREGUNTAS DE DECISIÓN (con opciones, las
 * que lee el autopilot) y lo genera una IA. Los huecos son otra cosa: **información que el item no
 * trae**. Se separan por dos razones:
 *
 *  1. El brief depende del modelo y de un worker de cola. Cuando cualquiera de los dos falta —hoy
 *     mismo: sin workers arriba— el item se quedaba mudo, que es exactamente el caso que esto viene
 *     a cerrar. Esto no llama a nadie: siempre escribe algo.
 *  2. Escribirlos dentro de `preguntas` chocaría con el footgun documentado de los IDs POSICIONALES
 *     (`q1`, `q2`…): re-briefear pega la respuesta vieja a una pregunta nueva. Viven en su propia
 *     columna y los dos pueden coexistir.
 *
 * ── SOBRE LOS TÉRMINOS ──────────────────────────────────────────────────────────────────────────
 *
 * **Aquí no nace ninguna lista nueva de términos de frontera.** La detección de producción / dinero
 * / credenciales / borrado se le pregunta a `JarvisService::categoriaFronteraDura()`, que es el
 * único detector. Una segunda copia envejeciendo por separado sería el tercer incidente de la misma
 * familia («palabra completa, no substring» y «no distingue mención de negación»).
 */
class HuecosDelSpec
{
    /** Un spec por debajo de esto no es un spec: es un título con adorno. */
    private const MIN_CARACTERES_SPEC = 220;

    /** Señales de que el item SÍ dice cómo se ve terminado. Palabra completa, con acentos. */
    private const SENALES_CRITERIO = [
        'criterio', 'criterios', 'aceptación', 'aceptacion', 'al terminar', 'se ve',
        'en pantalla', 'resultado esperado', 'dod', 'definición de hecho', 'definicion de hecho',
        'verificación', 'verificacion', 'cómo se comprueba', 'como se comprueba', 'captura',
    ];

    /** Señales de que el item YA se pronunció sobre producción (en cualquier dirección). */
    private const SENALES_PRONUNCIA_PROD = [
        'no toca producción', 'no toca produccion', 'sin tocar producción', 'sin tocar produccion',
        'sólo dev', 'solo dev', 'sólo en dev', 'solo en dev', 'no prod', 'dev únicamente',
        'dev unicamente', 'no se despliega', 'fuera de alcance: producción', 'prohibido: producción',
    ];

    /**
     * @return list<array{clave:string,titulo:string,pregunta:string,opciones:list<string>}>
     */
    public static function detectar(RoadmapItem $item): array
    {
        $bruto = trim((string) $item->title . "\n" . (string) $item->description . "\n" . (string) $item->prompt);
        $heno  = mb_strtolower(preg_replace('/[ \t]+/', ' ', DetectorTerminos::limpiar($bruto)));
        $out   = [];

        // 1 — El item no trae spec. Va primero: si falta esto, lo demás sobra.
        if (mb_strlen(trim((string) $item->description . (string) $item->prompt)) < self::MIN_CARACTERES_SPEC) {
            $out[] = [
                'clave'    => 'sin_spec',
                'titulo'   => 'El item no trae spec, sólo título',
                'pregunta' => 'Este item no dice qué hay que hacer más allá del título. ¿Lo describes, o lo cierro?',
                'opciones' => [
                    'Lo describo yo — déjalo en la bandeja esperando el spec',
                    'Que el circuito proponga el spec y me lo traiga para aprobar',
                    'Ciérralo: ya no aplica',
                ],
            ];
        }

        // 2 — No dice qué se ve en pantalla al terminar.
        if (! self::mencionaAlguno($heno, self::SENALES_CRITERIO)) {
            $out[] = [
                'clave'    => 'sin_criterio_visible',
                'titulo'   => 'No dice qué se ve en pantalla al terminar',
                'pregunta' => 'No hay criterio de aceptación: nadie puede decir si quedó hecho. ¿Cómo compruebas que terminó?',
                'opciones' => [
                    'Con una captura de la pantalla que cambia — dime cuál',
                    'Con un comando que devuelve un valor concreto (sin pantalla)',
                    'Es trabajo interno sin cara visible: basta el commit',
                ],
            ];
        }

        // 3 — Frontera dura MENCIONADA sin pronunciarse. El detector no distingue mención de uso
        //     (limitación documentada), así que en vez de decidir por él, se pregunta.
        $categoria = app(JarvisService::class)->categoriaFronteraDura($bruto);
        if ($categoria !== null) {
            $yaSePronuncio = $categoria === 'produccion' && self::mencionaAlguno($heno, self::SENALES_PRONUNCIA_PROD);
            if (! $yaSePronuncio) {
                $out[] = [
                    'clave'    => 'frontera_' . $categoria,
                    'titulo'   => 'Menciona ' . self::nombreCategoria($categoria) . ' y no dice si lo toca',
                    'pregunta' => 'El texto menciona ' . self::nombreCategoria($categoria)
                                . '. ¿Este trabajo lo toca de verdad, o sólo lo nombra de pasada?',
                    'opciones' => [
                        'Sólo lo menciona — no lo toca, puede avanzar sin ti',
                        'Sí lo toca — que se quede contigo y no lo tome ninguna terminal',
                        'Lo toca pero de forma acotada — dime el límite exacto en el spec',
                    ],
                ];
            }
        }

        // 4 — Footprint desconocido. No es cosmético: un item sin `modulo` corre SOLO y bloquea a
        //     las 6 terminales mientras esté en vuelo (#432 B2). Es el freno de throughput #526.
        $modulo = trim((string) $item->modulo);
        if ($modulo === '' || mb_strtolower($modulo) === 'sin clasificar') {
            $out[] = [
                'clave'    => 'footprint_desconocido',
                'titulo'   => 'Sin módulo asignado: si corre, bloquea a las 6 terminales',
                'pregunta' => 'Este item no dice qué módulo toca. Sin eso corre en exclusiva y para la flota entera. ¿Cuál es?',
                // `array_merge`, NO `+`: con el operador, un array_map de menos de 3 elementos
                // dejaría huecos en los índices y `json_encode` escribiría un OBJETO en vez de una
                // lista — el front espera lista y se quedaría sin opciones que pintar.
                'opciones' => array_merge(
                    array_map(
                        fn ($m) => 'Es de ' . $m,
                        array_slice(self::modulosCandidatos($heno) ?: array_keys((array) config('circuito.clasificador.reglas', [])), 0, 3)
                    ),
                    ['Toca varios / todavía no se sabe — dímelo tú']
                ),
            ];
        }

        // 5 — Alcance repartido entre módulos sin orden declarado.
        $candidatos = self::modulosCandidatos($heno);
        if (count($candidatos) >= 2 && ! self::mencionaAlguno($heno, ['primero', 'en este orden', 'fase 1', 'empezar por'])) {
            $out[] = [
                'clave'    => 'alcance_multimodulo',
                'titulo'   => 'El alcance abarca ' . count($candidatos) . ' módulos y no dice cuál primero',
                'pregunta' => 'El texto toca ' . implode(', ', $candidatos) . ' y no declara el orden. ¿Por cuál se empieza?',
                'opciones' => array_map(fn ($m) => 'Empezar por ' . $m, array_slice($candidatos, 0, 3)),
            ];
        }

        return $out;
    }

    /** ¿Alguno de estos términos aparece, anclado a palabra y sin negación delante? */
    private static function mencionaAlguno(string $heno, array $terminos): bool
    {
        foreach ($terminos as $t) {
            if (DetectorTerminos::dispara($heno, mb_strtolower($t))) {
                return true;
            }
        }

        return false;
    }

    /** Módulos que el texto parece tocar, según el MISMO mapa que usa el clasificador. */
    private static function modulosCandidatos(string $heno): array
    {
        $out = [];
        foreach ((array) config('circuito.clasificador.reglas', []) as $modulo => $terminos) {
            if (self::mencionaAlguno($heno, (array) $terminos)) {
                $out[] = $modulo;
            }
        }

        return array_values(array_unique($out));
    }

    private static function nombreCategoria(string $c): string
    {
        return match ($c) {
            'produccion'    => 'producción',
            'borrar_datos'  => 'borrado de datos',
            'dinero'        => 'dinero',
            'credenciales'  => 'credenciales o seguridad',
            default         => $c,
        };
    }
}
