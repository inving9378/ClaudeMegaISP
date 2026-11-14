<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Models\Marketing\MarketingNiche;
use App\Modules\Addons\Marketing\Models\Empresa;
use Illuminate\Support\Str;

/**
 * Valida y normaliza el `contenido` de cada pieza de una Campaña 360 (§2.5.5). Se usa igual para la salida de la IA
 * (si no cumple: un reintento y luego `failed`) y para la edición manual (si no cumple: 422). Nunca recorta: el
 * usuario o la IA corrigen.
 *
 * Saneado silencioso de todo texto: `strip_tags` y caracteres de control fuera. Es ERROR (no se limpia en silencio):
 * `{{` / `}}` (marcadores del motor de video), emojis en textos que van a la pantalla del video, claves con «color»
 * en una escena (los colores los pone el motor desde el Brand Kit), teléfonos escritos en el guion (el teléfono sale
 * siempre del Brand Kit).
 */
class ValidadorPiezas
{
    /** @var array<int,string>|null */
    private ?array $nichos = null;

    /**
     * @param  array<int,string>|null  $nichos  slugs de nicho válidos para `categoria_broll` (null = los activos de Meganet)
     */
    public function __construct(?array $nichos = null)
    {
        $this->nichos = $nichos;
    }

    /**
     * @return array{ok: bool, contenido: array, errores: array<string,string>, avisos: array<int,string>}
     */
    public function validar(string $pieza, mixed $contenido): array
    {
        if (! is_array($contenido) || array_is_list($contenido) && $contenido !== []) {
            return $this->resultado([], ['contenido' => 'El contenido debe ser un objeto.']);
        }

        return match ($pieza) {
            'reel_guion', 'video_guion' => $this->validarGuion($pieza, $contenido),
            'copy_facebook'             => $this->validarCopy($contenido, 1500, 5),
            'copy_instagram'            => $this->validarCopy($contenido, 2000, 15),
            'variante_anuncio'          => $this->validarAnuncio($contenido),
            'mensaje_whatsapp'          => $this->validarWhatsApp($contenido),
            default                     => $this->resultado([], ['pieza' => 'Esta pieza no se puede editar.']),
        };
    }

    // ── Guiones ─────────────────────────────────────────────────────────────────────────────────

    private function validarGuion(string $pieza, array $c): array
    {
        $lim     = CatalogosCampana360::LIMITES_GUION[$pieza];
        $errores = [];
        $avisos  = [];

        $titulo = $this->texto($c, 'titulo', 100, $errores, requerido: true);
        $gancho = $this->texto($c, 'gancho', 150, $errores);

        $escenasEntrada = $c['escenas'] ?? null;
        $escenas = [];

        if (! is_array($escenasEntrada) || ! array_is_list($escenasEntrada)) {
            $errores['escenas'] = 'Las escenas deben ser una lista.';
            $escenasEntrada = [];
        } elseif (count($escenasEntrada) < $lim['escenas_min'] || count($escenasEntrada) > $lim['escenas_max']) {
            $errores['escenas'] = "El guion debe tener de {$lim['escenas_min']} a {$lim['escenas_max']} escenas.";
        }

        $total = 0;
        foreach (array_values($escenasEntrada) as $i => $e) {
            $p = "escenas.{$i}";

            if (! is_array($e) || array_is_list($e)) {
                $errores[$p] = 'Cada escena debe ser un objeto.';
                continue;
            }

            foreach (array_keys($e) as $clave) {
                if (is_string($clave) && str_contains(Str::lower($clave), 'color')) {
                    $errores["{$p}.{$clave}"] = 'Los colores no se eligen en el guion: los pone el video desde el Brand Kit.';
                }
            }

            $dur = $e['duracion_s'] ?? null;
            if (! $this->esEntero($dur) || (int) $dur < CatalogosCampana360::ESCENA_DURACION_MIN || (int) $dur > CatalogosCampana360::ESCENA_DURACION_MAX) {
                $errores["{$p}.duracion_s"] = 'Cada escena dura de ' . CatalogosCampana360::ESCENA_DURACION_MIN . ' a '
                    . CatalogosCampana360::ESCENA_DURACION_MAX . ' segundos (número entero).';
                $dur = 0;
            }
            $dur = (int) $dur;
            $total += $dur;

            $visual = $e['visual'] ?? null;
            $tipo   = is_array($visual) ? ($visual['tipo'] ?? null) : null;
            if (! is_string($tipo) || ! in_array($tipo, CatalogosCampana360::TIPOS_VISUAL, true)) {
                $errores["{$p}.visual.tipo"] = 'El visual debe ser logo, broll, texto o cta.';
                $tipo = null;
            }

            $categoria = is_array($visual) ? ($visual['categoria_broll'] ?? null) : null;
            if ($tipo === 'broll') {
                if (! is_string($categoria) || ! in_array($categoria, $this->nichos(), true)) {
                    $errores["{$p}.visual.categoria_broll"] = 'Elige una categoría de B-roll (nicho) válida.';
                    $categoria = null;
                }
            } else {
                $categoria = null;
            }

            $tope = CatalogosCampana360::TOPE_TEXTO_PANTALLA[$tipo] ?? 60;
            $textoPantalla = $tipo === 'logo' ? '' : $this->texto($e, 'texto_pantalla', $tope, $errores, $p, sinEmoji: true, sinTelefono: true);
            if ($tipo === 'texto' && $textoPantalla === '') {
                $errores["{$p}.texto_pantalla"] = 'Una escena de texto necesita el texto en pantalla.';
            }

            $voz = $this->texto($e, 'voz_en_off', 300, $errores, $p, sinTelefono: false);
            if ($voz !== '' && $dur > 0 && str_word_count($voz, 0, 'áéíóúüñÁÉÍÓÚÜÑ¿¡') > (int) floor($dur * 2.5)) {
                $avisos[] = 'La voz en off de la escena ' . ($i + 1) . ' es larga para ' . $dur . ' s (más de 2.5 palabras por segundo).';
            }

            $escenas[] = [
                'numero'         => $i + 1,
                'duracion_s'     => $dur,
                'toma'           => $this->texto($e, 'toma', 200, $errores, $p, requerido: true),
                'visual'         => ['tipo' => $tipo, 'categoria_broll' => $categoria],
                'texto_pantalla' => $textoPantalla,
                'voz_en_off'     => $voz,
                'musica_sonido'  => $this->texto($e, 'musica_sonido', 100, $errores, $p),
            ];
        }

        if ($escenas !== [] && end($escenas)['visual']['tipo'] !== 'cta') {
            $errores['escenas'] = $errores['escenas'] ?? 'La última escena debe ser la llamada a la acción (cta).';
        }

        if (! isset($errores['escenas']) && $escenasEntrada !== [] && ($total < $lim['total_min'] || $total > $lim['total_max'])) {
            $errores['duracion_total_s'] = "El guion debe durar de {$lim['total_min']} a {$lim['total_max']} segundos (suma {$total} s).";
        }

        $ambiente = $c['musica']['ambiente'] ?? null;
        if (! is_string($ambiente) || ! array_key_exists($ambiente, CatalogosCampana360::MUSICAS)) {
            $errores['musica.ambiente'] = 'Elige una música de la lista.';
        }

        $vozId = $c['voz']['id'] ?? null;
        if (! is_string($vozId) || ! array_key_exists($vozId, CatalogosCampana360::VOCES)) {
            $errores['voz.id'] = 'Elige una voz de la lista.';
        }

        $cta = is_array($c['cta_final'] ?? null) ? $c['cta_final'] : [];
        $ctaTexto = $this->texto($cta, 'texto', 24, $errores, 'cta_final', requerido: true, sinEmoji: true, sinTelefono: true);

        return $this->resultado([
            'titulo'           => $titulo,
            'formato'          => $lim['formato'],
            'gancho'           => $gancho,
            'escenas'          => $escenas,
            'musica'           => ['ambiente' => is_string($ambiente) ? $ambiente : null],
            'voz'              => ['id' => is_string($vozId) ? $vozId : null],
            'cta_final'        => ['texto' => $ctaTexto],
            'duracion_total_s' => $total,
        ], $errores, $avisos);
    }

    // ── Copies, anuncios, WhatsApp ──────────────────────────────────────────────────────────────

    private function validarCopy(array $c, int $tope, int $maxHashtags): array
    {
        $errores = [];
        $texto = $this->texto($c, 'texto', $tope, $errores, requerido: true);
        $cta   = $this->texto($c, 'cta', 40, $errores);

        $hashtags = [];
        $entrada  = $c['hashtags'] ?? [];
        if (! is_array($entrada) || ($entrada !== [] && ! array_is_list($entrada))) {
            $errores['hashtags'] = 'Los hashtags deben ser una lista.';
        } elseif (count($entrada) > $maxHashtags) {
            $errores['hashtags'] = "Máximo {$maxHashtags} hashtags.";
        } else {
            foreach ($entrada as $i => $h) {
                $limpio = is_string($h) ? $this->sanear($h) : null;
                if ($limpio === null || $limpio === '' || mb_strlen($limpio) > 30 || preg_match('/\s/u', $limpio)) {
                    $errores["hashtags.{$i}"] = 'Cada hashtag: una palabra de hasta 30 caracteres.';
                    continue;
                }
                if ($this->tieneLlaves($limpio)) {
                    $errores["hashtags.{$i}"] = 'No puede llevar {{ }}.';
                    continue;
                }
                $hashtags[] = str_starts_with($limpio, '#') ? $limpio : '#' . $limpio;
            }
        }

        return $this->resultado(['texto' => $texto, 'hashtags' => $hashtags, 'cta' => $cta], $errores);
    }

    private function validarAnuncio(array $c): array
    {
        $errores = [];
        $boton   = $c['boton'] ?? null;
        $formato = $c['formato'] ?? null;

        if (! is_string($boton) || ! array_key_exists($boton, CatalogosCampana360::BOTONES_ANUNCIO)) {
            $errores['boton'] = 'Elige un botón de la lista.';
        }
        if (! is_string($formato) || ! in_array($formato, CatalogosCampana360::FORMATOS_ANUNCIO, true)) {
            $errores['formato'] = 'El formato debe ser imagen, video o carrusel.';
        }

        return $this->resultado([
            'titular'         => $this->texto($c, 'titular', 40, $errores, requerido: true),
            'texto_principal' => $this->texto($c, 'texto_principal', 125, $errores, requerido: true),
            'descripcion'     => $this->texto($c, 'descripcion', 30, $errores),
            'boton'           => is_string($boton) ? $boton : null,
            'formato'         => is_string($formato) ? $formato : null,
            'idea_visual'     => $this->texto($c, 'idea_visual', 200, $errores),
        ], $errores);
    }

    private function validarWhatsApp(array $c): array
    {
        $errores = [];
        $uso = $c['uso'] ?? null;

        if (! is_string($uso) || ! in_array($uso, CatalogosCampana360::USOS_WHATSAPP, true)) {
            $errores['uso'] = 'El uso debe ser difusión, seguimiento o respuesta.';
        }

        return $this->resultado([
            'uso'   => is_string($uso) ? $uso : null,
            'texto' => $this->texto($c, 'texto', 700, $errores, requerido: true),
        ], $errores);
    }

    // ── Texto plano (output_text) ───────────────────────────────────────────────────────────────

    public static function textoPlano(string $pieza, array $c): string
    {
        return match ($pieza) {
            'reel_guion', 'video_guion' => trim(implode("\n", array_merge(
                [($c['titulo'] ?? '') . ' (' . ($c['formato'] ?? '') . ', ' . ($c['duracion_total_s'] ?? 0) . ' s)'],
                array_map(fn ($e) => sprintf(
                    '%d. [%ds] %s | Pantalla: %s | Voz: %s',
                    $e['numero'] ?? 0, $e['duracion_s'] ?? 0, $e['toma'] ?? '', $e['texto_pantalla'] ?? '', $e['voz_en_off'] ?? ''
                ), $c['escenas'] ?? []),
                ['CTA: ' . ($c['cta_final']['texto'] ?? '')]
            ))),
            'copy_facebook', 'copy_instagram' => trim(($c['texto'] ?? '') . "\n" . implode(' ', $c['hashtags'] ?? []) . "\n" . ($c['cta'] ?? '')),
            'variante_anuncio' => trim(implode("\n", array_filter([
                $c['titular'] ?? '', $c['texto_principal'] ?? '', $c['descripcion'] ?? '',
                'Botón: ' . (CatalogosCampana360::BOTONES_ANUNCIO[$c['boton'] ?? ''] ?? ''),
            ]))),
            'mensaje_whatsapp' => (string) ($c['texto'] ?? ''),
            default => '',
        };
    }

    // ── Saneado ─────────────────────────────────────────────────────────────────────────────────

    /** strip_tags + sin caracteres de control (se conservan salto de línea y tabulador) + trim. */
    public function sanear(string $texto): string
    {
        $texto = strip_tags($texto);
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? '';

        return trim($texto);
    }

    public static function tieneEmoji(string $texto): bool
    {
        return (bool) preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{E0020}-\x{E007F}]/u', $texto);
    }

    public function tieneLlaves(string $texto): bool
    {
        return str_contains($texto, '{{') || str_contains($texto, '}}');
    }

    public static function pareceTelefono(string $texto): bool
    {
        return (bool) preg_match('/(?:\d[\s\-\.\(\)]*){8,}/', $texto);
    }

    // ── Apoyos ──────────────────────────────────────────────────────────────────────────────────

    private function texto(array $c, string $campo, int $tope, array &$errores, ?string $prefijo = null,
                           bool $requerido = false, bool $sinEmoji = false, bool $sinTelefono = false): string
    {
        $ruta  = $prefijo ? "{$prefijo}.{$campo}" : $campo;
        $valor = $c[$campo] ?? null;

        if ($valor === null || $valor === '') {
            if ($requerido) {
                $errores[$ruta] = 'Este campo es obligatorio.';
            }

            return '';
        }

        if (! is_string($valor)) {
            $errores[$ruta] = 'Debe ser texto.';

            return '';
        }

        $limpio = $this->sanear($valor);

        if ($requerido && $limpio === '') {
            $errores[$ruta] = 'Este campo es obligatorio.';
        } elseif (mb_strlen($limpio) > $tope) {
            $errores[$ruta] = "Máximo {$tope} caracteres (tiene " . mb_strlen($limpio) . ').';
        } elseif ($this->tieneLlaves($limpio)) {
            $errores[$ruta] = 'No puede llevar {{ }}.';
        } elseif ($sinEmoji && self::tieneEmoji($limpio)) {
            $errores[$ruta] = 'Sin emojis: el video no los puede dibujar.';
        } elseif ($sinTelefono && self::pareceTelefono($limpio)) {
            $errores[$ruta] = 'No escribas teléfonos: el video pone el del Brand Kit.';
        }

        return $limpio;
    }

    private function esEntero(mixed $v): bool
    {
        return is_int($v) || (is_float($v) && floor($v) === $v) || (is_string($v) && preg_match('/^\d{1,3}$/', $v));
    }

    /** @return array<int,string> */
    private function nichos(): array
    {
        if ($this->nichos === null) {
            $this->nichos = MarketingNiche::query()
                ->where('company_id', Empresa::PRINCIPAL_ID)
                ->where('active', true)
                ->pluck('slug')
                ->all();
        }

        return $this->nichos;
    }

    private function resultado(array $contenido, array $errores, array $avisos = []): array
    {
        return ['ok' => $errores === [], 'contenido' => $contenido, 'errores' => $errores, 'avisos' => $avisos];
    }
}
