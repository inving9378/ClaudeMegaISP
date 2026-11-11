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
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1326: «No puedo completar» (incluido «El cliente canceló») manda la orden al supervisor, que la
 * atiende desde la bandeja de Órdenes. Datos sintéticos; correr SOLO por nombre (megaisp_test).
 */
class OrdenIncidenciaSupervisorTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const GESTOR = ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.ordenes.editar', 'talento.ordenes.eliminar'];

    private TalentoColaborador $tecnico;

    private Task $orden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Notification::fake();
        foreach (self::GESTOR as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tecnico = TalentoColaborador::create(['user_id' => $this->usuario()->id, 'type' => 'interno', 'status' => 'active']);
        $tipo = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1326 Instalación', 'category' => 'campo', 'points' => 9,
            'is_billable' => false, 'requires_validation' => true, 'active' => true,
        ]);
        $hoy = now()->format('Y-m-d');
        $this->orden = Task::create([
            'title' => '[PRUEBA-1326] orden', 'tipo' => 'campo', 'talento_type_id' => $tipo->id, 'points' => 9,
            'is_billable' => 0, 'status' => 'InProgress', 'priority' => 'Media', 'start_date' => $hoy,
            'start_time' => "$hoy 10:00:00", 'end_time' => "$hoy 11:00:00",
        ]);
        $this->orden->users()->sync([$this->tecnico->user_id]);
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

    private function comoTecnico(): void
    {
        Sanctum::actingAs(User::find($this->tecnico->user_id));
    }

    private function reportarCancelacion(): void
    {
        $this->comoTecnico();
        $this->postJson("/talento/api/ots/{$this->orden->id}/incidencia", ['motivo' => 'cliente_cancelo', 'nota' => 'Ya no quiere el servicio'])
            ->assertSuccessful()
            ->assertJsonPath('status', 'incidencia');
    }

    private function estadoEnApp(): string
    {
        $this->comoTecnico();

        return collect($this->getJson('/talento/api/ots/hoy')->assertOk()->json('ots'))->firstWhere('id', $this->orden->id)['status'];
    }

    public function test_el_cliente_cancelo_manda_la_orden_al_supervisor(): void
    {
        $this->reportarCancelacion();

        $this->assertSame('ToDo', $this->orden->fresh()->status, 'en curso vuelve a pendiente para poder editarla o eliminarla');
        $this->assertSame('incidencia', $this->estadoEnApp());

        $this->comoTecnico();
        $this->postJson("/talento/api/ots/{$this->orden->id}/iniciar")->assertStatus(422);

        $gestor = $this->usuario(self::GESTOR);
        // Desde la web tampoco se inicia mientras la incidencia no se atienda.
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$this->orden->id}/status", ['status' => 'in_progress'])
            ->assertStatus(422);

        $lista = $this->actingAs($gestor)
            ->getJson('/talento/api/ordenes?status=incidencia')->assertOk();
        $this->assertSame(1, $lista->json('con_incidencia'));
        $fila = collect($lista->json('data'))->firstWhere('id', $this->orden->id);
        $this->assertSame('El cliente canceló', $fila['incidencia']['motivo_texto']);
        $this->assertSame('Ya no quiere el servicio', $fila['incidencia']['nota']);
    }

    public function test_regresar_al_tecnico_la_deja_atendida_y_se_puede_iniciar(): void
    {
        $this->reportarCancelacion();

        $this->actingAs($this->usuario(self::GESTOR))
            ->postJson("/talento/api/ordenes/{$this->orden->id}/incidencia/regresar")
            ->assertOk()
            ->assertJsonPath('incidencia', null);

        $this->assertSame('regresada', DB::table('talento_work_order_incidents')->where('tarea_id', $this->orden->id)->value('atencion'));
        $this->assertSame('pending', $this->estadoEnApp());
        $this->comoTecnico();
        $this->postJson("/talento/api/ots/{$this->orden->id}/iniciar")->assertSuccessful();

        // Ya no hay nada que regresar.
        $this->actingAs($this->usuario(self::GESTOR))
            ->postJson("/talento/api/ordenes/{$this->orden->id}/incidencia/regresar")->assertStatus(422);
    }

    public function test_eliminarla_por_cancelacion_la_deja_atendida(): void
    {
        $this->reportarCancelacion();

        $this->actingAs($this->usuario(self::GESTOR))
            ->deleteJson("/talento/api/ordenes/{$this->orden->id}", ['motivo' => 'cliente_cancelo'])
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $this->orden->id]);
        $this->assertSame('eliminada', DB::table('talento_work_order_incidents')->where('tarea_id', $this->orden->id)->value('atencion'));
    }

    public function test_reprogramarla_la_deja_atendida(): void
    {
        $this->reportarCancelacion();
        $manana = now()->addDay()->format('Y-m-d');

        $this->actingAs($this->usuario(self::GESTOR))
            ->postJson("/talento/api/ordenes/{$this->orden->id}/reprogramar", ['fecha' => $manana, 'motivo' => 'El cliente pidió otro día'])
            ->assertOk();

        $this->assertSame('reprogramada', DB::table('talento_work_order_incidents')->where('tarea_id', $this->orden->id)->value('atencion'));
    }

    public function test_quien_no_puede_editar_no_la_regresa(): void
    {
        $this->reportarCancelacion();

        $this->actingAs($this->usuario(['talento.work_orders.view']))
            ->postJson("/talento/api/ordenes/{$this->orden->id}/incidencia/regresar")
            ->assertStatus(403);
    }
}
