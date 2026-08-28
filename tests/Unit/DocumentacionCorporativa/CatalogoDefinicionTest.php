<?php

namespace Tests\Unit\DocumentacionCorporativa;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Seeders\CatalogoSeeder;
use PHPUnit\Framework\TestCase; // El catálogo es un array literal: se valida sin BD.
use ReflectionMethod;

/**
 * El catálogo sembrado es la columna vertebral del módulo: 14 apartados y 139
 * conceptos. Si alguien edita la definición y descuadra la cuenta, o repite un
 * slug (que es la llave de idempotencia del seeder), esto revienta aquí y no en
 * el tablero de completitud, donde se vería como un porcentaje raro.
 */
class CatalogoDefinicionTest extends TestCase
{
    private function definicion(): array
    {
        $metodo = new ReflectionMethod(CatalogoSeeder::class, 'definicion');
        $metodo->setAccessible(true);

        return $metodo->invoke(new CatalogoSeeder());
    }

    public function test_hay_exactamente_14_apartados_con_las_claves_romanas(): void
    {
        $definicion = $this->definicion();

        $this->assertCount(14, $definicion);
        $this->assertSame(DcApartado::CLAVES, array_keys($definicion));
    }

    public function test_hay_exactamente_139_conceptos(): void
    {
        $total = array_sum(array_map(
            fn (array $a) => count($a['conceptos']),
            $this->definicion()
        ));

        $this->assertSame(CatalogoSeeder::TOTAL_CONCEPTOS, $total);
        $this->assertSame(139, $total);
    }

    public function test_no_hay_slugs_repetidos(): void
    {
        $slugs = [];
        foreach ($this->definicion() as $apartado) {
            foreach ($apartado['conceptos'] as $c) {
                $slugs[] = $c['s'];
            }
        }

        $this->assertSame(
            count($slugs),
            count(array_unique($slugs)),
            'Los slugs son la llave de idempotencia del seeder: uno repetido hace que un '
            . 'concepto pise a otro en cada corrida.'
        );
    }

    public function test_todo_concepto_declara_un_resolvedor_valido(): void
    {
        foreach ($this->definicion() as $clave => $apartado) {
            foreach ($apartado['conceptos'] as $c) {
                $this->assertContains(
                    $c['t'],
                    DcConcepto::RESOLVEDORES,
                    "El concepto '{$c['s']}' (apartado {$clave}) declara un resolvedor desconocido."
                );
                $this->assertContains($c['x'] ?? 'interna', DcConcepto::CONFIDENCIALIDADES);
            }
        }
    }

    public function test_los_resolvedores_de_datos_declaran_de_donde_leen(): void
    {
        foreach ($this->definicion() as $clave => $apartado) {
            foreach ($apartado['conceptos'] as $c) {
                if (in_array($c['t'], ['sistema', 'grafica'], true)) {
                    $this->assertArrayHasKey('fuente', $c['c'] ?? [], "Falta 'fuente' en {$c['s']}.");
                }
                if ($c['t'] === 'inventario') {
                    $this->assertArrayHasKey('tabla', $c['c'] ?? [], "Falta 'tabla' en {$c['s']}.");
                }
            }
        }
    }

    public function test_el_apartado_xi_nunca_pide_guardar_un_secreto(): void
    {
        // Bancos: el campo de credencial se rinde como constante, nunca como dato.
        // Si algún concepto de XI declarara una columna de secreto, aquí se ve.
        $prohibidas = ['password', 'contrasena', 'contraseña', 'token', 'clabe_completa', 'pan', 'llave_privada'];

        foreach ($this->definicion()['XI']['conceptos'] as $c) {
            $config = json_encode($c['c'] ?? [], JSON_UNESCAPED_UNICODE);
            foreach ($prohibidas as $palabra) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $palabra,
                    (string) $config,
                    "El concepto '{$c['s']}' referencia un secreto en su config."
                );
            }
        }
    }
}
