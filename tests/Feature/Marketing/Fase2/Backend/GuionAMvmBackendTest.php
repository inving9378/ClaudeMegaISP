<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Modules\Addons\Marketing\Services\Campanas360\GuionAMvm;
use Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — traducción de un guion a las variables del motor Video MVM (§2.6): cada tipo de visual,
 * colores descartados, `{{ }}` y emojis fuera, voz/música elegidas y respaldo del nicho.
 */
class GuionAMvmBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase2;

    public function test_traduce_cada_tipo_de_visual(): void
    {
        $guion = $this->guionValido('9:16');
        array_unshift($guion['escenas'], ['duracion_s' => 3, 'toma' => 'Logo', 'visual' => ['tipo' => 'logo', 'categoria_broll' => null],
            'texto_pantalla' => '', 'voz_en_off' => '', 'musica_sonido' => '']);

        $t = (new GuionAMvm())->traducir($guion, '9:16');
        $e = $t['variables']['_escenas_mvm'];

        $this->assertSame(['type' => 'logo_reveal', 'duration' => 3], $e[0]);
        $this->assertSame([
            'type' => 'broll_overlay', 'duration' => 5, 'broll_source' => 'niche:familia', 'bg_darken' => 0.35,
            'layers' => [['type' => 'kinetic_text', 'text' => '¿Se traba tu serie?', 'duration_sec' => 5, 'style' => 'huge_centered', 'position' => 'center']],
        ], $e[1]);
        $this->assertSame(['type' => 'feature_highlight', 'duration' => 5, 'main_text' => '$599 al mes', 'sub_text' => ''], $e[2]);
        $this->assertSame(['type' => 'call_to_action', 'duration' => 6, 'cta_text' => 'Contrata hoy', 'phone' => '{{brand.phone}}'], $e[3]);

        $this->assertSame('¿Otra vez se trabó la serie? Fibra de 500 megas. Contrata hoy, la instalación es gratis.', $t['variables']['voiceover_text']);
        $this->assertSame('nova', $t['variables']['_voz']);
        $this->assertSame('uplifting_family', $t['variables']['_musica']);
        $this->assertSame('familia', $t['variables']['_niche_slug']);
        $this->assertSame('fade', $t['variables']['_transition_type']);
        $this->assertSame(0.3, $t['variables']['_transition_duration']);
        $this->assertSame(['aspect_ratio' => '9:16'], $t['options']);
    }

    public function test_cta_sin_texto_usa_el_cta_final_y_broll_sin_texto_no_lleva_capa(): void
    {
        $guion = $this->guionValido();
        $guion['escenas'][0]['texto_pantalla'] = '';
        $guion['escenas'][2]['texto_pantalla'] = '';

        $e = (new GuionAMvm())->traducir($guion, '9:16')['variables']['_escenas_mvm'];

        $this->assertArrayNotHasKey('layers', $e[0]);
        $this->assertSame('Contrata hoy', $e[2]['cta_text']);
    }

    public function test_colores_llaves_emojis_y_control_fuera(): void
    {
        $guion = $this->guionValido();
        $guion['escenas'][0]['bg_color'] = 'red';
        $guion['escenas'][0]['visual']['color'] = 'blue';
        $guion['escenas'][1]['texto_pantalla'] = "{{brand.phone}} Oferta 🔥\x07";
        $guion['escenas'][2]['voz_en_off'] = 'Llama {{phone_cta}} 😀';

        $t = (new GuionAMvm())->traducir($guion, '16:9');
        $json = json_encode($t, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('color', $json);
        $this->assertStringNotContainsString('"red"', $json);
        $this->assertSame('brand.phone Oferta', $t['variables']['_escenas_mvm'][1]['main_text']);
        $this->assertStringNotContainsString('{{phone_cta}}', $t['variables']['voiceover_text']);
        $this->assertStringNotContainsString('😀', $t['variables']['voiceover_text']);
        // El único marcador permitido es el teléfono del Brand Kit que pone el traductor en el CTA.
        $this->assertSame('{{brand.phone}}', $t['variables']['_escenas_mvm'][2]['phone']);
    }

    public function test_voz_y_musica_elegidas_ganan_y_sin_musica_es_null(): void
    {
        $t = (new GuionAMvm())->traducir($this->guionValido(), '9:16', 'onyx', 'sin_musica');
        $this->assertSame('onyx', $t['variables']['_voz']);
        $this->assertNull($t['variables']['_musica']);

        $t2 = (new GuionAMvm())->traducir($this->guionValido(), '9:16', 'robot', 'rock');
        $this->assertNull($t2['variables']['_voz']);
        $this->assertNull($t2['variables']['_musica']);
    }

    public function test_nicho_de_respaldo_es_el_primero_de_la_campana(): void
    {
        $guion = $this->guionValido();
        $guion['escenas'][0]['visual'] = ['tipo' => 'texto', 'categoria_broll' => null];

        $t = (new GuionAMvm())->traducir($guion, '9:16', null, null, ['home_office', 'familia']);
        $this->assertSame('home_office', $t['variables']['_niche_slug']);

        $t2 = (new GuionAMvm())->traducir($guion, '9:16', null, null, ['../etc']);
        $this->assertNull($t2['variables']['_niche_slug']);
    }

    public function test_slug_de_broll_raro_cae_a_familia(): void
    {
        $guion = $this->guionValido();
        $guion['escenas'][0]['visual']['categoria_broll'] = 'x"; rm -rf /';

        $e = (new GuionAMvm())->traducir($guion, '9:16')['variables']['_escenas_mvm'];
        $this->assertSame('niche:familia', $e[0]['broll_source']);
    }
}
