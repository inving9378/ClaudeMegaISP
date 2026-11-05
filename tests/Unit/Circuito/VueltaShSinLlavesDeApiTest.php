<?php

namespace Tests\Unit\Circuito;

use PHPUnit\Framework\TestCase;

/**
 * Las terminales del circuito deben gastar la suscripción, nunca la API. En modo `claude -p` una
 * llave de API presente en el entorno SIEMPRE gana sobre el login de la suscripción, y el `.env`
 * de la app define ANTHROPIC_API_KEY y CLAUDE_API_KEY: sin el `unset` de vuelta.sh, cada vuelta
 * se cobraría por API sin ningún aviso (#1077).
 */
final class VueltaShSinLlavesDeApiTest extends TestCase
{
    private const LLAVES = ['ANTHROPIC_API_KEY', 'ANTHROPIC_AUTH_TOKEN', 'CLAUDE_API_KEY'];

    private function lineas(): array
    {
        return file(dirname(__DIR__, 3) . '/deploy/circuito/vuelta.sh', FILE_IGNORE_NEW_LINES);
    }

    public function test_vuelta_sh_borra_las_llaves_de_api_antes_de_lanzar_claude(): void
    {
        $unset = $claude = null;
        foreach ($this->lineas() as $n => $l) {
            $codigo = ltrim($l);
            if ($codigo === '' || $codigo[0] === '#') {
                continue;
            }
            if ($unset === null && preg_match('/^unset\b/', $codigo)
                && count(array_filter(self::LLAVES, fn ($k) => preg_match('/\b' . $k . '\b/', $codigo))) === count(self::LLAVES)) {
                $unset = $n;
            }
            if ($claude === null && preg_match('/\bclaude\s+-p\b/', $codigo)) {
                $claude = $n;
            }
        }

        $this->assertNotNull($unset, 'vuelta.sh ya no borra ' . implode(', ', self::LLAVES)
            . ': las terminales se cobrarían por API en lugar de la suscripción.');
        $this->assertNotNull($claude, 'No se encontró la llamada a claude -p en vuelta.sh.');
        $this->assertLessThan($claude, $unset, 'El unset de las llaves debe ir antes de lanzar claude -p.');
    }

    public function test_vuelta_sh_no_vuelve_a_poner_una_llave_de_api(): void
    {
        foreach ($this->lineas() as $n => $l) {
            $codigo = ltrim($l);
            if ($codigo === '' || $codigo[0] === '#') {
                continue;
            }
            foreach (self::LLAVES as $k) {
                $this->assertDoesNotMatchRegularExpression('/\b(export\s+)?' . $k . '=/', $codigo,
                    'vuelta.sh línea ' . ($n + 1) . " vuelve a definir {$k}: las terminales se cobrarían por API.");
            }
        }
    }
}
