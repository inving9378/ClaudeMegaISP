<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Modules\Addons\Marketing\Services\Campanas360\ValidadorPiezas;
use Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — validador de cada tipo de pieza (§2.5.5): normal, bordes, ataques (emojis, `{{ }}`, colores,
 * teléfonos, HTML), y que nunca recorta.
 */
class ValidadorPiezasBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase2;

    private function v(): ValidadorPiezas
    {
        return new ValidadorPiezas(['familia', 'home_office']);
    }

    public function test_guion_valido_se_normaliza(): void
    {
        $g = $this->guionValido('16:9');
        $g['escenas'][0]['numero'] = 99;
        $g['duracion_total_s'] = 999;
        $g['escenas'][1]['duracion_s'] = '5';

        $r = $this->v()->validar('reel_guion', $g);

        $this->assertTrue($r['ok'], json_encode($r['errores']));
        $this->assertSame('9:16', $r['contenido']['formato']);
        $this->assertSame([1, 2, 3], array_column($r['contenido']['escenas'], 'numero'));
        $this->assertSame(16, $r['contenido']['duracion_total_s']);
        $this->assertSame(5, $r['contenido']['escenas'][1]['duracion_s']);
    }

    /** @dataProvider guionesInvalidos */
    public function test_guiones_invalidos(callable $romper, string $pieza, string $campo): void
    {
        $g = $this->guionValido();
        $romper($g);

        $r = $this->v()->validar($pieza, $g);

        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey($campo, $r['errores'], json_encode($r['errores'], JSON_UNESCAPED_UNICODE));
    }

    public static function guionesInvalidos(): array
    {
        return [
            'escena de 40 s'        => [function (&$g) { $g['escenas'][0]['duracion_s'] = 40; }, 'reel_guion', 'escenas.0.duracion_s'],
            'escena de 1 s'         => [function (&$g) { $g['escenas'][0]['duracion_s'] = 1; }, 'reel_guion', 'escenas.0.duracion_s'],
            'duración decimal'      => [function (&$g) { $g['escenas'][0]['duracion_s'] = 2.5; }, 'reel_guion', 'escenas.0.duracion_s'],
            'emoji en pantalla'     => [function (&$g) { $g['escenas'][0]['texto_pantalla'] = 'Rápido 🚀'; }, 'reel_guion', 'escenas.0.texto_pantalla'],
            'llaves en pantalla'    => [function (&$g) { $g['escenas'][0]['texto_pantalla'] = 'Llama {{brand.phone}}'; }, 'reel_guion', 'escenas.0.texto_pantalla'],
            'llaves en voz'         => [function (&$g) { $g['escenas'][0]['voz_en_off'] = 'Hola {{x}}'; }, 'reel_guion', 'escenas.0.voz_en_off'],
            'color en escena'       => [function (&$g) { $g['escenas'][0]['bg_color'] = '#ff0000'; }, 'reel_guion', 'escenas.0.bg_color'],
            'teléfono en pantalla'  => [function (&$g) { $g['escenas'][2]['texto_pantalla'] = '55 1234 5678'; }, 'reel_guion', 'escenas.2.texto_pantalla'],
            'sin cta final'         => [function (&$g) { $g['escenas'][2]['visual']['tipo'] = 'texto'; $g['escenas'][2]['texto_pantalla'] = 'Fin'; }, 'reel_guion', 'escenas'],
            'dos escenas'           => [function (&$g) { array_pop($g['escenas']); }, 'reel_guion', 'escenas'],
            'nueve escenas'         => [function (&$g) { $g['escenas'] = array_merge(array_fill(0, 8, $g['escenas'][0]), [$g['escenas'][2]]); }, 'reel_guion', 'escenas'],
            'reel de 9 s'           => [function (&$g) { foreach ($g['escenas'] as &$e) { $e['duracion_s'] = 3; } }, 'reel_guion', 'duracion_total_s'],
            'video de 12 s'         => [function (&$g) { foreach ($g['escenas'] as &$e) { $e['duracion_s'] = 4; } }, 'video_guion', 'duracion_total_s'],
            'reel de 46 s'          => [function (&$g) { foreach ($g['escenas'] as &$e) { $e['duracion_s'] = 15; } $g['escenas'][] = $g['escenas'][2]; }, 'reel_guion', 'duracion_total_s'],
            'texto de 19'           => [function (&$g) { $g['escenas'][1]['texto_pantalla'] = str_repeat('a', 19); }, 'reel_guion', 'escenas.1.texto_pantalla'],
            'cta de 25'             => [function (&$g) { $g['escenas'][2]['texto_pantalla'] = str_repeat('a', 25); }, 'reel_guion', 'escenas.2.texto_pantalla'],
            'broll de 61'           => [function (&$g) { $g['escenas'][0]['texto_pantalla'] = str_repeat('a', 61); }, 'reel_guion', 'escenas.0.texto_pantalla'],
            'broll sin categoría'   => [function (&$g) { $g['escenas'][0]['visual']['categoria_broll'] = null; }, 'reel_guion', 'escenas.0.visual.categoria_broll'],
            'categoría ajena'       => [function (&$g) { $g['escenas'][0]['visual']['categoria_broll'] = 'gamer'; }, 'reel_guion', 'escenas.0.visual.categoria_broll'],
            'tipo visual raro'      => [function (&$g) { $g['escenas'][0]['visual']['tipo'] = 'explosion'; }, 'reel_guion', 'escenas.0.visual.tipo'],
            'voz fuera de lista'    => [function (&$g) { $g['voz']['id'] = 'robot'; }, 'reel_guion', 'voz.id'],
            'música fuera de lista' => [function (&$g) { $g['musica']['ambiente'] = 'rock'; }, 'reel_guion', 'musica.ambiente'],
            'cta final con emoji'   => [function (&$g) { $g['cta_final']['texto'] = 'Ya 👉'; }, 'reel_guion', 'cta_final.texto'],
            'sin cta final texto'   => [function (&$g) { $g['cta_final'] = []; }, 'reel_guion', 'cta_final.texto'],
            'toma de 201'           => [function (&$g) { $g['escenas'][0]['toma'] = str_repeat('a', 201); }, 'reel_guion', 'escenas.0.toma'],
            'voz de 301'            => [function (&$g) { $g['escenas'][0]['voz_en_off'] = str_repeat('a', 301); }, 'reel_guion', 'escenas.0.voz_en_off'],
            'escenas objeto'        => [function (&$g) { $g['escenas'] = ['a' => 1]; }, 'reel_guion', 'escenas'],
            'escena texto'          => [function (&$g) { $g['escenas'][0] = 'hola'; }, 'reel_guion', 'escenas.0'],
            'texto pantalla arreglo'=> [function (&$g) { $g['escenas'][0]['texto_pantalla'] = ['x']; }, 'reel_guion', 'escenas.0.texto_pantalla'],
        ];
    }

    public function test_voz_larga_es_aviso_no_error(): void
    {
        $g = $this->guionValido();
        $g['escenas'][0]['voz_en_off'] = implode(' ', array_fill(0, 20, 'palabra'));

        $r = $this->v()->validar('reel_guion', $g);

        $this->assertTrue($r['ok']);
        $this->assertCount(1, $r['avisos']);
    }

    public function test_html_se_quita_sin_error(): void
    {
        $g = $this->guionValido();
        $g['titulo'] = '<b>Título</b>';

        $r = $this->v()->validar('reel_guion', $g);
        $this->assertTrue($r['ok']);
        $this->assertSame('Título', $r['contenido']['titulo']);
    }

    public function test_copies(): void
    {
        $ok = $this->v()->validar('copy_facebook', ['texto' => 'Hola', 'hashtags' => ['Fibra', '#Internet'], 'cta' => 'Ya']);
        $this->assertTrue($ok['ok']);
        $this->assertSame(['#Fibra', '#Internet'], $ok['contenido']['hashtags']);

        $this->assertArrayHasKey('hashtags', $this->v()->validar('copy_facebook', ['texto' => 'x', 'hashtags' => array_fill(0, 6, 'a')])['errores']);
        $this->assertTrue($this->v()->validar('copy_instagram', ['texto' => 'x', 'hashtags' => array_fill(0, 15, 'a')])['ok']);
        $this->assertArrayHasKey('hashtags', $this->v()->validar('copy_instagram', ['texto' => 'x', 'hashtags' => array_fill(0, 16, 'a')])['errores']);
        $this->assertArrayHasKey('texto', $this->v()->validar('copy_facebook', ['texto' => str_repeat('a', 1501)])['errores']);
        $this->assertTrue($this->v()->validar('copy_instagram', ['texto' => str_repeat('a', 2000)])['ok']);
        $this->assertArrayHasKey('hashtags.0', $this->v()->validar('copy_facebook', ['texto' => 'x', 'hashtags' => ['dos palabras']])['errores']);
        $this->assertArrayHasKey('cta', $this->v()->validar('copy_facebook', ['texto' => 'x', 'cta' => str_repeat('a', 41)])['errores']);
        $this->assertArrayHasKey('texto', $this->v()->validar('copy_facebook', ['hashtags' => []])['errores']);
    }

    public function test_anuncio(): void
    {
        $base = ['titular' => 'Fibra', 'texto_principal' => 'Rápida', 'descripcion' => 'Ya', 'boton' => 'LLAMAR', 'formato' => 'carrusel', 'idea_visual' => 'x'];
        $this->assertTrue($this->v()->validar('variante_anuncio', $base)['ok']);
        $this->assertArrayHasKey('boton', $this->v()->validar('variante_anuncio', ['boton' => 'COMPRAR'] + $base)['errores']);
        $this->assertArrayHasKey('formato', $this->v()->validar('variante_anuncio', ['formato' => 'gif'] + $base)['errores']);
        $this->assertArrayHasKey('titular', $this->v()->validar('variante_anuncio', ['titular' => str_repeat('a', 41)] + $base)['errores']);
        $this->assertArrayHasKey('texto_principal', $this->v()->validar('variante_anuncio', ['texto_principal' => str_repeat('a', 126)] + $base)['errores']);
        $this->assertArrayHasKey('descripcion', $this->v()->validar('variante_anuncio', ['descripcion' => str_repeat('a', 31)] + $base)['errores']);
    }

    public function test_whatsapp_solo_texto(): void
    {
        $this->assertTrue($this->v()->validar('mensaje_whatsapp', ['uso' => 'difusion', 'texto' => 'Hola'])['ok']);
        $this->assertArrayHasKey('uso', $this->v()->validar('mensaje_whatsapp', ['uso' => 'spam', 'texto' => 'Hola'])['errores']);
        $this->assertArrayHasKey('texto', $this->v()->validar('mensaje_whatsapp', ['uso' => 'difusion', 'texto' => str_repeat('a', 701)])['errores']);
    }

    public function test_formas_raras(): void
    {
        $this->assertFalse($this->v()->validar('reel_guion', 'texto')['ok']);
        $this->assertFalse($this->v()->validar('reel_guion', [1, 2, 3])['ok']);
        $this->assertFalse($this->v()->validar('reel', [])['ok']);
    }

    public function test_texto_plano_del_guion(): void
    {
        $r = $this->v()->validar('reel_guion', $this->guionValido());
        $plano = ValidadorPiezas::textoPlano('reel_guion', $r['contenido']);

        $this->assertStringContainsString('¿Se traba tu serie? (9:16, 16 s)', $plano);
        $this->assertStringContainsString('3. [6s] Cierre con logo', $plano);
        $this->assertStringEndsWith('CTA: Contrata hoy', $plano);
    }
}
