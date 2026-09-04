<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Support\GraciaDeArranque;
use PHPUnit\Framework\TestCase; // TestCase PURO: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DE LA GRACIA DE ARRANQUE (incidente 2026-08-28).
 *
 * ⚠️ TestCase PURO de PHPUnit a propósito, igual que `FrenoFueraDeLaBaseTest`: `Tests\TestCase`
 * corre `migrate:fresh --seed` y este repo comparte base con el entorno de trabajo. `GraciaDeArranque`
 * es PHP sin framework justamente para poder probarse así.
 *
 * Lo que se fija aquí son las dos mitades que tienen que convivir: que un reinicio deje de frenar
 * el circuito, y que eso NO se convierta en una excusa para ignorar una base caída de verdad.
 */
class GraciaDeArranqueTest extends TestCase
{
    /** El caso del 28-ago: freno 66 s después del boot, con la base sana. Eso ya no frena. */
    public function test_dentro_de_la_ventana_hay_gracia(): void
    {
        $this->assertTrue(
            GraciaDeArranque::enGracia(66.0, 180),
            'Un fallo de conexión a los 66 s del boot debe leerse como «la base aún arranca». '
            . 'Sin esto, CADA reinicio del servidor vuelve a frenar el circuito.'
        );
    }

    /** Pasada la ventana ya no hay excusa: la base lleva rato sin responder y el freno debe caer. */
    public function test_fuera_de_la_ventana_no_hay_gracia(): void
    {
        $this->assertFalse(GraciaDeArranque::enGracia(181.0, 180));
        $this->assertFalse(
            GraciaDeArranque::enGracia(86400.0, 180),
            'Con el box arriba un día, «no me puedo conectar» es un problema real, no un arranque.'
        );
    }

    /** Borde exacto: la gracia es estricta (`<`), no inclusiva. */
    public function test_el_borde_exacto_ya_esta_fuera(): void
    {
        $this->assertFalse(GraciaDeArranque::enGracia(180.0, 180));
    }

    /**
     * FAIL-CLOSED. Si no se sabe cuánto lleva arriba el box, o si alguien pone la gracia en 0/negativa,
     * NO hay gracia: se conserva el comportamiento anterior (frenar). Un error leyendo `/proc/uptime`
     * nunca puede volverse permiso para ignorar una base caída.
     */
    public function test_sin_dato_de_uptime_no_hay_gracia(): void
    {
        $this->assertFalse(GraciaDeArranque::enGracia(null, 180));
    }

    public function test_gracia_en_cero_restaura_el_comportamiento_anterior(): void
    {
        $this->assertFalse(GraciaDeArranque::enGracia(1.0, 0));
        $this->assertFalse(GraciaDeArranque::enGracia(1.0, -5));
    }

    /** `/proc/uptime` = «segundos arriba» + «segundos ociosos». Sólo cuenta el primero. */
    public function test_lee_el_primer_numero_de_proc_uptime(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'uptime');
        file_put_contents($ruta, "3456.78 12345.67\n");

        $this->assertSame(3456.78, GraciaDeArranque::uptimeSegundos($ruta));

        unlink($ruta);
    }

    public function test_uptime_ilegible_o_basura_devuelve_null(): void
    {
        $this->assertNull(GraciaDeArranque::uptimeSegundos('/no/existe/uptime'));

        $ruta = tempnam(sys_get_temp_dir(), 'uptime');
        file_put_contents($ruta, "no-soy-un-numero\n");
        $this->assertNull(GraciaDeArranque::uptimeSegundos($ruta));
        unlink($ruta);
    }

    /**
     * EL CANDADO QUE IMPORTA: la gracia sólo cubre «no pude medir». La rama de «medí y las tablas
     * cayeron» —la defensa real de #228— tiene que seguir frenando sin consultar uptime alguno.
     * Se comprueba sobre el CÓDIGO FUENTE, mismo patrón que `FrenoFueraDeLaBaseTest`.
     */
    public function test_la_caida_de_tablas_sigue_frenando_sin_gracia(): void
    {
        $src = (string) file_get_contents(
            __DIR__ . '/../../../../../app/Modules/Addons/Roadmap/Console/JarvisVigilarCommand.php'
        );

        $this->assertMatchesRegularExpression(
            '/\$escalon = \$caida > 50 \? \'critico\'/',
            $src,
            'La rama de caída de tablas perdió su escalón crítico: una base vaciada dejaría de frenar.'
        );

        $this->assertMatchesRegularExpression(
            '/if \(\$tablas === 0\) \{.*?\'escalon\' => \'critico\'/s',
            $src,
            'Cero tablas debe seguir siendo crítico sin pasar por la gracia de arranque.'
        );

        $this->assertStringNotContainsString(
            "GraciaDeArranque::enGracia(\$uptime, \$gracia) ? 'arranque' : 'ok'",
            $src,
            'La gracia jamás debe producir un `ok`: un chequeo que no midió no puede reportar salud.'
        );
    }
}
