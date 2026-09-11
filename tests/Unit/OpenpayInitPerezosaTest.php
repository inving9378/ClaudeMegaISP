<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Candado de regresión: la inicialización de OpenPay debe seguir siendo PEREZOSA.
 *
 * Con la inicialización en `__construct()`, y sin OPENPAY_ID/OPENPAY_PRIVATE_KEY en
 * el .env, toda ruta de Domiciliación respondía HTTP 500 incluso sin sesión, y
 * `php artisan route:list` reventaba entero. La causa es la cadena de inyección por
 * constructor: DomiciliacionController / PortalDomiciliacionController /
 * EnrollmentLinkController reciben DomiciliacionEnrollmentService, que recibe
 * OpenpayService — y Laravel instancia el controlador para leer su middleware
 * (Route::controllerMiddleware()) ANTES de correr el pipeline.
 *
 * Análisis estático del fuente, sin arrancar la app ni tocar MySQL (mismo patrón que
 * CircuitoHardeningConfigShapeTest).
 */
class OpenpayInitPerezosaTest extends TestCase
{
    /**
     * Cuerpo de la clase OpenpayService AISLADO: el archivo declara además
     * OpenpayTransactionException, que sí tiene su propio __construct legítimo.
     */
    private function fuenteServicio(): string
    {
        $ruta = __DIR__.'/../../app/Modules/Addons/PortalCliente/Services/OpenpayService.php';
        $this->assertFileExists($ruta, 'No se encontró OpenpayService.');

        $archivo = (string) file_get_contents($ruta);

        $inicio = strpos($archivo, 'class OpenpayService');
        $this->assertIsInt($inicio, 'No se encontró la declaración de class OpenpayService.');

        $desdeClase = substr($archivo, $inicio);
        $siguiente  = strpos($desdeClase, "\nclass ", 1);

        return $siguiente === false ? $desdeClase : substr($desdeClase, 0, $siguiente);
    }

    public function test_el_constructor_no_inicializa_el_sdk(): void
    {
        $fuente = $this->fuenteServicio();

        $this->assertStringNotContainsString(
            'function __construct',
            $fuente,   // solo el cuerpo de OpenpayService, no las excepciones del mismo archivo
            'OpenpayService volvió a declarar __construct: construirlo debe ser barato y no lanzar, '
            .'o las rutas de Domiciliación vuelven a dar 500 sin credenciales.'
        );
    }

    public function test_existe_el_accesor_perezoso(): void
    {
        $this->assertStringContainsString(
            'private function api(): OpenpayApi',
            $this->fuenteServicio(),
            'Debe existir el accesor perezoso api() que inicializa el SDK en el primer uso.'
        );
    }

    public function test_ningun_metodo_usa_la_propiedad_cruda(): void
    {
        $this->assertStringNotContainsString(
            '$this->api->',
            $this->fuenteServicio(),
            'Uso directo de $this->api->: con inicialización perezosa la propiedad puede ser null. '
            .'Usa siempre $this->api().'
        );
    }

    public function test_la_validacion_de_credenciales_sigue_presente(): void
    {
        $fuente = $this->fuenteServicio();

        $this->assertStringContainsString('Credenciales OpenPay incompletas', $fuente);
        $this->assertStringContainsString("config('openpay.private_key')", $fuente);

        // La guarda debe vivir DENTRO de api(), no fuera: mover la inicialización no puede
        // convertirse en la excusa para dejar de validar antes de cobrar.
        $desdeApi = strstr($fuente, 'private function api(): OpenpayApi');
        $this->assertIsString($desdeApi);
        $this->assertStringContainsString(
            'Credenciales OpenPay incompletas',
            $desdeApi,
            'La validación de credenciales debe seguir dentro de api(), antes de hablar con OpenPay.'
        );
    }
}
