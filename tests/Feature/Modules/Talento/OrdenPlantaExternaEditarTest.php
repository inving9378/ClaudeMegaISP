<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoOrderEvent;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderData;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderTemplate;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\OrdenPendienteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1225 sobre #1200: editar una orden Pendiente de planta externa con plantilla.
 * a) el tipo no cambia; b) la descripción se arma con los datos; c) el título de la plantilla solo
 * cambia con sus datos; d) los campos van a talento_work_order_data; e) el cliente es referencia
 * (se cambia o se quita, sin prospecto); f) el choque de agenda aplica igual.
 * Datos 100 % sintéticos. Correr SOLO por nombre: megaisp_test dentro de una transacción.
 */
class OrdenPlantaExternaEditarTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private TalentoWorkOrderType $tipoCaja;
    private TalentoWorkOrderType $tipoCliente;
    private User $gestor;
    private TalentoColaborador $col;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Notification::fake();

        $permisos = ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.ordenes.editar', 'talento.ordenes.eliminar'];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tipoCaja = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1225 Caja', 'category' => 'campo', 'area' => TalentoWorkOrderType::AREA_PLANTA_EXTERNA,
            'project_id' => Project::value('id'), 'points' => 0, 'is_billable' => false, 'requires_validation' => true, 'active' => true,
        ]);
        $this->tipoCliente = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1225 Soporte', 'category' => 'campo', 'points' => 3,
            'is_billable' => false, 'requires_validation' => true, 'active' => true,
        ]);
        TalentoWorkOrderTemplate::create([
            'type_id' => $this->tipoCaja->id, 'name' => 'PRUEBA-1225 plantilla', 'title' => 'Habilitar', 'description' => 'Instrucciones sintéticas.',
            'priority' => 'Alta', 'active' => true,
            'fields' => [
                ['key' => 'zona', 'label' => 'Zona', 'type' => 'texto', 'required' => true, 'identificador' => true],
                ['key' => 'caja', 'label' => 'Caja', 'type' => 'texto', 'required' => true, 'identificador' => true],
                ['key' => 'modelo', 'label' => 'Modelo', 'type' => 'texto'],
                ['key' => 'potencia', 'label' => 'Potencia', 'type' => 'numero', 'unit' => 'dBm', 'when' => 'cierre'],
            ],
            'checklist' => [['label' => 'Conector', 'cantidad' => 2, 'unidad' => 'pza']],
        ]);

        $this->gestor = $this->usuario($permisos);
        $this->col = TalentoColaborador::create(['user_id' => $this->usuario()->id, 'type' => 'interno', 'status' => 'active']);
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

    /** Cliente sintético con ficha (clients.id). */
    private function cliente(): int
    {
        $id = DB::table('clients')->insertGetId([
            'created_by' => $this->gestor->id, 'updated_by' => $this->gestor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_main_information')->insert([
            'client_id' => $id, 'name' => 'CLIENTE', 'father_last_name' => 'PRUEBA' . strtoupper(Str::random(6)),
            'mother_last_name' => 'SINTETICO', 'phone' => '55' . random_int(10000000, 99999999), 'estado' => 'Activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** Orden de planta externa creada por el mismo alta que la pantalla. */
    private function ordenCaja(string $hora = '10:00', array $datos = ['zona' => 'Z1', 'caja' => 'C7', 'modelo' => 'M-1'], ?string $notas = 'Llevar escalera'): Task
    {
        $plantilla = TalentoWorkOrderTemplate::where('type_id', $this->tipoCaja->id)->value('id');
        $r = $this->actingAs($this->gestor)->postJson('/talento/api/ordenes', [
            'colaborador_id' => $this->col->id, 'type_id' => $this->tipoCaja->id, 'template_id' => $plantilla,
            'scheduled_at' => now()->addDay()->format('Y-m-d') . " $hora", 'datos' => $datos, 'notes' => $notas,
        ])->assertCreated();

        return Task::findOrFail($r->json('id'));
    }

    private function editar(Task $t, array $cambios)
    {
        return $this->actingAs($this->gestor)->putJson("/talento/api/ordenes/{$t->id}", $cambios);
    }

    public function test_editar_datos_actualiza_plantilla_descripcion_y_titulo_con_historial(): void
    {
        $t = $this->ordenCaja();
        $this->assertStringContainsString('Zona Z1 · Caja C7', $t->title);

        $this->editar($t, ['datos' => ['zona' => 'Z1', 'caja' => 'C9']])->assertOk();

        $t->refresh();
        $valores = TalentoWorkOrderData::where('tarea_id', $t->id)->value('values');
        $this->assertEquals(['zona' => 'Z1', 'caja' => 'C9'], $valores, 'Modelo vacío = se quita; solo quedan los capturados');
        $this->assertStringContainsString('Zona Z1 · Caja C9', $t->title);
        $this->assertStringContainsString("Caja: C9", $t->description);
        $this->assertStringNotContainsString('Modelo:', $t->description);
        $this->assertStringContainsString("Instrucciones sintéticas.", $t->description);
        $this->assertStringContainsString("MATERIALES:\n- Conector: 2 pza", $t->description);
        $this->assertStringEndsWith("NOTAS:\nLlevar escalera", $t->description, 'Las notas no se pierden');

        $campos = TalentoOrderEvent::deOrden('task', $t->id)->where('evento', 'order.edited')->get()->pluck('datos.campo')->all();
        $this->assertEqualsCanonicalizing(['dato:Caja', 'dato:Modelo'], $campos);
    }

    public function test_editar_solo_notas_rearma_descripcion_sin_tocar_datos_ni_titulo(): void
    {
        $t = $this->ordenCaja();
        $titulo = $t->title;

        $show = $this->actingAs($this->gestor)->getJson("/talento/api/ordenes/{$t->id}")->assertOk();
        $this->assertSame('Llevar escalera', $show->json('datos.notas'));

        $this->editar($t, ['notes' => "Otra nota\ncon dos renglones"])->assertOk();
        $t->refresh();
        $this->assertSame($titulo, $t->title);
        $this->assertStringContainsString('Caja: C7', $t->description);
        $this->assertStringEndsWith("NOTAS:\nOtra nota\ncon dos renglones", $t->description);

        $this->editar($t, ['notes' => ''])->assertOk();
        $this->assertStringNotContainsString('NOTAS:', $t->fresh()->description);
    }

    public function test_el_tipo_no_cambia_en_planta_externa_ni_una_de_cliente_pasa_a_planta_externa(): void
    {
        $t = $this->ordenCaja();
        $this->editar($t, ['type_id' => $this->tipoCliente->id])->assertStatus(422)
            ->assertJsonPath('error', OrdenPendienteService::MSG_TIPO_FIJO);
        $this->assertSame($this->tipoCaja->id, (int) $t->fresh()->talento_type_id);

        $cliente = Task::create([
            'title' => '[PRUEBA-1225] cliente', 'tipo' => 'campo', 'talento_type_id' => $this->tipoCliente->id, 'points' => 3,
            'is_billable' => 0, 'status' => 'ToDo', 'priority' => 'Media', 'description' => 'x',
            'start_date' => now()->addDays(2)->toDateString(), 'start_time' => now()->addDays(2)->format('Y-m-d') . ' 09:00:00',
        ]);
        $cliente->users()->sync([$this->col->user_id]);
        $this->editar($cliente, ['type_id' => $this->tipoCaja->id])->assertStatus(422)
            ->assertJsonPath('error', OrdenPendienteService::MSG_TIPO_FIJO);
    }

    public function test_datos_obligatorios_y_campos_de_cierre_se_rechazan_sin_cambiar_nada(): void
    {
        $t = $this->ordenCaja();
        $antes = [$t->title, $t->description];

        $this->editar($t, ['datos' => ['zona' => 'Z1']])->assertStatus(422)->assertJsonStructure(['errors' => ['datos.caja']]);
        $this->editar($t, ['datos' => ['zona' => 'Z1', 'caja' => 'C7', 'potencia' => -20]])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['datos.potencia']]);

        $t->refresh();
        $this->assertSame($antes, [$t->title, $t->description]);
        $this->assertSame('C7', TalentoWorkOrderData::where('tarea_id', $t->id)->value('values')['caja']);
    }

    public function test_cliente_de_referencia_se_cambia_y_se_quita_pero_no_lleva_prospecto(): void
    {
        $clientes = [$this->cliente(), $this->cliente()];
        $t = $this->ordenCaja();

        $this->editar($t, ['client_id' => $clientes[0]])->assertOk();
        $this->editar($t, ['client_id' => $clientes[1]])->assertOk()->assertJsonPath('cliente.id', $clientes[1]);
        $this->editar($t, ['client_id' => null])->assertOk();
        $this->assertNull($t->fresh()->client_main_information_id);

        $crm = DB::table('crms')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        $lead = DB::table('crm_lead_information')->insertGetId(['crm_id' => $crm, 'crm_status' => 'Nuevo', 'created_at' => now(), 'updated_at' => now()]);
        $this->editar($t, ['crm_lead_id' => $lead])->assertStatus(422)->assertJsonPath('error', fn ($e) => str_contains($e, 'no llevan prospecto'));
        $this->assertNull($t->fresh()->crm_lead_id);
    }

    public function test_choque_de_agenda_aplica_igual(): void
    {
        $a = $this->ordenCaja('10:00');
        $b = $this->ordenCaja('13:00', ['zona' => 'Z2', 'caja' => 'C1']);

        $this->editar($b, ['scheduled_at' => now()->addDay()->format('Y-m-d') . ' 10:30'])->assertStatus(422)
            ->assertJsonPath('choque', $a->id);
    }
}
