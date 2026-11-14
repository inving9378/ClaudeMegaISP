<?php

namespace App\Modules\Addons\Marketing\Services\Campanas360;

/**
 * Traduce un guion de Campaña 360 (§2.5.5) a las variables que entiende el motor Video MVM con la plantilla
 * «Guion por escenas» (§2.6). Los colores NUNCA vienen del guion: los pone el motor desde el Brand Kit. Todo texto
 * se limpia otra vez aquí (sin `{{ }}`, emojis ni caracteres de control) aunque el guion ya haya pasado el validador.
 */
class GuionAMvm
{
    /**
     * @param  array  $guion  contenido validado de un `reel_guion` / `video_guion`
     * @param  array<int,string>  $nichosCampana  `publico.nichos` de la campaña (respaldo de `_niche_slug`)
     * @return array{variables: array, options: array}
     */
    public function traducir(array $guion, string $formato, ?string $voz = null, ?string $musica = null, array $nichosCampana = []): array
    {
        $ctaFinal = self::limpiar((string) ($guion['cta_final']['texto'] ?? ''));
        $escenas  = [];
        $voces    = [];
        $nicho    = null;

        foreach ($guion['escenas'] ?? [] as $e) {
            $dur   = max(CatalogosCampana360::ESCENA_DURACION_MIN, min(CatalogosCampana360::ESCENA_DURACION_MAX, (int) ($e['duracion_s'] ?? 0)));
            $tipo  = $e['visual']['tipo'] ?? null;
            $texto = self::limpiar((string) ($e['texto_pantalla'] ?? ''));

            $escena = match ($tipo) {
                'logo'  => ['type' => 'logo_reveal', 'duration' => $dur],
                'broll' => $this->broll($dur, (string) ($e['visual']['categoria_broll'] ?? ''), $texto),
                'texto' => ['type' => 'feature_highlight', 'duration' => $dur, 'main_text' => $texto, 'sub_text' => ''],
                'cta'   => ['type' => 'call_to_action', 'duration' => $dur, 'cta_text' => $texto !== '' ? $texto : $ctaFinal, 'phone' => '{{brand.phone}}'],
                default => null,
            };

            if ($escena === null) {
                continue;
            }

            if ($tipo === 'broll' && $nicho === null && self::slugValido($e['visual']['categoria_broll'] ?? null)) {
                $nicho = $e['visual']['categoria_broll'];
            }

            $escenas[] = $escena;

            $vozEscena = self::limpiar((string) ($e['voz_en_off'] ?? ''));
            if ($vozEscena !== '') {
                $voces[] = $vozEscena;
            }
        }

        if ($nicho === null) {
            foreach ($nichosCampana as $n) {
                if (self::slugValido($n)) {
                    $nicho = $n;
                    break;
                }
            }
        }

        $voz    = $voz ?? ($guion['voz']['id'] ?? null);
        $musica = $musica ?? ($guion['musica']['ambiente'] ?? null);

        return [
            'variables' => [
                '_escenas_mvm'         => $escenas,
                'voiceover_text'       => implode(' ', $voces),
                '_voz'                 => array_key_exists((string) $voz, CatalogosCampana360::VOCES) ? $voz : null,
                '_musica'              => is_string($musica) && $musica !== 'sin_musica' && array_key_exists($musica, CatalogosCampana360::MUSICAS) ? $musica : null,
                '_niche_slug'          => $nicho,
                '_transition_type'     => 'fade',
                '_transition_duration' => 0.3,
            ],
            'options' => ['aspect_ratio' => $formato],
        ];
    }

    private function broll(int $dur, string $categoria, string $texto): array
    {
        $escena = [
            'type'         => 'broll_overlay',
            'duration'     => $dur,
            'broll_source' => 'niche:' . (self::slugValido($categoria) ? $categoria : 'familia'),
            'bg_darken'    => 0.35,
        ];

        if ($texto !== '') {
            $escena['layers'] = [[
                'type'         => 'kinetic_text',
                'text'         => $texto,
                'duration_sec' => $dur,
                'style'        => 'huge_centered',
                'position'     => 'center',
            ]];
        }

        return $escena;
    }

    /** Sin `{{ }}`, sin emojis, sin etiquetas ni caracteres de control; espacios colapsados. */
    public static function limpiar(string $texto): string
    {
        $texto = strip_tags($texto);
        $texto = str_replace(['{{', '}}'], '', $texto);
        $texto = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{E0020}-\x{E007F}]/u', '', $texto) ?? '';
        $texto = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $texto) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }

    public static function slugValido(mixed $slug): bool
    {
        return is_string($slug) && preg_match('/^[a-z_]{1,50}$/', $slug) === 1;
    }
}
