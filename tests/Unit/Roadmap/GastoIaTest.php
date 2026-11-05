<?php

namespace Tests\Unit\Roadmap;

use App\Modules\Addons\Roadmap\Support\GastoIa;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * #1076 — `circuito:gasto-ia` suma el log `claude` por pieza y modelo. Las líneas nuevas traen su
 * costo; las de antes de #1076 (sin pieza ni costo) se cuentan como «(sin etiqueta)» y su costo se
 * recalcula del `usage`, para poder comparar antes y después.
 */
final class GastoIaTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/gasto-ia-' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/claude-api-2026-10-29.log', implode("\n", [
            // Vieja: sin pieza ni costo → se recalcula (Opus 4.7: 1000 × $5/M + 100 × $25/M = $0.0075).
            '[2026-10-29 10:00:00] local.DEBUG: Claude API call {"model":"claude-opus-4-7","status":200,"usage":{"input_tokens":1000,"output_tokens":100}}',
            // Error: cuenta como llamada, no como costo.
            '[2026-10-29 10:01:00] local.DEBUG: Claude API call {"model":"claude-opus-4-7","status":401,"usage":null}',
            '[2026-10-29 10:02:00] local.ERROR: Claude API error 401: invalid x-api-key',
        ]) . "\n");
        file_put_contents($this->dir . '/claude-api-2026-10-30.log', implode("\n", [
            '[2026-10-30 10:00:00] local.DEBUG: Claude API call {"model":"claude-haiku-5-5","status":200,"usage":{"input_tokens":1500,"output_tokens":80,"output_tokens_details":{"thinking_tokens":0}},"pieza":"circuito.valvula","item":7,"stop_reason":"end_turn","costo_usd":0.00019,"costo_mxn":0.0036}',
            '[2026-10-30 10:05:00] local.DEBUG: Claude API call {"model":"claude-haiku-5-5","status":200,"usage":{"input_tokens":1500,"output_tokens":80},"pieza":"circuito.valvula","item":8,"stop_reason":"end_turn","costo_usd":0.00019,"costo_mxn":0.0036}',
            '[2026-10-30 10:06:00] local.DEBUG: Claude API call {"model":"claude-sonnet-5-5","status":200,"usage":{"input_tokens":200,"cache_read_input_tokens":4000,"output_tokens":300},"pieza":"circuito.revisor","item":9,"stop_reason":"end_turn","costo_usd":0.0042,"costo_mxn":0.0798}',
        ]) . "\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function test_suma_por_pieza_y_modelo_con_el_costo_de_cada_linea(): void
    {
        $filas = GastoIa::sumar(GastoIa::lineas($this->dir, '2026-10-29', '2026-10-30'), false, 19.0);

        $valvula = $filas['circuito.valvula|claude-haiku-5-5'];
        $this->assertSame(2, $valvula['llamadas']);
        $this->assertSame(2, $valvula['ok']);
        $this->assertSame(3000, $valvula['entrada']);
        $this->assertEqualsWithDelta(0.00038, $valvula['usd'], 1e-9);
        $this->assertEqualsWithDelta(0.00038 * 19, $valvula['mxn'], 1e-4); // se redondea a 4 decimales

        $revisor = $filas['circuito.revisor|claude-sonnet-5-5'];
        $this->assertSame(4000, $revisor['cache_lectura']);
        $this->assertEqualsWithDelta(0.0042, $revisor['usd'], 1e-9);
    }

    public function test_las_lineas_viejas_salen_sin_etiqueta_con_costo_recalculado(): void
    {
        $filas = GastoIa::sumar(GastoIa::lineas($this->dir, '2026-10-29', '2026-10-29'), false, 19.0);

        $vieja = $filas[GastoIa::SIN_ETIQUETA . '|claude-opus-4-7'];
        $this->assertSame(2, $vieja['llamadas']);
        $this->assertSame(1, $vieja['ok']);
        $this->assertSame(1, $vieja['errores']);
        $this->assertEqualsWithDelta(0.0075, $vieja['usd'], 1e-9);
        $this->assertCount(1, $filas, 'la línea de ERROR no es una llamada');
    }

    public function test_por_dia_separa_las_fechas_y_un_rango_sin_logs_no_truena(): void
    {
        $filas = GastoIa::sumar(GastoIa::lineas($this->dir, '2026-10-29', '2026-10-30'), true, 19.0);
        $this->assertArrayHasKey('2026-10-30|circuito.valvula|claude-haiku-5-5', $filas);
        $this->assertArrayHasKey('2026-10-29|' . GastoIa::SIN_ETIQUETA . '|claude-opus-4-7', $filas);

        $this->assertSame([], GastoIa::sumar(GastoIa::lineas($this->dir, '2026-01-01', '2026-01-03'), false, 19.0));
        $this->assertNull(GastoIa::parsear('[2026-10-30] local.DEBUG: otra cosa {"a":1}'));
    }
}
