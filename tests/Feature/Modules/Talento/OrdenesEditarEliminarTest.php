<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\Task;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoOrderEvent;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\AgendaOrdenesService;
use App\Modules\Addons\Talento\Services\AvisoOrdenColaborador;
use App\Modules\Addons\Talento\Services\OrdenPendienteService;
use App\Notifications\StandardNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1225: editar y eliminar una orden de trabajo de Talento solo mientras está Pendiente.
 * Datos 100 % sintéticos (usuarios, colaboradores y tipos creados aquí; prospecto con id ficticio).
 * Correr SOLO por nombre: usa megaisp_test dentro de una transacción que se deshace.
 */
class OrdenesEditarEliminarTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const VER = ['talento.work_orders.view', 'talento.work_orders.manage'];

    private TalentoWorkOrderType $tipoA;
    private TalentoWorkOrderType $tipoB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        foreach (['talento.ordenes.editar', 'talento.ordenes.eliminar', ...self::VER] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tipoA = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1225 Soporte', 'category' => 'campo', 'points' => 3,
            'is_billable' => false, 'requires_validation' => true, 'active' => true,
        ]);
        $this->tipoB = TalentoWorkOrderType::create([
            'name' => 'PRUEBA-1225 Instalación', 'category' => 'campo', 'points' => 9,
            'is_billable' => true, 'requires_validation' => true, 'active' => true,
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
        return $this->usuario([...self::VER, 'talento.ordenes.editar', 'talento.ordenes.eliminar']);
    }

    private function orden(TalentoColaborador $col, string $inicio, string $estado = 'ToDo', array $extra = []): Task
    {
        $t = Task::create($extra + [
            'title' => '[PRUEBA-1225] orden', 'tipo' => 'campo', 'talento_type_id' => $this->tipoA->id,
            'points' => $this->tipoA->points, 'is_billable' => 0, 'status' => $estado, 'priority' => 'Media',
            'start_date' => substr($inicio, 0, 10), 'start_time' => $inicio,
            'end_time' => date('Y-m-d H:i:s', strtotime($inicio) + 3600), 'description' => 'nota original',
        ]);
        $t->users()->sync([$col->user_id]);

        return $t;
    }

    private function editados(int $taskId): array
    {
        return TalentoOrderEvent::deOrden('task', $taskId)->where('evento', 'order.edited')->get()
            ->mapWithKeys(fn ($e) => [$e->datos['campo'] => [$e->datos['de'], $e->datos['a']]])->all();
    }

    private static function tituloAviso(StandardNotification $n, $notifiable): ?string
    {
        return $n->toDatabase($notifiable)['attributes']['title'] ?? null;
    }

    public function test_editar_pendiente_cambia_tipo_fecha_y_notas_recalcula_puntos_y_deja_historial(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $manana = now()->addDay()->format('Y-m-d');
        $orden  = $this->orden($col, "$manana 10:00:00");

        $r = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", [
            'colaborador_id' => $col->id,
            'type_id'        => $this->tipoB->id,
            'scheduled_at'   => "$manana 12:30",
            'notes'          => 'nota corregida',
        ])->assertOk();

        $this->assertSame(9, $r->json('points'));
        $this->assertEqualsCanonicalizing(['tipo', 'puntos', 'fecha', 'notas'], $r->json('cambios'));

        $t = $orden->fresh();
        $this->assertSame($this->tipoB->id, (int) $t->talento_type_id);
        $this->assertSame(9, (int) $t->points);
        $this->assertSame(1, (int) $t->is_billable);
        $this->assertSame("$manana 12:30:00", $t->start_time);
        $this->assertSame("$manana 13:30:00", $t->end_time);
        $this->assertSame('nota corregida', $t->description);
        $this->assertSame('[PRUEBA-1225 Instalación] nota corregida', $t->title);
        $this->assertSame('ToDo', $t->status);

        $hist = $this->editados($orden->id);
        $this->assertSame(['PRUEBA-1225 Soporte', 'PRUEBA-1225 Instalación'], $hist['tipo']);
        $this->assertSame([3, 9], $hist['puntos']);
        $this->assertSame(["$manana 10:00", "$manana 12:30"], $hist['fecha']);
        $this->assertSame(['nota original', 'nota corregida'], $hist['notas']);
        $this->assertArrayNotHasKey('colaborador', $hist, 'misma persona: no es un cambio');
        $this->assertSame($gestor->id, (int) TalentoOrderEvent::deOrden('task', $orden->id)->value('actor_id'));

        // Sin cambios reales no se escribe nada en el historial.
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'nota corregida'])->assertOk();
        $this->assertCount(4, $this->editados($orden->id));
    }

    public function test_cambiar_de_colaborador_notifica_al_nuevo_y_al_anterior(): void
    {
        Notification::fake();
        $gestor   = $this->gestor();
        $anterior = $this->colaborador();
        $nuevo    = $this->colaborador();
        $orden    = $this->orden($anterior, now()->addDays(2)->format('Y-m-d') . ' 09:00:00');

        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['colaborador_id' => $nuevo->id])->assertOk();

        $this->assertSame([$nuevo->user_id], $orden->fresh()->users()->pluck('users.id')->all());
        Notification::assertSentTo($nuevo->user, StandardNotification::class,
            fn ($n, $c, $u) => self::tituloAviso($n, $u) === 'Nueva tarea asignada');
        Notification::assertSentTo($anterior->user, StandardNotification::class,
            fn ($n, $c, $u) => self::tituloAviso($n, $u) === AvisoOrdenColaborador::TITULO_RETIRADA);
        Notification::assertNotSentTo($anterior->user, StandardNotification::class,
            fn ($n, $c, $u) => self::tituloAviso($n, $u) === 'Nueva tarea asignada');
        $this->assertArrayHasKey('colaborador', $this->editados($orden->id));

        // A alguien dado de baja no se le reasigna.
        $baja = $this->colaborador();
        $baja->update(['status' => 'inactive']);
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['colaborador_id' => $baja->id])
            ->assertStatus(422)->assertJsonPath('error', 'Esta persona ya no está activa; elige a otra para asignarle la orden.');
    }

    public function test_choque_de_agenda_al_editar_se_rechaza_con_mensaje_claro(): void
    {
        $gestor = $this->gestor();
        $a      = $this->colaborador();
        $b      = $this->colaborador();
        $dia    = now()->addDays(3)->format('Y-m-d');
        $ocupa  = $this->orden($b, "$dia 12:00:00");
        $orden  = $this->orden($a, "$dia 08:00:00");

        // Pasarla a B a las 12:30 se encima con su orden de 12:00–13:00.
        $r = $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", [
            'colaborador_id' => $b->id, 'scheduled_at' => "$dia 12:30",
        ])->assertStatus(422);
        $this->assertStringStartsWith('Choque de agenda:', $r->json('error'));
        $this->assertStringContainsString("#{$ocupa->id}", $r->json('error'));
        $this->assertSame($ocupa->id, $r->json('choque'));
        $this->assertSame([$a->user_id], $orden->fresh()->users()->pluck('users.id')->all(), 'no se guardó nada');
        $this->assertSame("$dia 08:00:00", $orden->fresh()->start_time);

        // Mover solo la hora de A sobre una orden suya también choca.
        $this->orden($a, "$dia 15:00:00");
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['scheduled_at' => "$dia 14:30"])->assertStatus(422);

        // Justo al terminar la otra (13:00) no se encima; sin hora (solo el día) nunca choca.
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", [
            'colaborador_id' => $b->id, 'scheduled_at' => "$dia 13:00",
        ])->assertOk();
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['scheduled_at' => $dia])->assertOk();

        // Una orden ya terminada no ocupa la franja.
        $hecha = $this->orden($a, "$dia 18:00:00", 'Done');
        $otra  = $this->orden($a, "$dia 07:00:00");
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$otra->id}", ['scheduled_at' => "$dia 18:00"])->assertOk();
        $this->assertNotNull($hecha->fresh());
    }

    public function test_fuera_de_pendiente_no_se_edita_ni_elimina_aunque_se_llame_a_la_api(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $dia    = now()->addDays(4)->format('Y-m-d');
        $casos  = [
            'en curso'   => $this->orden($col, "$dia 08:00:00", 'InProgress'),
            'completada' => $this->orden($col, "$dia 10:00:00", 'Done'),
            'validada'   => $this->orden($col, "$dia 12:00:00", 'Done', ['validated_at' => now()]),
            'cancelada'  => $this->orden($col, "$dia 14:00:00", 'Archivado'),
        ];

        foreach ($casos as $caso => $orden) {
            $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'no debe entrar'])
                ->assertStatus(422)
                ->assertJsonPath('error', OrdenPendienteService::MSG_NO_EDITABLE)
                ->assertJsonPath('ya_no_pendiente', true);
            $this->actingAs($gestor)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'duplicada'])
                ->assertStatus(422)
                ->assertJsonPath('error', OrdenPendienteService::MSG_NO_ELIMINABLE)
                ->assertJsonPath('ya_no_pendiente', true);

            $fresca = Task::find($orden->id);
            $this->assertNotNull($fresca, "$caso: no se eliminó");
            $this->assertSame('nota original', $fresca->description, "$caso: no se editó");
        }
        $this->assertSame(0, TalentoOrderEvent::whereIn('orden_id', collect($casos)->pluck('id'))->where('origen', 'task')->count());
    }

    public function test_pendiente_atrasada_si_se_edita_y_se_elimina(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $ayer   = now()->subDay()->format('Y-m-d');
        $orden  = $this->orden($col, "$ayer 10:00:00");

        $item = collect($this->actingAs($gestor)->getJson("/talento/api/ordenes?status=atrasadas&colaborador_id={$col->id}&per_page=100")->assertOk()->json('data'))
            ->firstWhere('id', $orden->id);
        $this->assertTrue($item['atrasada']);
        $this->assertSame('pending', $item['status']);

        // Editar sin mover el día: se permite aunque el día ya pasó.
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'llamar antes'])->assertOk();
        // Moverla hacia atrás no: misma regla que Reprogramar.
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['scheduled_at' => "$ayer 16:00"])
            ->assertStatus(422)->assertJsonPath('error', 'La nueva fecha debe ser de hoy en adelante.');

        $this->actingAs($gestor)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'cliente_cancelo'])->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $orden->id]);
    }

    public function test_sin_permiso_la_api_responde_403_y_los_permisos_son_independientes(): void
    {
        $col   = $this->colaborador();
        $orden = $this->orden($col, now()->addDays(2)->format('Y-m-d') . ' 10:00:00');

        $soloVer = $this->usuario(self::VER);
        $this->actingAs($soloVer)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'x'])->assertForbidden();
        $this->actingAs($soloVer)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'duplicada'])->assertForbidden();
        $this->actingAs($soloVer)->getJson('/talento/api/ordenes?eliminadas=1')->assertForbidden();

        $soloEditar = $this->usuario([...self::VER, 'talento.ordenes.editar']);
        $this->actingAs($soloEditar)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'duplicada'])->assertForbidden();
        $this->actingAs($soloEditar)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'x'])->assertOk();

        $soloEliminar = $this->usuario([...self::VER, 'talento.ordenes.eliminar']);
        $this->actingAs($soloEliminar)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'y'])->assertForbidden();
        $this->actingAs($soloEliminar)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'duplicada'])->assertOk();
        $this->assertSame('x', Task::withTrashed()->find($orden->id)->description);
    }

    public function test_eliminar_exige_motivo_y_con_otro_exige_texto(): void
    {
        $gestor = $this->gestor();
        $orden  = $this->orden($this->colaborador(), now()->addDay()->format('Y-m-d') . ' 10:00:00');
        $url    = "/talento/api/ordenes/{$orden->id}";

        $this->actingAs($gestor)->deleteJson($url, [])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->actingAs($gestor)->deleteJson($url, ['motivo' => 'porque si'])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->actingAs($gestor)->deleteJson($url, ['motivo' => 'otro'])->assertStatus(422)->assertJsonValidationErrors('detalle');
        $this->actingAs($gestor)->deleteJson($url, ['motivo' => 'otro', 'detalle' => '   '])->assertStatus(422)->assertJsonValidationErrors('detalle');
        $this->assertNotNull(Task::find($orden->id));

        $this->actingAs($gestor)->deleteJson($url, ['motivo' => 'otro', 'detalle' => 'Se capturó en otra sucursal'])->assertOk();
        $this->assertSame('Otro: Se capturó en otra sucursal', Task::withTrashed()->find($orden->id)->motivo_eliminacion);
    }

    public function test_eliminar_es_logico_libera_agenda_avisa_y_no_toca_el_prospecto(): void
    {
        Notification::fake();
        $gestor   = $this->gestor();
        $col      = $this->colaborador();
        $dia      = now()->addDays(2)->format('Y-m-d');
        $leadId   = 987654321; // id ficticio: tasks.crm_lead_id no tiene llave foránea
        $orden    = $this->orden($col, "$dia 10:00:00", 'ToDo', ['crm_lead_id' => $leadId]);
        $agenda   = app(AgendaOrdenesService::class);
        $this->assertContains($orden->id, array_column($agenda->carga($dia, $dia, [$col->id])['ordenes'], 'id'));

        $r = $this->actingAs($gestor)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'duplicada'])->assertOk();
        $this->assertSame($leadId, $r->json('ligada.prospecto_id'));

        // Borrado lógico con motivo, quién y cuándo; el prospecto ligado sigue igual.
        $this->assertNull(Task::find($orden->id));
        $borrada = Task::withTrashed()->find($orden->id);
        $this->assertNotNull($borrada->deleted_at);
        $this->assertSame('Duplicada', $borrada->motivo_eliminacion);
        $this->assertSame($gestor->id, (int) $borrada->eliminada_por);
        $this->assertSame($leadId, (int) $borrada->crm_lead_id);
        $this->assertSame('ToDo', $borrada->status);

        // Agenda libre: ya no sale en la carga y otra orden puede ocupar esa misma hora.
        $this->assertNotContains($orden->id, array_column($agenda->carga($dia, $dia, [$col->id])['ordenes'], 'id'));
        $this->assertNull(app(OrdenPendienteService::class)->choqueDeAgenda($col->user_id, \Carbon\Carbon::parse("$dia 10:00")));

        // Bitácora: evento de la orden y activity_log.
        $evento = TalentoOrderEvent::deOrden('task', $orden->id)->where('evento', 'order.deleted')->first();
        $this->assertSame('Duplicada', $evento->motivo);
        $this->assertSame($gestor->id, (int) $evento->actor_id);
        $this->assertSame($leadId, $evento->datos['prospecto_id']);
        $this->assertTrue(DB::table('activity_log')->where('subject_type', Task::class)->where('subject_id', $orden->id)
            ->where('description', 'Orden de trabajo eliminada')->exists());

        Notification::assertSentTo($col->user, StandardNotification::class,
            fn ($n, $c, $u) => self::tituloAviso($n, $u) === AvisoOrdenColaborador::TITULO_ELIMINADA);

        // Ya eliminada: un segundo intento (otra pestaña) se entera de que ya no está pendiente.
        $this->actingAs($gestor)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'duplicada'])
            ->assertStatus(422)->assertJsonPath('ya_no_pendiente', true);
        $this->actingAs($gestor)->putJson("/talento/api/ordenes/{$orden->id}", ['notes' => 'x'])
            ->assertStatus(422)->assertJsonPath('ya_no_pendiente', true);
    }

    public function test_eliminadas_fuera_del_listado_normal_y_visibles_con_el_filtro(): void
    {
        $gestor = $this->gestor();
        $col    = $this->colaborador();
        $dia    = now()->addDays(2)->format('Y-m-d');
        $viva   = $this->orden($col, "$dia 08:00:00");
        $orden  = $this->orden($col, "$dia 10:00:00");
        $this->actingAs($gestor)->deleteJson("/talento/api/ordenes/{$orden->id}", ['motivo' => 'creada_por_error'])->assertOk();

        $params = "colaborador_id={$col->id}&per_page=100";
        $normal = collect($this->actingAs($gestor)->getJson("/talento/api/ordenes?$params")->assertOk()->json('data'))->pluck('id');
        $this->assertContains($viva->id, $normal);
        $this->assertNotContains($orden->id, $normal);

        $eliminadas = collect($this->actingAs($gestor)->getJson("/talento/api/ordenes?$params&eliminadas=1")->assertOk()->json('data'));
        $this->assertSame([$orden->id], $eliminadas->pluck('id')->all());
        $this->assertSame('Creada por error', $eliminadas[0]['eliminada']['motivo']);
        $this->assertNotNull($eliminadas[0]['eliminada']['cuando']);

        // El detalle de una eliminada lo abre quien puede eliminar; para los demás ya no existe.
        $this->actingAs($gestor)->getJson("/talento/api/ordenes/{$orden->id}")->assertOk()
            ->assertJsonPath('eliminada.motivo', 'Creada por error');
        $this->actingAs($this->usuario(self::VER))->getJson("/talento/api/ordenes/{$orden->id}")->assertNotFound();
    }
}
