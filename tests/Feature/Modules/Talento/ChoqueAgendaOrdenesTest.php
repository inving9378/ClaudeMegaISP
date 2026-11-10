<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1227: la misma regla de choque de agenda en Crear, Editar y Reprogramar.
 * Datos 100 % sintéticos (usuarios, colaboradores, tipos y órdenes creados aquí, sin clientes).
 * Correr SOLO por nombre: usa megaisp_test dentro de una transacción que se deshace.
 */
class ChoqueAgendaOrdenesTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const PERMISOS = ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.ordenes.editar'];

    private TalentoWorkOrderType $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Notification::fake();

        foreach (self::PERMISOS as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tipo = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1227 Soporte', 'category' => 'campo', 'points' => 3,
            'is_billable' => false, 'requires_validation' => true, 'active' => true,
        ]);
    }

    private function usuario(array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => Str::random(6), 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => bcrypt(Str::random(20)), 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::find($id);
        $permisos && $user->givePermissionTo($permisos);

        return $user;
    }

    private function colaborador(): TalentoColaborador
    {
        return TalentoColaborador::create(['user_id' => $this->usuario()->id, 'type' => 'interno', 'status' => 'active']);
    }

    private function gestor(): User
    {
        return $this->usuario(self::PERMISOS);
    }

    private function orden(TalentoColaborador $col, string $inicio, string $estado = 'ToDo', array $extra = []): Task
    {
        $t = Task::create($extra + [
            'title' => '[PRUEBA-1227] orden', 'tipo' => 'campo', 'talento_type_id' => $this->tipo->id,
            'points' => $this->tipo->points, 'is_billable' => 0, 'status' => $estado, 'priority' => 'Media',
            'start_date' => substr($inicio, 0, 10), 'start_time' => strlen($inicio) > 10 ? $inicio : null,
            'end_time' => strlen($inicio) > 10 ? date('Y-m-d H:i:s', strtotime($inicio) + 3600) : null,
        ]);
        $t->users()->sync([$col->user_id]);

        return $t;
    }

    private function crear(User $gestor, TalentoColaborador $col, string $cuando)
    {
        return $this->actingAs($gestor)->postJson('/talento/api/ordenes', [
            'colaborador_id' => $col->id, 'type_id' => $this->tipo->id, 'scheduled_at' => $cuando,
        ]);
    }

    private function ordenesDe(TalentoColaborador $col): int
    {
        return Task::whereHas('users', fn ($u) => $u->where('users.id', $col->user_id))->count();
    }

    // ── Crear ───────────────────────────────────────────────────────────────────────

    public function test_crear_encimada_con_otra_orden_abierta_se_rechaza_con_el_mismo_mensaje(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $dia    = now()->addDays(2)->format('Y-m-d');
        $ocupa  = $this->orden($col, "$dia 12:00:00");
        $antes  = $this->ordenesDe($col);

        $r = $this->crear($gestor, $col, "$dia 12:30")->assertStatus(422);
        $this->assertStringStartsWith('Choque de agenda:', $r->json('error'));
        $this->assertStringContainsString("#{$ocupa->id} (PRUEBA-1227 Soporte)", $r->json('error'));
        $this->assertStringContainsString('a las 12:00', $r->json('error'));
        $this->assertSame($ocupa->id, $r->json('choque'));
        $this->assertSame($antes, $this->ordenesDe($col), 'no se creó la orden');

        // Una orden En curso también ocupa su franja.
        $this->orden($col, "$dia 16:00:00", 'InProgress');
        $this->crear($gestor, $col, "$dia 15:30")->assertStatus(422);
    }

    public function test_crear_sin_hora_o_sin_encimarse_no_choca(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $otro   = $this->colaborador();
        $dia    = now()->addDays(2)->format('Y-m-d');
        $this->orden($col, "$dia 12:00:00");
        $this->orden($col, $dia); // sin hora: ocupa el día, no una franja

        $this->crear($gestor, $col, $dia)->assertCreated();               // la nueva sin hora
        $this->crear($gestor, $col, "$dia 13:00")->assertCreated();       // empieza justo al terminar la otra
        $this->crear($gestor, $col, "$dia 08:00")->assertCreated();       // la de sin hora no le estorba
        $this->crear($gestor, $otro, "$dia 12:00")->assertCreated();      // otra persona, misma hora
    }

    public function test_completada_validada_o_eliminada_no_cuentan_al_crear(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $dia    = now()->addDays(2)->format('Y-m-d');
        $this->orden($col, "$dia 09:00:00", 'Done');
        $this->orden($col, "$dia 10:00:00", 'Done', ['validated_at' => now()]);
        $this->orden($col, "$dia 11:00:00")->delete();

        $this->crear($gestor, $col, "$dia 09:00")->assertCreated();
        $this->crear($gestor, $col, "$dia 10:00")->assertCreated();
        $this->crear($gestor, $col, "$dia 11:00")->assertCreated();
    }

    // ── Reprogramar ─────────────────────────────────────────────────────────────────

    private function reprogramar(User $gestor, Task $orden, string $fecha)
    {
        return $this->actingAs($gestor)->postJson("/talento/api/ordenes/{$orden->id}/reprogramar", [
            'fecha' => $fecha, 'motivo' => 'Prueba sintética de reprogramación',
        ]);
    }

    private function reprogramaciones(Task $orden): int
    {
        return \App\Modules\Addons\Talento\Models\TalentoOrderEvent::deOrden('task', $orden->id)
            ->where('evento', 'order.rescheduled')->count();
    }

    public function test_reprogramar_a_una_hora_ocupada_se_rechaza_y_no_mueve_la_orden(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $d1     = now()->addDay()->format('Y-m-d');
        $d2     = now()->addDays(2)->format('Y-m-d');
        $ocupa  = $this->orden($col, "$d2 12:00:00");
        $orden  = $this->orden($col, "$d1 09:00:00");

        $r = $this->reprogramar($gestor, $orden, "$d2 12:30")->assertStatus(422);
        $this->assertStringStartsWith('Choque de agenda:', $r->json('error'));
        $this->assertStringContainsString("#{$ocupa->id}", $r->json('error'));
        $this->assertSame($ocupa->id, $r->json('choque'));
        $this->assertSame("$d1 09:00:00", $orden->fresh()->start_time);
        $this->assertSame(0, $this->reprogramaciones($orden), 'no quedó en el historial');

        // Solo el día: conserva su hora (12:15) y esa franja choca con la de 12:00.
        $otra = $this->orden($col, "$d1 12:15:00");
        $this->reprogramar($gestor, $otra, $d2)->assertStatus(422);
        $this->assertSame("$d1 12:15:00", $otra->fresh()->start_time);
    }

    public function test_reprogramar_no_choca_consigo_misma_ni_cuando_no_hay_hora(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $d1     = now()->addDay()->format('Y-m-d');
        $d2     = now()->addDays(2)->format('Y-m-d');
        $this->orden($col, "$d2 12:00:00");

        $propia = $this->orden($col, "$d2 09:00:00");
        $this->reprogramar($gestor, $propia, "$d2 09:30")->assertOk();      // solo se encima con su franja vieja
        $this->assertSame("$d2 09:30:00", $propia->fresh()->start_time);

        $sinHora = $this->orden($col, $d1);
        $this->reprogramar($gestor, $sinHora, $d2)->assertOk();             // sigue sin hora: no choca
        $this->reprogramar($gestor, $this->orden($col, "$d1 08:00:00"), "$d2 13:00")->assertOk(); // contigua
    }

    public function test_reprogramar_ignora_completadas_validadas_y_eliminadas(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $d1     = now()->addDay()->format('Y-m-d');
        $d2     = now()->addDays(2)->format('Y-m-d');
        $this->orden($col, "$d2 09:00:00", 'Done');
        $this->orden($col, "$d2 11:00:00", 'Done', ['validated_at' => now()]);
        $this->orden($col, "$d2 13:00:00")->delete();

        $this->reprogramar($gestor, $this->orden($col, "$d1 09:00:00"), "$d2 09:00")->assertOk();
        $this->reprogramar($gestor, $this->orden($col, "$d1 11:00:00"), "$d2 11:00")->assertOk();
        $this->reprogramar($gestor, $this->orden($col, "$d1 13:00:00"), "$d2 13:00")->assertOk();
    }

    public function test_reprogramar_desde_mi_dia_o_la_app_tambien_respeta_el_choque(): void
    {
        $col   = $this->colaborador();
        $d1    = now()->addDay()->format('Y-m-d');
        $d2    = now()->addDays(2)->format('Y-m-d');
        $ocupa = $this->orden($col, "$d2 12:00:00");
        $orden = $this->orden($col, "$d1 09:00:00");

        // Camino común de «Mi día» (portal) y la app: OrdenTrabajoUnifiedService::reprogramarPropia.
        $r = app(\App\Modules\Addons\Talento\Services\OrdenTrabajoUnifiedService::class)
            ->reprogramarPropia($orden->id, $col->id, "$d2 12:30", 'Prueba sintética de reprogramación', $col->user_id);

        $this->assertFalse($r['ok']);
        $this->assertSame(422, $r['codigo']);
        $this->assertSame($ocupa->id, $r['choque']);
        $this->assertSame("$d1 09:00:00", $orden->fresh()->start_time);
    }

    // ── Editar (ya lo hacía #1225: sigue igual) y el mismo mensaje en los tres ─────────

    public function test_editar_sigue_rechazando_y_no_choca_consigo_misma(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $dia    = now()->addDays(2)->format('Y-m-d');
        $this->orden($col, "$dia 12:00:00");
        $orden  = $this->orden($col, "$dia 10:00:00");

        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['scheduled_at' => "$dia 12:30"])
            ->assertStatus(422);
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['scheduled_at' => "$dia 10:30"])
            ->assertOk();
        $this->assertSame("$dia 10:30:00", $orden->fresh()->start_time);
    }

    public function test_crear_editar_y_reprogramar_dan_el_mismo_mensaje(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $d1     = now()->addDay()->format('Y-m-d');
        $d2     = now()->addDays(2)->format('Y-m-d');
        $this->orden($col, "$d2 12:00:00");
        $orden  = $this->orden($col, "$d1 09:00:00");

        $crear  = $this->crear($gestor, $col, "$d2 12:30")->assertStatus(422)->json('error');
        $editar = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['scheduled_at' => "$d2 12:30"])
            ->assertStatus(422)->json('error');
        $mover  = $this->reprogramar($gestor, $orden, "$d2 12:30")->assertStatus(422)->json('error');

        $this->assertSame($crear, $editar);
        $this->assertSame($crear, $mover);
        $this->assertStringEndsWith('a las 12:00. Elige otra hora u otra persona.', $crear);
    }
}
