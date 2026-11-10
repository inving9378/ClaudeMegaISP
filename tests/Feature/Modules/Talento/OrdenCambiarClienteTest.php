<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoOrderEvent;
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
 * Item #1228: cambiar el cliente o prospecto al editar una orden Pendiente.
 * Datos 100 % sintéticos: clientes, fichas, prospecto y ticket creados aquí con nombres y
 * teléfonos inventados. Correr SOLO por nombre: usa megaisp_test dentro de una transacción.
 */
class OrdenCambiarClienteTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const VER = ['talento.work_orders.view', 'talento.work_orders.manage'];

    private TalentoWorkOrderType $tipo;
    private User $creador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Notification::fake();

        foreach (['talento.ordenes.editar', ...self::VER] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tipo = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1228 Soporte', 'category' => 'campo', 'points' => 3,
            'is_billable' => false, 'requires_validation' => true, 'active' => true,
        ]);
        $this->creador = $this->usuario();
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

    private function gestor(): User
    {
        return $this->usuario([...self::VER, 'talento.ordenes.editar']);
    }

    /** Cliente sintético: apellido y teléfono únicos para poder buscarlo. @return array{id:int, cmi:int, apellido:string, telefono:string} */
    private function cliente(): array
    {
        $apellido = 'PRUEBA' . strtoupper(Str::random(8));
        $telefono = '55' . random_int(10000000, 99999999);
        $id = DB::table('clients')->insertGetId([
            'created_by' => $this->creador->id, 'updated_by' => $this->creador->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cmi = DB::table('client_main_information')->insertGetId([
            'client_id' => $id, 'name' => 'CLIENTE', 'father_last_name' => $apellido, 'mother_last_name' => 'SINTETICO',
            'phone' => $telefono, 'estado' => 'Activo', 'street' => 'Calle Prueba', 'external_number' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'cmi' => $cmi, 'apellido' => $apellido, 'telefono' => $telefono];
    }

    private function prospecto(): int
    {
        $crm = DB::table('crms')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_main_information')->insert(['crm_id' => $crm, 'name' => 'PROSPECTO', 'father_last_name' => 'SINTETICO',
            'phone' => '5500000000', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('crm_lead_information')->insertGetId(['crm_id' => $crm, 'crm_status' => 'Nuevo', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function orden(array $extra = [], string $estado = 'ToDo'): Task
    {
        $col = TalentoColaborador::create(['user_id' => $this->usuario()->id, 'type' => 'interno', 'status' => 'active']);
        $dia = now()->addDays(2)->format('Y-m-d');
        $t = Task::create($extra + [
            'title' => '[PRUEBA-1228] orden', 'tipo' => 'campo', 'talento_type_id' => $this->tipo->id,
            'points' => 3, 'is_billable' => 0, 'status' => $estado, 'priority' => 'Media',
            'start_date' => $dia, 'start_time' => "$dia 10:00:00", 'end_time' => "$dia 11:00:00",
        ]);
        $t->users()->sync([$col->user_id]);

        return $t;
    }

    private function editados(int $taskId): array
    {
        return TalentoOrderEvent::deOrden('task', $taskId)->where('evento', 'order.edited')->get()
            ->mapWithKeys(fn ($e) => [$e->datos['campo'] => [$e->datos['de'], $e->datos['a']]])->all();
    }

    private static function fila(string $tabla, int $id): array
    {
        return (array) DB::table($tabla)->where('id', $id)->first();
    }

    public function test_cambiar_el_cliente_de_una_pendiente_funciona_y_queda_en_el_historial(): void
    {
        $gestor = $this->gestor();
        $a      = $this->cliente();
        $b      = $this->cliente();
        $orden  = $this->orden(['client_main_information_id' => $a['cmi']]);
        $antesA = self::fila('client_main_information', $a['cmi']);

        $r = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['client_id' => $b['id']])->assertOk();

        $this->assertSame(['cliente'], $r->json('cambios'));
        $this->assertSame($b['id'], $r->json('cliente.id'));
        $this->assertSame((string) $b['cmi'], (string) $orden->fresh()->client_main_information_id);
        $this->assertSame(
            ["#{$a['id']} · CLIENTE {$a['apellido']} SINTETICO", "#{$b['id']} · CLIENTE {$b['apellido']} SINTETICO"],
            $this->editados($orden->id)['cliente']
        );
        $this->assertSame($antesA, self::fila('client_main_information', $a['cmi']), 'el cliente anterior no se toca');
    }

    public function test_de_prospecto_a_cliente_y_de_vuelta_sin_tocar_al_anterior(): void
    {
        $gestor = $this->gestor();
        $a      = $this->cliente();
        $lead   = $this->prospecto();
        $orden  = $this->orden(['crm_lead_id' => $lead]);
        $antesLead = self::fila('crm_lead_information', $lead);

        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['client_id' => $a['id'], 'crm_lead_id' => null])->assertOk();
        $t = $orden->fresh();
        $this->assertNull($t->crm_lead_id);
        $this->assertSame((string) $a['cmi'], (string) $t->client_main_information_id);
        $this->assertSame(["#{$lead} · PROSPECTO SINTETICO", null], $this->editados($orden->id)['prospecto']);
        $this->assertSame($antesLead, self::fila('crm_lead_information', $lead), 'el prospecto anterior no se toca');

        $antesA = self::fila('client_main_information', $a['cmi']);
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['client_id' => null, 'crm_lead_id' => $lead])->assertOk();
        $this->assertSame($lead, (int) $orden->fresh()->crm_lead_id);
        $this->assertNull($orden->fresh()->client_main_information_id);
        $this->assertSame($antesA, self::fila('client_main_information', $a['cmi']));

        // Nunca los dos a la vez.
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['client_id' => $a['id'], 'crm_lead_id' => $lead])
            ->assertStatus(422);
    }

    public function test_fuera_de_pendiente_no_se_cambia_el_cliente_aunque_se_llame_a_la_api(): void
    {
        $gestor = $this->gestor();
        $a      = $this->cliente();
        $b      = $this->cliente();

        foreach ([['InProgress', []], ['Done', []], ['Done', ['validated_at' => now()]]] as [$estado, $extra]) {
            $orden = $this->orden(['client_main_information_id' => $a['cmi']] + $extra, $estado);
            $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['client_id' => $b['id']])
                ->assertStatus(422)->assertJsonPath('ya_no_pendiente', true);
            $this->assertSame((string) $a['cmi'], (string) $orden->fresh()->client_main_information_id, $estado);
        }
    }

    public function test_sin_permiso_de_editar_la_api_responde_403(): void
    {
        $soloVer = $this->usuario(self::VER);
        $a       = $this->cliente();
        $b       = $this->cliente();
        $orden   = $this->orden(['client_main_information_id' => $a['cmi']]);

        $this->actingAs($soloVer)->putJson("/talento/api/ordenes/{$orden->id}", ['client_id' => $b['id']])->assertForbidden();
        $this->actingAs($soloVer)->getJson('/talento/api/clientes-orden?search=' . $b['apellido'])->assertForbidden();
        $this->assertSame((string) $a['cmi'], (string) $orden->fresh()->client_main_information_id);
    }

    public function test_si_la_orden_trae_ticket_onu_o_servicio_del_cliente_no_se_le_cambia(): void
    {
        $gestor = $this->gestor();
        $a      = $this->cliente();
        $b      = $this->cliente();
        $ticket = DB::table('tickets')->insertGetId(['topic' => 'Ticket sintético #1228', 'customer_lead' => $a['id'],
            'created_at' => now(), 'updated_at' => now()]);
        $antesTicket = self::fila('tickets', $ticket);

        $conTicket = $this->orden(['client_main_information_id' => $a['cmi'], 'ticket_id' => $ticket]);
        $r = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$conTicket->id}", ['client_id' => $b['id']])->assertStatus(422);
        $this->assertStringContainsString("el ticket #{$ticket}", $r->json('error'));
        $this->assertSame((string) $a['cmi'], (string) $conTicket->fresh()->client_main_information_id);
        $this->assertSame($antesTicket, self::fila('tickets', $ticket), 'el ticket no se toca');

        // La pantalla lo sabe desde que abre la orden (para bloquear el buscador y explicar por qué).
        $this->actingAs($gestor)->getJson("/talento/api/ordenes/{$conTicket->id}")->assertOk()
            ->assertJsonPath('vinculos', ["el ticket #{$ticket}"])
            ->assertJsonPath('cliente.id', $a['id']);

        // Lo demás de la orden sí se puede editar (mandando el mismo cliente, como hace la pantalla).
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$conTicket->id}", ['client_id' => $a['id'], 'notes' => 'nota nueva'])
            ->assertOk()->assertJsonPath('cambios', ['notas']);

        // ONU y servicio son del cliente actual: tampoco (ids ficticios, esas columnas no tienen llave foránea).
        $conOnu = $this->orden(['client_main_information_id' => $a['cmi'], 'olt_onu_id' => 987654321]);
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$conOnu->id}", ['client_id' => $b['id']])
            ->assertStatus(422)->assertJsonPath('vinculos', ['la ONU']);
        $conServicio = $this->orden(['client_main_information_id' => $a['cmi'], 'client_service_id' => 987654321]);
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$conServicio->id}", ['client_id' => null])
            ->assertStatus(422)->assertJsonPath('vinculos', ['el servicio']);
    }

    public function test_buscador_de_clientes_por_nombre_telefono_o_numero(): void
    {
        $gestor = $this->gestor();
        $a      = $this->cliente();

        $porNombre = $this->actingAs($gestor)->getJson('/talento/api/clientes-orden?search=' . $a['apellido'])->assertOk();
        $this->assertSame([$a['id']], array_column($porNombre->json(), 'id'));
        $this->assertSame("CLIENTE {$a['apellido']} SINTETICO", $porNombre->json('0.nombre'));
        $this->assertSame('Calle Prueba 1', $porNombre->json('0.direccion'));

        $porTelefono = $this->actingAs($gestor)->getJson('/talento/api/clientes-orden?search=' . $a['telefono'])->assertOk();
        $this->assertContains($a['id'], array_column($porTelefono->json(), 'id'));

        $porNumero = $this->actingAs($gestor)->getJson('/talento/api/clientes-orden?search=id:' . $a['id'])->assertOk();
        $this->assertSame([$a['id']], array_column($porNumero->json(), 'id'));

        // Muy corto: no lista clientes al azar.
        $this->actingAs($gestor)->getJson('/talento/api/clientes-orden?search=PR')->assertOk()->assertExactJson([]);
    }
}
