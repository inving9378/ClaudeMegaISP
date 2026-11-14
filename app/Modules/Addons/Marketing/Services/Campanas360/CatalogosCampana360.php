<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

/**
 * Catálogos cerrados de las Campañas 360 (§2.5 de docs/marketing/fase-2-paquetes.md). Una sola fuente para
 * validación, prompts y etiquetas; el frontend tiene su espejo en `catalogos360.js`.
 */
final class CatalogosCampana360
{
    public const OBJETIVOS = [
        'nuevos_clientes' => 'Conseguir clientes nuevos',
        'reconocimiento'  => 'Dar a conocer la marca',
        'cobertura_nueva' => 'Anunciar cobertura nueva',
        'promocion'       => 'Impulsar una promoción',
        'retencion'       => 'Retener clientes',
        'mejora_plan'     => 'Subir de plan a clientes',
    ];

    public const CANALES = [
        'facebook'      => 'Publicaciones de Facebook',
        'instagram'     => 'Publicaciones de Instagram',
        'reels'         => 'Reels (9:16)',
        'video'         => 'Video (16:9)',
        'anuncios_meta' => 'Anuncios de Meta',
        'whatsapp'      => 'Mensajes de WhatsApp (solo texto, no se envían)',
    ];

    /** Canal → [pieza, variantes] (F2-9: fijas en esta fase). El orden define el orden de generación. */
    public const PIEZAS_POR_CANAL = [
        'reels'         => ['pieza' => 'reel_guion', 'variantes' => ['A', 'B']],
        'video'         => ['pieza' => 'video_guion', 'variantes' => ['A']],
        'facebook'      => ['pieza' => 'copy_facebook', 'variantes' => ['A', 'B']],
        'instagram'     => ['pieza' => 'copy_instagram', 'variantes' => ['A', 'B']],
        'anuncios_meta' => ['pieza' => 'variante_anuncio', 'variantes' => ['A', 'B', 'C']],
        'whatsapp'      => ['pieza' => 'mensaje_whatsapp', 'variantes' => ['A', 'B', 'C']],
    ];

    /** Piezas de texto (las genera la IA) en el orden en que la ficha las muestra. */
    public const PIEZAS_TEXTO = ['reel_guion', 'video_guion', 'copy_facebook', 'copy_instagram', 'mensaje_whatsapp', 'variante_anuncio'];

    public const PIEZAS_VIDEO = ['reel', 'video'];

    public const GUIONES = ['reel_guion', 'video_guion'];

    public const ETIQUETAS_PIEZA = [
        'reel_guion'       => 'Guion de Reel',
        'video_guion'      => 'Guion de Video',
        'copy_facebook'    => 'Texto para Facebook',
        'copy_instagram'   => 'Texto para Instagram',
        'variante_anuncio' => 'Variante de anuncio',
        'mensaje_whatsapp' => 'Mensaje de WhatsApp',
        'reel'             => 'Reel producido',
        'video'            => 'Video producido',
    ];

    /** Uso esperado de cada variante de WhatsApp. */
    public const USOS_WHATSAPP = ['A' => 'difusion', 'B' => 'seguimiento', 'C' => 'respuesta'];

    public const ESTADOS_CAMPANA = [
        'draft'    => 'Borrador',
        'active'   => 'Activa',
        'paused'   => 'Pausada',
        'finished' => 'Terminada',
        'canceled' => 'Cancelada',
    ];

    /** Transiciones válidas de estado (§2.4.6). */
    public const TRANSICIONES = [
        'draft'    => ['active', 'canceled'],
        'active'   => ['paused', 'finished'],
        'paused'   => ['active', 'finished', 'canceled'],
        'finished' => [],
        'canceled' => [],
    ];

    public const VOCES = [
        'alloy'   => 'Neutra',
        'echo'    => 'Masculina joven',
        'fable'   => 'Masculina narrativa',
        'onyx'    => 'Masculina grave',
        'nova'    => 'Femenina cálida',
        'shimmer' => 'Femenina suave',
    ];

    public const MUSICAS = [
        'corporate_clean'     => 'Corporativa limpia',
        'corporate_confident' => 'Corporativa segura',
        'epic_electronic'     => 'Electrónica épica',
        'modern_creative'     => 'Moderna creativa',
        'uplifting_family'    => 'Familiar alegre',
        'warm_acoustic'       => 'Acústica cálida',
        'sin_musica'          => 'Sin música',
    ];

    public const BOTONES_ANUNCIO = [
        'MAS_INFORMACION' => 'Más información',
        'ENVIAR_MENSAJE'  => 'Enviar mensaje',
        'LLAMAR'          => 'Llamar',
        'REGISTRARTE'     => 'Registrarte',
        'OBTENER_OFERTA'  => 'Obtener oferta',
        'CONTACTANOS'     => 'Contáctanos',
    ];

    public const FORMATOS_ANUNCIO = ['imagen', 'video', 'carrusel'];

    public const TIPOS_VISUAL = ['logo', 'broll', 'texto', 'cta'];

    /** Límites de guion (F2-10). */
    public const LIMITES_GUION = [
        'reel_guion'  => ['formato' => '9:16', 'escenas_min' => 3, 'escenas_max' => 8, 'total_min' => 10, 'total_max' => 45],
        'video_guion' => ['formato' => '16:9', 'escenas_min' => 3, 'escenas_max' => 8, 'total_min' => 15, 'total_max' => 75],
    ];

    public const ESCENA_DURACION_MIN = 2;

    public const ESCENA_DURACION_MAX = 15;

    /** Tope de `texto_pantalla` por tipo de visual (`logo` se ignora). */
    public const TOPE_TEXTO_PANTALLA = ['broll' => 60, 'texto' => 18, 'cta' => 24];

    /** Estado de la estrategia. */
    public const ESTRATEGIA_ESTADOS = ['sin_generar', 'generando', 'lista', 'error'];

    public static function piezasDeCanales(array $canales): array
    {
        $piezas = [];
        foreach (self::PIEZAS_POR_CANAL as $canal => $def) {
            if (in_array($canal, $canales, true)) {
                $piezas[$def['pieza']] = $def['variantes'];
            }
        }

        return $piezas;
    }

    public static function formatoDeGuion(string $pieza): ?string
    {
        return self::LIMITES_GUION[$pieza]['formato'] ?? null;
    }

    public static function piezaVideoDeGuion(string $pieza): ?string
    {
        return ['reel_guion' => 'reel', 'video_guion' => 'video'][$pieza] ?? null;
    }

    public static function nombreFuncionIA(string $clave): string
    {
        return (config('ia_modulos', [])[$clave]['nombre'] ?? null) ?: $clave;
    }
}
