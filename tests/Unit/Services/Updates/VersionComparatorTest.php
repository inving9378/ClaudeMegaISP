<?php

namespace Tests\Unit\Services\Updates;

use App\Services\Updates\VersionComparator;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

/**
 * Tests unitarios de VersionComparator (item roadmap #488).
 * Cubre exactamente el footgun documentado: comparar la fecha DD.MM.YYYY embebida
 * en el tag como texto en vez de como fecha real, y el caso "major.minor manda,
 * la fecha solo desempata".
 * ⚠️ Validar también con tinker (regla del proyecto: no correr el test runner contra la BD).
 */
class VersionComparatorTest extends TestCase
{
    public function test_major_distinto_gana_el_mayor(): void
    {
        $this->assertTrue(VersionComparator::isNewer('V2.0-01.01.2026', 'V1.28-04.08.2026'));
        $this->assertFalse(VersionComparator::isNewer('V1.28-04.08.2026', 'V2.0-01.01.2026'));
    }

    public function test_minor_distinto_mismo_major(): void
    {
        $this->assertTrue(VersionComparator::isNewer('V1.28-04.08.2026', 'V1.25-09.07.2026'));
        $this->assertFalse(VersionComparator::isNewer('V1.25-09.07.2026', 'V1.28-04.08.2026'));
    }

    /** Caso trampa del item: día menor pero versión mayor NO debe engañar la comparación. */
    public function test_no_se_deja_enganar_por_el_dia_cuando_la_version_es_mayor(): void
    {
        $this->assertTrue(VersionComparator::isNewer('V1.28-04.08.2026', 'V1.25-09.07.2026'));
        $this->assertTrue(VersionComparator::isNewer('V1.30-01.01.2027', 'V1.29-28.12.2026'));
    }

    public function test_mismo_major_minor_desempata_por_fecha_real_no_por_texto(): void
    {
        // "28.12.2026" > "04.01.2027" como texto (2 < 4... en realidad "28" > "04" así que
        // el string SÍ "ganaría" mal aquí de otra forma; el punto es que la fecha real manda).
        $this->assertTrue(VersionComparator::isNewer('V1.30-04.01.2027', 'V1.30-28.12.2026'));
        $this->assertFalse(VersionComparator::isNewer('V1.30-28.12.2026', 'V1.30-04.01.2027'));
    }

    public function test_misma_version_no_es_mas_nueva(): void
    {
        $this->assertFalse(VersionComparator::isNewer('V1.28-04.08.2026', 'V1.28-04.08.2026'));
    }

    public function test_sin_version_instalada_siempre_hay_actualizacion(): void
    {
        $this->assertTrue(VersionComparator::isNewer('V1.28-04.08.2026', null));
        $this->assertTrue(VersionComparator::isNewer('V1.28-04.08.2026', ''));
    }

    /** Fecha embebida inválida: no debe tronar, degrada con gracia (usa major.minor). */
    public function test_formato_de_fecha_invalido_no_truena_y_usa_major_minor(): void
    {
        $this->assertTrue(VersionComparator::isNewer('V1.28-99.99.2026', 'V1.25-09.07.2026'));
        $this->assertFalse(VersionComparator::isNewer('V1.25-99.99.2026', 'V1.28-09.07.2026'));
    }

    public function test_parse_extrae_major_minor_y_fecha(): void
    {
        $parsed = VersionComparator::parse('V1.28-04.08.2026');

        $this->assertSame(1, $parsed['major']);
        $this->assertSame(28, $parsed['minor']);
        $this->assertNotNull($parsed['date']);
        $this->assertSame('04.08.2026', $parsed['date']->format('d.m.Y'));
    }
}
