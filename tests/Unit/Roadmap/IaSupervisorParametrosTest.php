<?php

namespace Tests\Unit\Roadmap;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Roadmap\Support\IaSupervisor;
use App\Modules\Addons\Roadmap\Support\RespuestaNoConcluyente;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD ni la API.

/**
 * #1076 — cómo adapta el supervisor cada petición al modelo, cómo lee la respuesta y cuánto cuesta.
 *
 * Lo que se candadea es lo que hace 400 en la API real: `temperature` en los 5.x y en Opus 4.7,
 * `thinking: disabled` en Sonnet 5.5 (va `between_tools`), y un `max_tokens` que el razonamiento
 * se comería. Y que un `refusal` nunca se lea como veredicto.
 */
final class IaSupervisorParametrosTest extends TestCase
{
    private function base(array $extra = []): array
    {
        return array_merge([
            'max_tokens'  => 300,
            'system'      => 'corto',
            'messages'    => [['role' => 'user', 'content' => 'hola']],
            'temperature' => 0,
        ], $extra);
    }

    public function test_haiku_55_sin_razonamiento_lo_apaga_con_disabled_y_quita_la_temperatura(): void
    {
        $p = IaSupervisor::parametros('claude-haiku-5-5', $this->base(), 'low', false);

        $this->assertSame('claude-haiku-5-5', $p['model']);
        $this->assertArrayNotHasKey('temperature', $p);
        $this->assertSame(['type' => 'disabled'], $p['thinking']);
        $this->assertSame(['effort' => 'low'], $p['output_config']);
        // Sin razonamiento solo se compensa el tokenizer nuevo (~30 % más tokens).
        $this->assertSame(390, $p['max_tokens']);
    }

    public function test_sonnet_55_sin_razonamiento_usa_between_tools_porque_disabled_es_400(): void
    {
        $p = IaSupervisor::parametros('claude-sonnet-5-5', $this->base(), 'low', false);

        $this->assertSame(['type' => 'between_tools'], $p['thinking']);
        $this->assertArrayNotHasKey('temperature', $p);
        $this->assertSame('low', $p['output_config']['effort']);
    }

    public function test_con_razonamiento_no_manda_thinking_y_sube_el_techo_para_que_el_json_no_se_corte(): void
    {
        $p = IaSupervisor::parametros('claude-sonnet-5-5', $this->base(['thinking' => ['type' => 'enabled', 'budget_tokens' => 500]]), 'medium', true);

        $this->assertArrayNotHasKey('thinking', $p, 'adaptive es el default en 5.x; budget_tokens es 400');
        $this->assertSame('medium', $p['output_config']['effort']);
        $this->assertGreaterThanOrEqual(12000, $p['max_tokens']);

        $bajo = IaSupervisor::parametros('claude-haiku-5-5', $this->base(), 'low', true);
        $this->assertGreaterThanOrEqual(8000, $bajo['max_tokens']);
    }

    public function test_opus_55_no_puede_apagar_el_razonamiento(): void
    {
        $p = IaSupervisor::parametros('claude-opus-5-5', $this->base(), 'low', false);

        $this->assertArrayNotHasKey('thinking', $p);
        $this->assertGreaterThanOrEqual(8000, $p['max_tokens']);
    }

    public function test_opus_47_queda_como_hoy_sin_temperatura_sin_razonamiento_ni_esfuerzo(): void
    {
        $p = IaSupervisor::parametros('claude-opus-4-7', $this->base(), 'medium', true);

        $this->assertArrayNotHasKey('temperature', $p);
        $this->assertArrayNotHasKey('thinking', $p);
        $this->assertArrayNotHasKey('output_config', $p);
        $this->assertSame(300, $p['max_tokens']);
    }

    public function test_sonnet_46_conserva_temperatura_cero_como_hoy(): void
    {
        $p = IaSupervisor::parametros('claude-sonnet-4-6', $this->base(), 'low', false);

        $this->assertSame(0, $p['temperature']);
        $this->assertArrayNotHasKey('output_config', $p);
        $this->assertArrayNotHasKey('thinking', $p);
    }

    public function test_el_system_prompt_largo_va_a_la_cache_y_el_corto_no(): void
    {
        $largo = str_repeat('Perfil de decisiones de Irving. ', 80);
        $p = IaSupervisor::parametros('claude-haiku-5-5', $this->base(['system' => $largo]), 'low', false, true);

        $this->assertSame([[
            'type' => 'text', 'text' => $largo, 'cache_control' => ['type' => 'ephemeral'],
        ]], $p['system']);

        $corto = IaSupervisor::parametros('claude-haiku-5-5', $this->base(), 'low', false, true);
        $this->assertSame('corto', $corto['system']);

        $sinCache = IaSupervisor::parametros('claude-haiku-5-5', $this->base(['system' => $largo]), 'low', false, false);
        $this->assertSame($largo, $sinCache['system']);
    }

    public function test_texto_ignora_bloques_de_razonamiento(): void
    {
        $texto = IaSupervisor::texto(['stop_reason' => 'end_turn', 'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'x'],
            ['type' => 'text', 'text' => '{"veredicto":'],
            ['type' => 'text', 'text' => '"escala"}'],
        ]]);

        $this->assertSame('{"veredicto":"escala"}', $texto);
    }

    public function test_un_rechazo_nunca_se_lee_como_veredicto(): void
    {
        $this->expectException(RespuestaNoConcluyente::class);
        $this->expectExceptionMessage('cyber');

        IaSupervisor::texto(['stop_reason' => 'refusal', 'stop_details' => ['category' => 'cyber'],
            'content' => [['type' => 'text', 'text' => '{"veredicto":"autoriza"}']]]);
    }

    public function test_solo_opus_cuenta_como_modelo_caro(): void
    {
        $this->assertTrue(IaSupervisor::esCaro('claude-opus-4-7'));
        $this->assertTrue(IaSupervisor::esCaro('claude-opus-5-5'));
        $this->assertFalse(IaSupervisor::esCaro('claude-sonnet-5-5'));
        $this->assertFalse(IaSupervisor::esCaro('claude-haiku-5-5'));
    }

    public function test_costo_incluye_cache_y_tarifa_larga_de_haiku(): void
    {
        // Sonnet 5.5: 1000 entrada × $2/M + 2000 escritas 5 min × 1.25 + 4000 leídas × 0.1 + 500 salida × $10/M
        $usd = ClaudeApiClient::costoUsd([
            'input_tokens' => 1000, 'cache_creation_input_tokens' => 2000, 'cache_read_input_tokens' => 4000,
            'output_tokens' => 500,
        ], 'claude-sonnet-5-5');
        $this->assertEqualsWithDelta(0.002 + 0.005 + 0.0008 + 0.005, $usd, 1e-9);

        // Escritura en la caché de 1 h: 2×.
        $hora = ClaudeApiClient::costoUsd([
            'input_tokens' => 0, 'cache_creation_input_tokens' => 1000, 'output_tokens' => 0,
            'cache_creation' => ['ephemeral_5m_input_tokens' => 0, 'ephemeral_1h_input_tokens' => 1000],
        ], 'claude-sonnet-5-5');
        $this->assertEqualsWithDelta(0.004, $hora, 1e-9);

        // Haiku 5.5 arriba de 100K tokens de prompt: 5×.
        $corto = ClaudeApiClient::costoUsd(['input_tokens' => 100000, 'output_tokens' => 1000], 'claude-haiku-5-5');
        $largo = ClaudeApiClient::costoUsd(['input_tokens' => 100001, 'output_tokens' => 1000], 'claude-haiku-5-5');
        $this->assertEqualsWithDelta(0.01 + 0.0005, $corto, 1e-9);
        $this->assertEqualsWithDelta((100001 * 0.0000001 + 1000 * 0.0000005) * 5, $largo, 1e-9);
    }
}
