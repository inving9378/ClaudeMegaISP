<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DEL MODO SOLO-LECTURA DEL PANEL — el camino que nadie ejecuta.
 *
 * Decisión de Irving (2026-08-19): `torre.config.view` **no se otorga** a los ~15 roles de la
 * operación del ISP. La política de automatización de un circuito de desarrollo no es información
 * que necesiten, y repartir un permiso «por si acaso» es como se erosiona un modelo de permisos.
 *
 * Consecuencia: hoy **nadie tiene `.view` sin `.edit`**, así que el modo solo-lectura no lo ejerce
 * nadie. Y un camino que nadie ejecuta se rompe en silencio — que es literalmente el destrabe otra
 * vez, en versión pequeña. Este test lo mantiene vivo mientras duerme.
 *
 * Complemento en runtime: `php artisan circuito:verificar-solo-lectura`, que crea un rol temporal
 * con sólo `.view` dentro de una transacción, ejerce los dos endpoints y hace rollback.
 */
class PanelSoloLecturaTest extends TestCase
{
    private function fuente(string $rel): string
    {
        $f = dirname(__DIR__, 5) . '/' . $rel;
        $this->assertFileExists($f, "No encontré {$rel}.");

        return file_get_contents($f);
    }

    /** Los dos endpoints exigen permisos DISTINTOS: leer no habilita escribir. */
    public function test_los_endpoints_exigen_permisos_distintos(): void
    {
        $src = $this->fuente('app/Modules/Addons/Roadmap/Controllers/RoadmapController.php');

        $get  = $this->metodo($src, 'torreConfig');
        $post = $this->metodo($src, 'torreConfigGuardar');

        $this->assertStringContainsString("authorize('torre.config.view')", $get,
            'El GET de la configuración dejó de exigir `torre.config.view`.');
        $this->assertStringContainsString("authorize('torre.config.edit')", $post,
            'El POST dejó de exigir `torre.config.edit`: cualquiera que pueda VER podría GUARDAR. '
            . 'Ese es exactamente el modo que este candado protege.');
        $this->assertStringNotContainsString("authorize('torre.config.view')", $post,
            'El POST exige el permiso de lectura: leer no puede habilitar escribir.');
    }

    /** El servidor —no la UI— decide si se puede editar, y lo manda como dato. */
    public function test_el_servidor_manda_puede_editar(): void
    {
        $get = $this->metodo(
            $this->fuente('app/Modules/Addons/Roadmap/Controllers/RoadmapController.php'),
            'torreConfig'
        );

        $this->assertMatchesRegularExpression("/'puede_editar'\s*=>.*can\('torre\.config\.edit'\)/s", $get,
            'El GET dejó de calcular `puede_editar` en el SERVIDOR. Si la UI lo dedujera por su '
            . 'cuenta, bastaría abrir DevTools para habilitar los controles — y el POST es lo único '
            . 'que realmente protege, pero el panel estaría mintiendo sobre lo que se puede hacer.');
    }

    /**
     * Los controles se DESHABILITAN, no se ocultan. Que todos vean la política vigente es parte del
     * valor: un panel que se esconde de quien no puede editarlo deja a media empresa sin saber bajo
     * qué régimen corre el circuito.
     */
    public function test_los_controles_se_deshabilitan_no_se_ocultan(): void
    {
        $vue = $this->fuente('resources/js/components/module/releases/torre-control/TorreConfigPanel.vue');

        $this->assertStringContainsString(':disabled="!puedeEditar"', $vue,
            'Los controles del panel ya no se deshabilitan con `puedeEditar`.');

        // El ÚNICO `v-if="puedeEditar"` permitido es el del botón Guardar: ése es la ACCIÓN, no un
        // control de política. Cualquier otro significaría un control oculto — y el modo solo-lectura
        // muestra el panel COMPLETO y deshabilitado, no recortado.
        $ocultos = substr_count($vue, 'v-if="puedeEditar"');
        $this->assertSame(1, $ocultos,
            "Hay {$ocultos} elementos tras `v-if=\"puedeEditar\"` y sólo debería haber uno (el botón "
            . 'Guardar). Ocultar un control de política a quien sólo puede mirar cambia el trato: el '
            . 'panel deja de decirle bajo qué régimen corre el circuito.');

        $this->assertStringContainsString('v-if="puedeEditar" class="tcfg-btn tcfg-primary"', $vue,
            'El botón Guardar debe existir sólo para quien puede editar.');

        $this->assertStringContainsString('v-if="!puedeEditar"', $vue,
            'Se quitó el aviso de «solo lectura»: sin él, alguien puede pensar que el panel está roto.');
    }

    /** Cuerpo fuente de un método, balanceando llaves. */
    private function metodo(string $src, string $nombre): string
    {
        $pos = strpos($src, "function {$nombre}(");
        $this->assertNotFalse($pos, "No encontré `{$nombre}()` — ¿se renombró?");

        $inicio = strpos($src, '{', $pos);
        $depth  = 0;
        for ($i = $inicio, $len = strlen($src); $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                return substr($src, $inicio, $i - $inicio + 1);
            }
        }

        $this->fail("No pude delimitar `{$nombre}()`.");
    }
}
