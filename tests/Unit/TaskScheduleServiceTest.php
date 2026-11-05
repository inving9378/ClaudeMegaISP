<?php

namespace Tests\Unit;

use App\Services\TaskScheduleService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TaskScheduleServiceTest extends TestCase
{
    public function test_lee_formato_viejo_solo_hora(): void
    {
        $this->assertSame('2026-10-30 16:00:00', TaskScheduleService::start('2026-10-30', '16:00:00')->format('Y-m-d H:i:s'));
    }

    public function test_lee_datetime_completo_y_prevalece_sobre_start_date_viejo(): void
    {
        $this->assertSame('2026-11-02 09:30:00', TaskScheduleService::start('2026-10-30', '2026-11-02 09:30:00')->format('Y-m-d H:i:s'));
    }

    public function test_sin_hora_es_medianoche_y_sin_fecha_es_null(): void
    {
        $this->assertSame('2026-10-30 00:00:00', TaskScheduleService::start('2026-10-30', null)->format('Y-m-d H:i:s'));
        $this->assertNull(TaskScheduleService::start(null, '16:00:00'));
    }

    public function test_fields_deja_fecha_y_hora_coherentes_al_reprogramar(): void
    {
        $f = TaskScheduleService::fields(Carbon::parse('2026-11-03 10:00'), Carbon::parse('2026-11-03 11:00'));
        $this->assertSame(['start_date' => '2026-11-03', 'start_time' => '2026-11-03 10:00:00', 'end_time' => '2026-11-03 11:00:00'], $f);
        $this->assertSame('2026-11-03 10:00:00', TaskScheduleService::start($f['start_date'], $f['start_time'])->format('Y-m-d H:i:s'));
    }

    public function test_conDuracion_alta_usa_hora_de_negocio_y_deja_campos_coherentes(): void
    {
        $r = TaskScheduleService::conDuracion(['start_time' => '2026-11-03', 'end_time' => null], '01:30', false, Carbon::parse('2026-10-26 09:45'));
        $this->assertSame('2026-11-03', $r['start_date']);
        $this->assertSame('2026-11-03 09:00:00', $r['start_time']);
        $this->assertSame('2026-11-03 10:30:00', $r['end_time']);
    }

    public function test_conDuracion_edicion_conserva_inicio_y_recalcula_fin(): void
    {
        $r = TaskScheduleService::conDuracion(['start_time' => '2026-11-03 14:15:00'], '02:00', true);
        $this->assertSame('2026-11-03 14:15:00', $r['start_time']);
        $this->assertSame('2026-11-03 16:15:00', $r['end_time']);
    }

    public function test_ventana_lee_formato_viejo_y_nuevo(): void
    {
        [$i, $f] = TaskScheduleService::ventana('2026-10-30', '16:00:00', '2026-10-30 17:00:00');
        $this->assertSame('2026-10-30 16:00:00', $i->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-30 17:00:00', $f->format('Y-m-d H:i:s'));
        [$i2] = TaskScheduleService::ventana(null, null, null);
        $this->assertNull($i2);
    }

    public function test_normalizar_alta_desde_ticket_fecha_mas_hhmm(): void
    {
        $r = TaskScheduleService::normalizar(['start_date' => '2026-11-03', 'start_time' => '16:30', 'end_time' => null]);
        $this->assertSame('2026-11-03', $r['start_date']);
        $this->assertSame('2026-11-03 16:30:00', $r['start_time']);
        $this->assertNull($r['end_time']);
    }

    public function test_normalizar_sin_fecha_no_toca_y_conserva_fin_con_parentesis(): void
    {
        $this->assertSame(['title' => 'x'], TaskScheduleService::normalizar(['title' => 'x']));
        $r = TaskScheduleService::normalizar(['start_date' => '2026-11-03', 'start_time' => '09:00', 'end_time' => '2026-11-03 10:00:00 (hora estándar)']);
        $this->assertSame('2026-11-03 10:00:00', $r['end_time']);
    }

    public function test_zona_de_negocio_es_mexico_city_por_defecto(): void
    {
        $this->assertSame('America/Mexico_City', TaskScheduleService::zona());
    }
}
