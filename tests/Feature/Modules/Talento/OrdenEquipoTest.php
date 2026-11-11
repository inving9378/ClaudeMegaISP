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
 * Item #1325: órdenes en equipo (responsable + auxiliares) detrás de ordenes_equipo. Datos sintéticos;
 * correr SOLO por nombre (megaisp_test, transacción que se deshace).
 */
class OrdenEquipoTest extends TestCase
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
            'name' => 'PRUEBA-1325 Instalación', 'category' => 'campo', 'points' => 10,
            'is_billable' => true, 'requires_validation' => true, 'active' => true,
        ]);
    }

    private function encender(): void
    {
        config([
            'unificacion.interruptores.ordenes_equipo'   => true,
            'unificacion.interruptores.participantes_d8' => true,
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

    private function crear(TalentoColaborador $responsable, array $auxiliares = [])
    {
        return $this->actingAs($this->usuario(self::PERMISOS))->postJson('/talento/api/ordenes', [
            'colaborador_id' => $responsable->id,
            'type_id'        => $this->tipo->id,
            'scheduled_at'   => now()->addDay()->format('Y-m-d') . ' 10:00',
            'auxiliares'     => array_map(fn ($c) => $c->id, $auxiliares),
        ]);
    }

    private function asignados(int $taskId): array
    {
        return DB::table('task_user')->where('task_id', $taskId)->orderBy('user_id')->pluck('user_id')->map(fn ($i) => (int) $i)->all();
    }

    public function test_apagado_no_acepta_auxiliares_y_sin_ellos_crea_como_siempre(): void
    {
        $responsable = $this->colaborador();

        $this->crear($responsable, [$this->colaborador()])
            ->assertStatus(422)
            ->assertJsonPath('errors.auxiliares', 'Las órdenes en equipo todavía no están activas.');

        $id = $this->crear($responsable)->assertCreated()->json('id');
        $this->assertSame([$responsable->user_id], $this->asignados($id));
    }

    public function test_encendido_crea_con_responsable_y_auxiliares_y_lo_muestra(): void
    {
        $this->encender();
        [$responsable, $aux1, $aux2] = [$this->colaborador(), $this->colaborador(), $this->colaborador()];

        $res = $this->crear($responsable, [$aux1, $aux2])->assertCreated();
        $id = $res->json('id');

        $this->assertEqualsCanonicalizing([$responsable->user_id, $aux1->user_id, $aux2->user_id], $this->asignados($id));
        $this->assertSame($responsable->id, $res->json('colaborador_id'));
        $this->assertSame([$aux1->id, $aux2->id], array_column($res->json('auxiliares'), 'colaborador_id'));
        $this->assertSame('principal', DB::table('talento_order_participants')
            ->where('origen', 'task')->where('orden_id', $id)->where('user_id', $responsable->user_id)->value('rol'));
    }

    public function test_el_responsable_no_puede_ser_auxiliar(): void
    {
        $this->encender();
        $responsable = $this->colaborador();

        $this->crear($responsable, [$responsable])
            ->assertStatus(422)
            ->assertJsonPath('errors.auxiliares', 'El responsable no puede ser también auxiliar.');
    }

    public function test_editar_conserva_auxiliares_al_cambiar_responsable_y_los_quita_si_se_piden_vacios(): void
    {
        $this->encender();
        [$responsable, $aux, $nuevo] = [$this->colaborador(), $this->colaborador(), $this->colaborador()];
        $id = $this->crear($responsable, [$aux])->assertCreated()->json('id');
        $gestor = $this->usuario(self::PERMISOS);

        // Cambiar el responsable sin mandar auxiliares: el auxiliar se queda.
        $res = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$id}", ['colaborador_id' => $nuevo->id])->assertOk();
        $this->assertSame($nuevo->id, $res->json('colaborador_id'));
        $this->assertSame([$aux->id], array_column($res->json('auxiliares'), 'colaborador_id'));
        $this->assertEqualsCanonicalizing([$nuevo->user_id, $aux->user_id], $this->asignados($id));

        // Mandar auxiliares vacíos los quita.
        $res = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$id}", ['auxiliares' => []])->assertOk();
        $this->assertSame([], $res->json('auxiliares'));
        $this->assertContains('auxiliares', $res->json('cambios'));
        $this->assertSame([$nuevo->user_id], $this->asignados($id));
    }

    public function test_la_consulta_de_asignacion_dice_si_hay_equipos_y_el_reparto(): void
    {
        $this->encender();

        $equipo = $this->actingAs($this->usuario(self::PERMISOS))->getJson('/talento/api/ordenes/asignacion')
            ->assertOk()->json('equipo');

        $this->assertTrue($equipo['activo']);
        $this->assertSame(5, $equipo['max_auxiliares']);
        $this->assertFalse($equipo['reparto']['activo']);
    }
}
