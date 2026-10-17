<?php

namespace Tests\Unit\Services;

use App\Services\Finance\Billing\PrefacturaPlantillaUpdater as Updater;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * Transiciones verificadas de la plantilla «Prefactura.» (FAC-03, item #508). PURA: usa el HTML
 * original de producción embebido en la migración 2026_10_16_170000 (exacto byte a byte, CRLF) y
 * transformar(), que no toca la BD.
 */
class PrefacturaPlantillaUpdaterTest extends TestCase
{
    private const NOTA = '<div style="font-size:6.8pt;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6E7C8C;margin:1px 0 2px 0;">${data.documento_nota}</div>';

    private static function original(): string
    {
        $migracion = require __DIR__ . '/../../../database/migrations/2026_10_16_170000_ajusta_plantilla_prefactura_para_provider.php';
        $b64 = (new ReflectionClassConstant($migracion, 'ORIGINAL_GZ_B64'))->getValue();

        return gzuncompress(base64_decode(preg_replace('/\s+/', '', $b64)));
    }

    public function test_el_original_embebido_es_el_de_produccion(): void
    {
        $this->assertSame(Updater::HASH_ORIGINAL, sha1(self::original()));
    }

    public function test_desde_cada_version_conocida_se_llega_al_mismo_destino(): void
    {
        $u = new Updater();
        $final = $u->transformar(self::original());
        $this->assertSame([], $final['faltan']);
        $this->assertCount(6, $final['aplicados']);
        $this->assertSame(Updater::HASH_APLICADA, sha1($final['html']));

        $v2 = str_replace('${data.leyenda_legal}${data.aviso_privacidad}', '${data.leyenda_legal}', $final['html']);
        $this->assertSame(Updater::HASH_APLICADA_V2, sha1($v2), 'la versión 1-5 (dev tras #505)');
        $this->assertSame(Updater::HASH_APLICADA, sha1($u->transformar($v2)['html']));

        $v1 = str_replace(self::NOTA, '', $v2);
        $this->assertSame(Updater::HASH_APLICADA_V1, sha1($v1), 'la versión 1-4 (dev tras #480)');
        $this->assertSame(Updater::HASH_APLICADA, sha1($u->transformar($v1)['html']));
    }

    public function test_es_idempotente(): void
    {
        $u = new Updater();
        $final = $u->transformar(self::original())['html'];
        $otraVez = $u->transformar($final);

        $this->assertSame($final, $otraVez['html']);
        $this->assertSame([], $otraVez['aplicados']);
        $this->assertCount(6, $otraVez['ya_estaban']);
    }

    public function test_el_aviso_queda_dentro_del_pie_legal_y_una_sola_vez(): void
    {
        $html = (new Updater())->transformar(self::original())['html'];

        $this->assertSame(1, substr_count($html, '${data.aviso_privacidad}'));
        $this->assertMatchesRegularExpression('/<div class="legal">\s*\$\{data\.leyenda_legal\}\$\{data\.aviso_privacidad\}\s*<\/div>/', $html);
    }

    public function test_html_personalizado_sin_anclas_no_se_completa(): void
    {
        $t = (new Updater())->transformar('<html><body><p>Plantilla editada a mano</p></body></html>');

        $this->assertSame([], $t['aplicados']);
        $this->assertCount(6, $t['faltan']);
    }

    public function test_las_versiones_ajustadas_las_reconoce_el_down(): void
    {
        $this->assertSame(
            [Updater::HASH_APLICADA, Updater::HASH_APLICADA_V2, Updater::HASH_APLICADA_V1],
            Updater::HASHES_AJUSTADAS
        );
    }
}
