<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\AgendaOrdenesService;
use App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService;
use Database\Seeders\UniPruebaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * Item #1158: una orden pendiente cuyo día ya pasó no se queda atrás — sale en «Mi día» y en la carga
 * de hoy, en rojo, hasta que se termine o se reprograme con motivo (de hoy en adelante, con historial).
 * Correr SOLO por nombre: usa megaisp_test dentro de una transacción que se deshace.
 */
class OrdenesAtrasadasTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function orden($colab, TalentoWorkOrderType $tipo, string $dia, string $estado = 'ToDo'): Task
    {
        $t = Task::create([
            'title'           => 'UNI-PRUEBA orden',
            'tipo'            => 'campo',
            'talento_type_id' => $tipo->id,
            'status'          => $estado,
            'start_date'      => $dia,
            'start_time'      => $dia . ' 10:00:00',
        ]);
        $t->users()->attach($colab->user_id);

        return $t;
    }

    public function test_lo_atrasado_sigue_en_mi_dia_y_en_la_carga_de_hoy_hasta_reprogramarlo(): void
    {
        if (! Schema::hasColumn('talento_work_order_types', 'color')) {
            $this->markTestSkipped('megaisp_test sin la columna color (migración 2026_11_03_120000 pendiente).');
        }
        $colabs = (new UniPruebaSeeder())->perfiles();
        $colab  = reset($colabs);
        $tipo   = TalentoWorkOrderType::create([
            'name' => 'UNI-PRUEBA Instalación', 'category' => 'campo', 'color' => '#1E88E5', 'points' => 1,
            'is_billable' => true, 'requires_validation' => false, 'active' => true,
        ]);
        $ayer = now()->subDay()->toDateString();
        $hoy  = now()->toDateString();

        $atrasada = $this->orden($colab, $tipo, $ayer);
        $hecha    = $this->orden($colab, $tipo, $ayer, 'Done');
        $deHoy    = $this->orden($colab, $tipo, $hoy);

        $svc    = new OrdenTrabajoUnifiedService();
        $agenda = new AgendaOrdenesService();

        // «Mi día»: la atrasada va primero y marcada; la ya hecha no regresa.
        config(['talento.ordenes_atrasadas_en_mi_dia' => true]);
        $miDia = $svc->summaryForHoy($colab->id);
        $this->assertSame($atrasada->id, $miDia[0]['id']);
        $this->assertTrue($miDia[0]['atrasada']);
        $this->assertSame($ayer, $miDia[0]['fecha']);
        $this->assertSame(1, $miDia[0]['dias_atraso']);
        $this->assertContains($deHoy->id, array_column($miDia, 'id'));
        $this->assertNotContains($hecha->id, array_column($miDia, 'id'));

        config(['talento.ordenes_atrasadas_en_mi_dia' => false]);
        $this->assertNotContains($atrasada->id, array_column($svc->summaryForHoy($colab->id), 'id'));

        // Carga: la atrasada cuenta en hoy, en rojo; la hecha se queda en su día con el color del tipo.
        $carga = collect($agenda->carga($ayer, $hoy, [$colab->id])['ordenes'])->keyBy('id');
        $this->assertSame($hoy, $carga[$atrasada->id]['dia']);
        $this->assertSame(TalentoWorkOrderType::COLOR_ATRASADA, $carga[$atrasada->id]['color']);
        $this->assertSame($ayer, $carga[$hecha->id]['dia']);
        $this->assertSame('#1E88E5', $carga[$hecha->id]['color']);
        $this->assertEmpty($agenda->carga($ayer, $hoy, [])['ordenes']);

        // Reprogramar: nunca hacia atrás, siempre con motivo, y queda en el historial.
        $this->assertFalse($svc->reprogramarPropia($atrasada->id, $colab->id, $ayer, 'El cliente no estaba', $colab->user_id)['ok']);
        $this->assertFalse($svc->reprogramarPropia($atrasada->id, $colab->id, $hoy, 'no', $colab->user_id)['ok']);
        $this->assertFalse($agenda->reprogramar($hecha->fresh(), $hoy, 'El cliente no estaba', $colab->user_id)['ok']);

        $manana = now()->addDay()->toDateString();
        $r = $svc->reprogramarPropia($atrasada->id, $colab->id, $manana, 'El cliente pidió otro día', $colab->user_id);
        $this->assertTrue($r['ok']);
        $this->assertSame('10:00', $r['hora']);
        $this->assertSame($manana, $atrasada->fresh()->start_date);

        config(['talento.ordenes_atrasadas_en_mi_dia' => true]);
        $this->assertNotContains($atrasada->id, array_column($svc->summaryForHoy($colab->id), 'id'));

        $historial = $agenda->reprogramacionesDe($atrasada->id);
        $this->assertCount(1, $historial);
        $this->assertSame('El cliente pidió otro día', $historial[0]['motivo']);
        $this->assertTrue($historial[0]['atrasada']);
        $this->assertSame($ayer . ' 10:00', $historial[0]['de']);
    }

    public function test_la_tarea_de_fechas_pasadas_no_mueve_las_ordenes_atrasadas(): void
    {
        if (! Schema::hasColumn('talento_work_order_types', 'color')) {
            $this->markTestSkipped('megaisp_test sin la columna color (migración 2026_11_03_120000 pendiente).');
        }
        $colabs = (new UniPruebaSeeder())->perfiles();
        $colab  = reset($colabs);
        $tipo   = TalentoWorkOrderType::create([
            'name' => 'UNI-PRUEBA Soporte', 'category' => 'campo', 'points' => 1,
            'is_billable' => true, 'requires_validation' => false, 'active' => true,
        ]);
        $ayer   = now()->subDay()->toDateString();
        $orden  = $this->orden($colab, $tipo, $ayer);
        $tarea  = Task::create(['title' => 'UNI-PRUEBA tarea suelta', 'status' => 'ToDo', 'start_date' => $ayer, 'start_time' => $ayer . ' 09:00:00']);
        $ids    = fn () => (new \App\Http\Repository\TaskRepository())->getTasksToUpdate()->pluck('id')->all();

        // Con las atrasadas encendidas la orden conserva su día; las tareas sueltas siguen como antes.
        config(['talento.ordenes_atrasadas_en_mi_dia' => true]);
        $this->assertNotContains($orden->id, $ids());
        $this->assertContains($tarea->id, $ids());

        config(['talento.ordenes_atrasadas_en_mi_dia' => false]);
        $this->assertContains($orden->id, $ids());

        // El interruptor F2 (antes mal ubicado en la config y nunca leído) detiene la tarea completa.
        config(['unificacion.interruptores.fechas_atrasadas_intactas_f2' => true]);
        $this->artisan('update_task_old_command:process')->assertExitCode(0);
        $this->assertSame($ayer, $tarea->fresh()->start_date);
        $this->assertSame($ayer, $orden->fresh()->start_date);
    }

    public function test_la_tarea_de_fechas_pasadas_solo_mueve_lo_abierto(): void
    {
        config(['unificacion.interruptores.fechas_atrasadas_intactas_f2' => false]);
        $ayer  = now()->subDay()->toDateString();
        $tarea = fn (string $status) => Task::create(['title' => "UNI-PRUEBA {$status}", 'status' => $status, 'start_date' => $ayer, 'start_time' => $ayer . ' 09:00:00']);
        $abiertas = [$tarea('ToDo'), $tarea('InProgress')];
        $cerradas = [$tarea('Done'), $tarea('Archivado')];

        $this->artisan('update_task_old_command:process')->assertExitCode(0);

        foreach ($abiertas as $t) {
            $this->assertSame(now()->toDateString(), substr((string) $t->fresh()->start_time, 0, 10), $t->status);
        }
        foreach ($cerradas as $t) {
            $this->assertSame($ayer, substr((string) $t->fresh()->start_time, 0, 10), $t->status);
        }
    }
}
