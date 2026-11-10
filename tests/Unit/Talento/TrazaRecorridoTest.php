<?php

namespace Tests\Unit\Talento;

use App\Modules\Addons\Talento\Support\TrazaRecorrido as T;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Item #1311 — lectura y cálculo de la ruta de un recorrido. Prueba pura: sin BD ni contenedor.
 * Rutas sintéticas (RecorridoFixtures), horas con «Z» para no depender de la zona.
 */
class TrazaRecorridoTest extends TestCase
{
    private const T0 = 1789056000;

    public function test_detecta_el_formato_por_el_contenido(): void
    {
        $p = RecorridoFixtures::linea(3, self::T0);
        $this->assertSame('gpx', T::formatoDe(RecorridoFixtures::gpx($p), 'ruta.txt'));
        $this->assertSame('kml', T::formatoDe(RecorridoFixtures::kmlTrack($p)));
        $this->assertSame('kmz', T::formatoDe(RecorridoFixtures::kmz(RecorridoFixtures::kmlTrack($p))));
        $this->assertNull(T::formatoDe('hola, no soy una ruta', 'ruta.gpx'));
        $this->assertNull(T::formatoDe("\xFF\xD8\xFF\xE0", 'foto.jpg'));
    }

    public function test_lee_gpx_kml_con_horas_kml_sin_horas_y_kmz(): void
    {
        $p = RecorridoFixtures::linea(25, self::T0);

        $this->assertSame($p, T::leer(RecorridoFixtures::gpx($p), 'gpx'));
        $this->assertSame($p, T::leer(RecorridoFixtures::kmlTrack($p), 'kml'), 'gx:Track: cada coord con su when');

        $sinHora = array_map(fn ($x) => [$x[0], $x[1], null], $p);
        $this->assertSame($sinHora, T::leer(RecorridoFixtures::kmlLinea($p), 'kml'), 'El Point suelto no entra a la línea');
        $this->assertSame($p, T::leer(RecorridoFixtures::kmz(RecorridoFixtures::kmlTrack($p)), 'kmz'));
    }

    public function test_gpx_de_rutas_y_horas_sin_zona(): void
    {
        $gpx = '<?xml version="1.0"?><gpx><rte><rtept lat="19.4" lon="-99.15"><time>2026-11-10 10:00:00</time></rtept>'
            . '<rtept lat="19.401" lon="-99.15"/></rte></gpx>';
        $puntos = T::leer($gpx, 'gpx');
        $this->assertCount(2, $puntos);
        $this->assertNotNull($puntos[0][2], 'Una hora sin zona se toma en la zona configurada');
        $this->assertNull($puntos[1][2]);
    }

    public function test_descarta_puntos_invalidos(): void
    {
        $gpx = '<gpx><trk><trkseg><trkpt lat="0" lon="0"/><trkpt lat="95" lon="10"/><trkpt lat="abc" lon="1"/>'
            . '<trkpt lat="19.4" lon="-99.15"/></trkseg></trk></gpx>';
        $this->assertSame([[19.4, -99.15, null]], T::leer($gpx, 'gpx'));
    }

    public function test_xml_danado_y_entidades_externas(): void
    {
        try {
            T::leer('<gpx><trk><trkseg><trkpt lat="1" lon="2">', 'gpx');
            $this->fail('Un GPX cortado debe rechazarse');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('dañado', $e->getMessage());
        }

        $xxe = '<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY x SYSTEM "file:///etc/hostname">]>'
            . '<gpx><trk><trkseg><trkpt lat="19.4" lon="-99.15"><name>&x;</name></trkpt></trkseg></trk></gpx>';
        try {
            $puntos = T::leer($xxe, 'gpx');
            $this->assertCount(1, $puntos, 'La entidad externa no se resuelve; el punto sí se lee');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);  // también vale rechazarlo
        }
    }

    public function test_distancia_bbox_y_muestra(): void
    {
        $p = RecorridoFixtures::linea(1001, self::T0, 1, 5.0);
        $this->assertEqualsWithDelta(5000, T::distancia($p), 5);
        $this->assertSame([$p[0][0], $p[0][1], $p[1000][0], $p[1000][1]], T::bbox($p));

        $m = T::muestra($p, 100);
        $this->assertLessThanOrEqual(102, count($m));
        $this->assertSame([0, $p[0][0], $p[0][1], $p[0][2]], $m[0]);
        $this->assertSame(1000, $m[count($m) - 1][0], 'La muestra siempre trae el último punto');
    }

    public function test_simplifica_rectas_y_conserva_esquinas(): void
    {
        $recta = RecorridoFixtures::linea(500, self::T0);
        $this->assertCount(2, T::simplificar($recta, 4));

        // Una «L»: 100 puntos al este y 100 al norte. La esquina se conserva.
        $l = $recta = array_slice(RecorridoFixtures::linea(100, self::T0), 0, 100);
        $esquina = end($l);
        for ($i = 1; $i <= 100; $i++) {
            $l[] = [round($esquina[0] + $i * 5 / 110540, 7), $esquina[1], null];
        }
        $s = T::simplificar($l, 4);
        $this->assertCount(3, $s);
        $this->assertSame([$esquina[0], $esquina[1]], $s[1]);
    }

    public function test_ubica_por_hora_interpolando_y_respeta_la_tolerancia(): void
    {
        $p = RecorridoFixtures::linea(11, self::T0, 60, 100.0);   // un punto por minuto, 100 m

        $this->assertSame([$p[3][0], $p[3][1]], T::ubicarPorHora($p, self::T0 + 180));
        $medio = T::ubicarPorHora($p, self::T0 + 210);
        $this->assertEqualsWithDelta(($p[3][1] + $p[4][1]) / 2, $medio[1], 0.0000002);

        $this->assertSame([$p[0][0], $p[0][1]], T::ubicarPorHora($p, self::T0 - 120), 'Antes del inicio, dentro de la tolerancia');
        $this->assertNull(T::ubicarPorHora($p, self::T0 - 301));
        $this->assertNull(T::ubicarPorHora($p, self::T0 + 600 + 301));
        $this->assertNull(T::ubicarPorHora(array_map(fn ($x) => [$x[0], $x[1], null], $p), self::T0));
    }
}
