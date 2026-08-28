<?php

namespace Tests\Unit\DocumentacionCorporativa;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use PHPUnit\Framework\TestCase; // TestCase PURO: el estado se deriva en memoria, no toca BD.

/**
 * El estado de vigencia de un documento es un ACCESSOR, no una columna.
 *
 * Esta prueba es la que justifica esa decisión: el mismo documento cambia de
 * `por_vencer` a `vencido` con sólo pasar la medianoche, sin que nadie escriba
 * su fila. Una copia persistida en BD daría la respuesta de ayer.
 */
class EstadoDocumentoTest extends TestCase
{
    private function documento(?string $vigenciaFin): DcDocumento
    {
        $d = new DcDocumento();
        $d->vigencia_fin = $vigenciaFin;

        return $d;
    }

    public function test_sin_fecha_de_vigencia_esta_vigente(): void
    {
        $this->assertSame(DcDocumento::ESTADO_VIGENTE, $this->documento(null)->estado);
    }

    public function test_vigencia_pasada_esta_vencido(): void
    {
        $this->assertSame(
            DcDocumento::ESTADO_VENCIDO,
            $this->documento(now()->subDay()->toDateString())->estado
        );
    }

    public function test_dentro_de_los_30_dias_de_aviso_esta_por_vencer(): void
    {
        $this->assertSame(
            DcDocumento::ESTADO_POR_VENCER,
            $this->documento(now()->addDays(10)->toDateString())->estado
        );
    }

    public function test_el_dia_30_exacto_todavia_es_por_vencer(): void
    {
        $this->assertSame(
            DcDocumento::ESTADO_POR_VENCER,
            $this->documento(now()->addDays(DcDocumento::DIAS_AVISO)->toDateString())->estado
        );
    }

    public function test_mas_alla_del_aviso_esta_vigente(): void
    {
        $this->assertSame(
            DcDocumento::ESTADO_VIGENTE,
            $this->documento(now()->addDays(DcDocumento::DIAS_AVISO + 1)->toDateString())->estado
        );
    }

    public function test_vence_hoy_todavia_no_esta_vencido(): void
    {
        // Frontera: el último día de vigencia el documento SIGUE sirviendo.
        $this->assertSame(
            DcDocumento::ESTADO_POR_VENCER,
            $this->documento(now()->toDateString())->estado
        );
    }
}
