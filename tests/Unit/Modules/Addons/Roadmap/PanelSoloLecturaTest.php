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
     *
     * ⚠️ 2026-08-27 (#648) — este candado apuntaba a `TorreConfigPanel.vue`, que era el modal del
     * engrane. Ese modal desapareció: la configuración vive ahora en la pestaña
     * `TorreConfiguracion.vue` y el engrane quedó como atajo (había TRES pantallas mostrando lo
     * mismo). El candado sigue al contenido, no al archivo — lo que se protege es el trato al que
     * sólo puede mirar, y ése no cambió.
     */
    public function test_los_controles_se_deshabilitan_no_se_ocultan(): void
    {
        $vue = $this->fuente('resources/js/components/module/releases/torre-control/TorreConfiguracion.vue');

        $this->assertStringContainsString(':disabled="!puedeEditar"', $vue,
            'Los controles del panel ya no se deshabilitan con `puedeEditar`.');

        // La pantalla nueva NO oculta absolutamente nada: hasta los botones de guardar se pintan
        // deshabilitados. Se admite como mucho UNO (el botón de acción), nunca más — cualquier otro
        // significaría un control de política escondido a quien sólo puede mirar.
        $ocultos = substr_count($vue, 'v-if="puedeEditar"');
        $this->assertLessThanOrEqual(1, $ocultos,
            "Hay {$ocultos} elementos tras `v-if=\"puedeEditar\"` y como mucho debería haber uno (un "
            . 'botón de acción). Ocultar un control de política a quien sólo puede mirar cambia el '
            . 'trato: el panel deja de decirle bajo qué régimen corre el circuito.');

        $this->assertStringContainsString('v-if="!puedeEditar"', $vue,
            'Se quitó el aviso de «solo lectura»: sin él, alguien puede pensar que el panel está roto.');
    }

    /**
     * CANDADO DE LA SUPERFICIE NUEVA (#648) — gobernar las fronteras duras desde una pantalla web
     * sólo es defendible mientras cada escritura exija permiso de edición Y el segundo paso.
     *
     * La confirmación tiene que vivir en el SERVIDOR. Una que sólo exista en el frontend es un
     * adorno: el endpoint sigue estando a un `curl` de distancia, y lo que hay del otro lado es el
     * único control por contenido que no depende de la autodeclaración de un modelo.
     */
    public function test_toda_escritura_de_fronteras_exige_edicion_y_confirmacion(): void
    {
        $src = $this->fuente('app/Modules/Addons/Roadmap/Controllers/TorreFronterasController.php');

        $guarda = $this->metodo($src, 'autorizarEscribir');
        $this->assertStringContainsString("authorize('torre.config.edit')", $guarda,
            'La guarda de escritura dejó de exigir `torre.config.edit`.');
        $this->assertStringContainsString("boolean('confirmado')", $guarda,
            'La guarda de escritura dejó de exigir el segundo paso (`confirmado`) en el SERVIDOR.');

        foreach (['categoria', 'termino', 'valvula', 'techoAutopilot'] as $metodo) {
            $cuerpo = $this->metodo($src, $metodo);
            $this->assertStringContainsString('autorizarEscribir($request)', $cuerpo,
                "El endpoint `{$metodo}` escribe sin pasar por la guarda de edición + confirmación.");
        }

        // El GET pasa por su propia guarda de lectura, y esa guarda pide `.view` — nunca `.edit`:
        // mirar bajo qué régimen corre el circuito no debería requerir poder cambiarlo.
        $this->assertStringContainsString("authorize('torre.config.view')", $this->metodo($src, 'autorizarVer'),
            'La guarda de lectura dejó de exigir `torre.config.view`.');

        $get = $this->metodo($src, 'index');
        $this->assertStringContainsString('autorizarVer()', $get,
            'El GET de fronteras dejó de pasar por la guarda de lectura.');
        $this->assertStringNotContainsString("authorize('torre.config.edit')", $get,
            'El GET exige el permiso de edición: mirar la configuración no debería requerir poder cambiarla.');
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
