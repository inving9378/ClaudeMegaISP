<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\FronterasService;
use App\Modules\Addons\Roadmap\Services\JarvisService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;

/**
 * CANDADO DE REGRESIÓN — Fase 4 de #9990210 (item #9990247).
 *
 * `JarvisService::fronteraDuraDeItemDetalle()` sólo consulta `MencionFrontera::retiene()` —el
 * ablandamiento que deja pasar una MENCIÓN de `produccion`/`borrar_datos`— DENTRO del bloque
 * `if ($item->frontera_valvula === 'mencion')`. `MencionFronteraDuraTest` ya vigila el HELPER puro;
 * lo que NINGÚN test cubría hasta ahora es que esa llamada siga encerrada en ese bloque: si alguien
 * la moviera afuera (refactor, merge, copy-paste), el ablandamiento se filtraría también a las
 * ACCIONES (`frontera_valvula === 'accion'`) sin que nada lo notara.
 *
 * Este candado bootea Laravel real (vía `CreatesApplication`, NUNCA `Tests\TestCase`: ese `setUp()`
 * corre `migrate:fresh --seed`, que este test no necesita — no guarda nada, `RoadmapItem` es un
 * objeto en memoria) para poder llamar al método REAL con los términos REALES de
 * `FronterasService::mapa()` — nunca términos inventados a mano, que es justo lo que pide la
 * descripción del item: si el catálogo de términos cambia algún día desde la Torre, este test lo
 * sigue usando tal cual, en vez de quedarse probando una lista vieja.
 */
class AccionFronteraDuraNuncaAflojaTest extends BaseTestCase
{
    use CreatesApplication;

    /** Las 4 categorías de frontera dura vigentes (`config/circuito.php` → `jarvis.escalamiento`). */
    private const CATEGORIAS = ['dinero', 'credenciales', 'produccion', 'borrar_datos'];

    /**
     * Arma un texto que dispara `$categoria` de verdad, usando un término REAL y ACTIVO tomado de
     * `FronterasService::mapa()` — nunca un término copiado a mano. Se auto-verifica con el propio
     * detector antes de devolverlo, así que si algún término deja de disparar (cambia el catálogo,
     * queda mal anclado, etc.) el test falla con un mensaje claro en vez de dar un falso positivo.
     */
    private function textoQueDisparaLaCategoria(FronterasService $fronteras, string $categoria): array
    {
        $mapa = $fronteras->mapa();

        $this->assertArrayHasKey(
            $categoria,
            $mapa,
            "La categoría «{$categoria}» desapareció de FronterasService::mapa(); este candado necesita las 4."
        );

        $cfg = $mapa[$categoria];

        $this->assertTrue($cfg['activa'], "La categoría «{$categoria}» está inactiva; no sirve para probar retención.");
        $this->assertNotSame(
            'avisar',
            $cfg['efecto'],
            "La categoría «{$categoria}» está en modo «avisar» (no retiene a nadie); no sirve para este candado."
        );

        foreach ($cfg['terminos'] as $t) {
            if (! $t['activo']) {
                continue;
            }

            $texto = "Item de prueba que trabaja con {$t['termino']} en su alcance.";

            if ($fronteras->detectar($texto)['categoria'] === $categoria) {
                return [$texto, (string) $t['termino']];
            }
        }

        $this->fail("Ningún término activo de «{$categoria}» disparó en un texto de prueba; revisar FronterasService::mapa().");
    }

    /**
     * El caso base: con la configuración REAL de `mencion_retiene_categorias` (la del `.env`/default
     * de este entorno), una ACCIÓN retiene en las 4 categorías. `produccion` y `borrar_datos` NO
     * están en esa lista por defecto (ver `MencionFronteraDuraTest`) — si el bug que este candado
     * vigila existiera, serían justo esas dos las que se colarían.
     */
    public function test_una_accion_retiene_en_las_4_categorias_con_la_config_vigente(): void
    {
        $fronteras = app(FronterasService::class);
        $jarvis    = app(JarvisService::class);

        foreach (self::CATEGORIAS as $categoria) {
            [$texto, $termino] = $this->textoQueDisparaLaCategoria($fronteras, $categoria);

            $item = new RoadmapItem([
                'title'            => $texto,
                'description'      => '',
                'prompt'           => '',
                'frontera_valvula' => 'accion',
            ]);

            $detalle = $jarvis->fronteraDuraDeItemDetalle($item);

            $this->assertSame(
                $categoria,
                $detalle['categoria'],
                "Una ACCIÓN que dispara «{$termino}» ({$categoria}) dejó de retener — "
                . 'fronteraDuraDeItemDetalle() devolvió ' . json_encode($detalle, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * La prueba de que el candado vigila lo correcto: se VACÍA `mencion_retiene_categorias` a
     * propósito (el escenario donde una MENCIÓN dejaría pasar TODO, incluidas `dinero` y
     * `credenciales`) y una ACCIÓN sigue reteniendo en las 4 categorías igual. Si algún día la
     * llamada a `MencionFrontera::retiene()` se moviera fuera del bloque `mencion`, esta prueba es
     * la que lo atraparía primero: con la lista vacía, cualquier fuga se vería inmediatamente.
     */
    public function test_una_accion_retiene_aunque_mencion_retiene_categorias_este_vacia(): void
    {
        config(['circuito.mencion_retiene_categorias' => []]);

        $fronteras = app(FronterasService::class);
        $jarvis    = app(JarvisService::class);

        foreach (self::CATEGORIAS as $categoria) {
            [$texto, $termino] = $this->textoQueDisparaLaCategoria($fronteras, $categoria);

            $item = new RoadmapItem([
                'title'            => $texto,
                'description'      => '',
                'prompt'           => '',
                'frontera_valvula' => 'accion',
            ]);

            $detalle = $jarvis->fronteraDuraDeItemDetalle($item);

            $this->assertSame(
                $categoria,
                $detalle['categoria'],
                "Con mencion_retiene_categorias=[] una ACCIÓN que dispara «{$termino}» ({$categoria}) dejó de "
                . 'retener — fronteraDuraDeItemDetalle() devolvió ' . json_encode($detalle, JSON_UNESCAPED_UNICODE)
            );
        }
    }
}
