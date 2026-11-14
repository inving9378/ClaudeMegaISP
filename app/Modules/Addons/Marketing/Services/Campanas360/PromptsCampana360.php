<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingNiche;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Models\Empresa;

/**
 * Textos que se mandan a la IA en las Campañas 360 (§2.5.4 y §2.5.5). Solo datos de la campaña y del Brand Kit (y,
 * para guiones, las categorías de clips de fondo): NUNCA datos de leads ni de clientes. El público es solo lo que el
 * equipo escribió (tipo de público y, si vienen, zonas, edades e intereses); los nichos NO se mandan como público. Los datos van como JSON delimitado (no mezclados con las reglas).
 */
class PromptsCampana360
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    private const REGLAS_COMUNES = <<<'TXT'
Reglas que no se rompen:
- Español de México, claro y cercano. Dinero siempre en pesos mexicanos (MXN).
- NO inventes precios, velocidades, promociones, regalos ni plazos que no estén en la oferta. Si la oferta no lo dice, no lo digas.
- No prometas «ilimitado», «sin cortes» ni garantías si la oferta no lo dice.
- No escribas teléfonos ni ligas: el sistema pone los del Brand Kit.
- No uses los caracteres {{ ni }}.
- Responde SOLO con JSON válido, sin texto antes ni después y sin bloques de código.
TXT;

    public function sistemaEstrategia(): string
    {
        return "Eres el estratega de marketing de un proveedor de internet (ISP) en México. Diseñas campañas 360 "
            . "(Facebook, Instagram, Reels, video, anuncios de Meta y mensajes de WhatsApp que el equipo copia a mano).\n\n"
            . self::REGLAS_COMUNES;
    }

    public function usuarioEstrategia(Campaign $c): string
    {
        $canales = $c->canales ?? [];

        $forma = [
            'resumen'             => 'texto ≤ 600',
            'propuesta_valor'     => 'texto ≤ 300',
            'mensajes_clave'      => ['de 3 a 5 textos, cada uno ≤ 120'],
            'publico_detalle'     => 'texto ≤ 600',
            'por_canal'           => [['canal' => 'uno de: ' . implode(', ', $canales), 'rol' => '≤ 60', 'formato' => '≤ 120', 'frecuencia' => '≤ 60']],
            'calendario'          => [['semana' => 'entero desde 1', 'acciones' => ['de 1 a 6 textos, cada uno ≤ 160']]],
            'reparto_presupuesto' => [['canal' => 'uno de: ' . implode(', ', $canales), 'porcentaje' => 'cadena con 2 decimales; todos suman 100.00']],
            'kpis'                => [['nombre' => '≤ 60', 'meta' => '≤ 60']],
            'riesgos'             => ['de 0 a 6 textos, cada uno ≤ 200'],
        ];

        return "Diseña la estrategia de esta campaña.\n\n"
            . "DATOS DE LA CAMPAÑA (JSON):\n" . json_encode($this->datosCampana($c), self::JSON) . "\n\n"
            . "MARCA (JSON):\n" . json_encode($this->marca(), self::JSON) . "\n\n"
            . "Usa SOLO los canales de la campaña en `por_canal` y `reparto_presupuesto` (uno por canal). "
            . "El calendario cubre las semanas entre la fecha de inicio y la de fin (máximo 12 semanas).\n\n"
            . "Forma exacta de la respuesta (JSON):\n" . json_encode($forma, self::JSON);
    }

    public function sistemaPiezas(): string
    {
        return "Eres el redactor creativo de un proveedor de internet (ISP) en México. Escribes guiones de Reels y videos "
            . "por escenas, copies para Facebook e Instagram, anuncios de Meta y mensajes de WhatsApp (solo texto: el equipo "
            . "los copia a mano, el sistema no los envía).\n\n"
            . self::REGLAS_COMUNES . "\n- Sin emojis en textos que van en la pantalla del video ni en el CTA final.";
    }

    /**
     * @param  array<int,string>  $variantes
     * @param  array<int,array>  $otras  contenido de otras variantes (para no repetirlas al regenerar)
     */
    public function usuarioPiezas(Campaign $c, string $pieza, array $variantes, array $otras = [], string $indicaciones = ''): string
    {
        $estrategia = $c->estrategia ?? [];
        $resumenEstrategia = [
            'resumen'         => $estrategia['resumen'] ?? null,
            'propuesta_valor' => $estrategia['propuesta_valor'] ?? null,
            'mensajes_clave'  => $estrategia['mensajes_clave'] ?? [],
            'por_canal'       => $estrategia['por_canal'] ?? [],
        ];

        $texto = 'Escribe la pieza «' . (CatalogosCampana360::ETIQUETAS_PIEZA[$pieza] ?? $pieza) . '» en las variantes '
            . implode(', ', $variantes) . " (cada variante con un enfoque distinto).\n\n"
            . "DATOS DE LA CAMPAÑA (JSON):\n" . json_encode($this->datosCampana($c), self::JSON) . "\n\n"
            . "ESTRATEGIA APROBADA (JSON):\n" . json_encode($resumenEstrategia, self::JSON) . "\n\n"
            . "MARCA (JSON):\n" . json_encode($this->marca(), self::JSON) . "\n\n"
            . $this->bloqueCategoriasBroll($c, $pieza)
            . $this->reglasDePieza($pieza) . "\n\n"
            . "Forma exacta de la respuesta (JSON):\n"
            . json_encode(['piezas' => array_map(fn ($v) => ['variante' => $v, 'contenido' => $this->formaDePieza($pieza, $v)], $variantes)], self::JSON);

        if ($otras !== []) {
            $texto .= "\n\nOTRAS VARIANTES YA ESCRITAS (no las repitas, JSON):\n" . json_encode($otras, self::JSON);
        }

        if ($indicaciones !== '') {
            $texto .= "\n\nINDICACIONES DEL EQUIPO (aplícalas sin romper las reglas):\n" . $indicaciones;
        }

        return $texto;
    }

    public function reintento(array $errores): string
    {
        $lista = [];
        foreach ($errores as $campo => $mensaje) {
            $lista[] = "- {$campo}: {$mensaje}";
        }

        return "Tu respuesta no cumplió:\n" . implode("\n", array_slice($lista, 0, 30)) . "\nResponde solo el JSON corregido.";
    }

    // ── Piezas: forma y reglas ─────────────────────────────────────────────────────────────────

    private function formaDePieza(string $pieza, string $variante): array
    {
        return match ($pieza) {
            'reel_guion', 'video_guion' => [
                'titulo'    => '≤ 100',
                'gancho'    => '≤ 150',
                'escenas'   => [[
                    'duracion_s'     => 'entero de 2 a 15',
                    'toma'           => 'qué se ve, ≤ 200 (indicación para quien edita)',
                    'visual'         => ['tipo' => 'logo|broll|texto|cta', 'categoria_broll' => 'slug de una categoría de clips de fondo si tipo=broll, si no null'],
                    'texto_pantalla' => 'broll ≤ 60, texto ≤ 18, cta ≤ 24, logo vacío; sin emojis',
                    'voz_en_off'     => '≤ 300 y como máximo 2.5 palabras por segundo de la escena',
                    'musica_sonido'  => '≤ 100',
                ]],
                'musica'    => ['ambiente' => implode('|', array_keys(CatalogosCampana360::MUSICAS))],
                'voz'       => ['id' => implode('|', array_keys(CatalogosCampana360::VOCES))],
                'cta_final' => ['texto' => '≤ 24, sin emojis'],
            ],
            'copy_facebook'    => ['texto' => '≤ 1500', 'hashtags' => ['máximo 5, cada uno ≤ 30 sin espacios'], 'cta' => '≤ 40'],
            'copy_instagram'   => ['texto' => '≤ 2000', 'hashtags' => ['máximo 15, cada uno ≤ 30 sin espacios'], 'cta' => '≤ 40'],
            'variante_anuncio' => [
                'titular' => '≤ 40', 'texto_principal' => '≤ 125', 'descripcion' => '≤ 30',
                'boton' => implode('|', array_keys(CatalogosCampana360::BOTONES_ANUNCIO)),
                'formato' => implode('|', CatalogosCampana360::FORMATOS_ANUNCIO), 'idea_visual' => '≤ 200',
            ],
            'mensaje_whatsapp' => ['uso' => CatalogosCampana360::USOS_WHATSAPP[$variante] ?? 'difusion', 'texto' => '≤ 700'],
            default            => [],
        };
    }

    private function reglasDePieza(string $pieza): string
    {
        if (isset(CatalogosCampana360::LIMITES_GUION[$pieza])) {
            $l = CatalogosCampana360::LIMITES_GUION[$pieza];

            return "Reglas del guion ({$l['formato']}): de {$l['escenas_min']} a {$l['escenas_max']} escenas; duración total de "
                . "{$l['total_min']} a {$l['total_max']} segundos (suma de las escenas); la ÚLTIMA escena es tipo `cta`; "
                . "`categoria_broll` solo con un slug de las categorías de clips de fondo de arriba (son solo el tipo de clip, NO el "
                . "público: no hables de ellas en los textos); el motor de video no filma: «toma» es una indicación y las escenas "
                . "`broll` usan un clip de la biblioteca de esa categoría.";
        }

        return match ($pieza) {
            'mensaje_whatsapp' => 'Variante A = difusión (primer mensaje), B = seguimiento (a quien no respondió), C = respuesta a quien pregunta. '
                . 'Solo texto, sin ligas ni teléfonos.',
            'variante_anuncio' => 'Textos para el administrador de anuncios de Meta; respeta los topes de caracteres.',
            default            => 'Respeta los topes de caracteres.',
        };
    }

    // ── Datos ──────────────────────────────────────────────────────────────────────────────────

    /**
     * Datos de la campaña para la IA. Ajuste de Henry (13-nov): en «Público» la pantalla solo pide el tipo de público;
     * edad, nichos, zonas e intereses son opcionales y, si vienen vacíos, NO se mencionan (ni como «sin dato»).
     */
    private function datosCampana(Campaign $c): array
    {
        $publico = $c->publico ?? [];
        $oferta  = array_filter($c->oferta ?? [], fn ($v) => ! self::vacio($v));

        return array_filter([
            'nombre'          => $c->name,
            'objetivo'        => CatalogosCampana360::OBJETIVOS[$c->objetivo] ?? $c->objetivo,
            'publico'         => array_filter([
                'descripcion' => $publico['descripcion'] ?? null,
                'zonas'       => $publico['zonas'] ?? [],
                'edad_min'    => $publico['edad_min'] ?? null,
                'edad_max'    => $publico['edad_max'] ?? null,
                'intereses'   => $publico['intereses'] ?? [],
            ], fn ($v) => ! self::vacio($v)),
            'oferta'          => $oferta,
            'canales'         => array_values(array_intersect_key(CatalogosCampana360::CANALES, array_flip($c->canales ?? []))),
            'presupuesto_mxn' => (string) $c->budget,
            'fecha_inicio'    => $c->start_date?->format('Y-m-d'),
            'fecha_fin'       => $c->end_date?->format('Y-m-d'),
        ], fn ($v) => ! self::vacio($v));
    }

    private static function vacio(mixed $v): bool
    {
        return $v === null || $v === [] || (is_string($v) && trim($v) === '');
    }

    /**
     * Solo para guiones: categorías de clips de fondo (B-roll) que tiene la biblioteca, con slug y nombre. NO son el
     * público (Henry, 13-nov: si se mandaban como «nichos del público», la IA hablaba de gamers/home office aunque la
     * campaña fuera para otro público). Sin motivadores, dolores ni vocabulario.
     */
    private function bloqueCategoriasBroll(Campaign $c, string $pieza): string
    {
        if (! isset(CatalogosCampana360::LIMITES_GUION[$pieza])) {
            return '';
        }

        $categorias = MarketingNiche::query()
            ->where('company_id', Empresa::PRINCIPAL_ID)
            ->where('active', true)
            ->orderBy('display_order')
            ->get(['slug', 'name'])
            ->map(fn (MarketingNiche $n) => ['slug' => $n->slug, 'nombre' => $n->name])
            ->all();

        return "CATEGORÍAS DE CLIPS DE FONDO DISPONIBLES (no son el público; solo para `categoria_broll`, JSON):\n"
            . json_encode($categorias, self::JSON) . "\n\n";
    }

    private function marca(): array
    {
        $id = Empresa::PRINCIPAL_ID;

        return array_filter([
            'nombre' => Setting::get('brand_company_name', $id) ?: 'Meganet',
            'lema'   => Setting::get('brand_tagline', $id),
            'sitio'  => Setting::get('brand_website', $id),
        ]);
    }
}
