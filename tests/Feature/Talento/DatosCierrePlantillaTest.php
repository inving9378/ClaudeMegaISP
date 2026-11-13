<?php

namespace Tests\Feature\Talento;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #1202 (Fase D de planta externa): el técnico captura en la app o en el portal los campos de la
 * plantilla que van al cerrar y cuánto usó de cada material; no completa si falta un obligatorio.
 * Datos sintéticos; correr SOLO por nombre (megaisp_test, transacción que se deshace).
 */
class DatosCierrePlantillaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const ESQUEMA = [
        ['key' => 'zona', 'type' => 'texto', 'when' => 'creacion', 'label' => 'Zona', 'required' => true, 'sort_order' => 10],
        ['key' => 'potencia', 'type' => 'numero', 'unit' => 'dBm', 'when' => 'cierre', 'label' => 'Potencia', 'required' => true, 'sort_order' => 20],
        ['key' => 'coordenadas', 'type' => 'coordenadas', 'when' => 'cierre', 'label' => 'Coordenadas', 'required' => false, 'sort_order' => 30],
        ['key' => 'raquetas', 'type' => 'tabla', 'when' => 'cierre', 'label' => 'Raquetas', 'required' => false, 'sort_order' => 40,
            'dynamic' => true, 'max_rows' => 10, 'columns' => [['key' => 'coordenadas', 'type' => 'coordenadas', 'label' => 'Coordenadas']]],
    ];

    private int $tecnicoId;

    private int $tareaId;

    private array $materiales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $tipo = DB::table('talento_work_order_types')->insertGetId([
            'name' => 'Orden sintética ' . Str::random(6), 'category' => 'campo', 'area' => 'planta_externa',
            'points' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tecnicoId = $this->usuario();
        DB::table('talento_colaboradores')->insert([
            'user_id' => $this->tecnicoId, 'type' => 'interno', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tareaId = DB::table('tasks')->insertGetId([
            'title' => 'Orden de prueba', 'status' => 'InProgress', 'tipo' => 'campo', 'talento_type_id' => $tipo,
            'start_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('task_user')->insert(['task_id' => $this->tareaId, 'user_id' => $this->tecnicoId]);
        DB::table('talento_work_order_data')->insert([
            'tarea_id' => $this->tareaId, 'schema_snapshot' => json_encode(self::ESQUEMA), 'values' => json_encode(['zona' => 'Z1']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['Fibra', 'Herrajes'] as $i => $m) {
            $this->materiales[] = DB::table('talento_work_order_checklist_items')->insertGetId([
                'tarea_id' => $this->tareaId, 'label' => $m, 'unidad' => $i ? 'pzas' : 'm', 'sort_order' => $i,
                'checked' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function usuario(array $permisos = []): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Sintético', 'father_last_name' => Str::random(6), 'email' => Str::random(10) . '@example.test',
            'login_user' => 'sint_' . Str::random(8), 'password' => 'x', 'estado' => 'activo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permisos && User::find($id)->givePermissionTo($permisos);

        return $id;
    }

    private function comoTecnico(): static
    {
        Sanctum::actingAs(User::find($this->tecnicoId));

        return $this;
    }

    private function guardar(array $datos, array $checklist = [])
    {
        return $this->comoTecnico()->putJson("/talento/api/ots/{$this->tareaId}/datos-cierre", ['datos' => $datos, 'checklist' => $checklist]);
    }

    public function test_el_tecnico_llena_los_datos_del_cierre_y_hasta_entonces_completa(): void
    {
        $ot = $this->comoTecnico()->getJson("/talento/api/ots/{$this->tareaId}")->assertOk()->json('ot');
        $this->assertSame(['potencia', 'coordenadas', 'raquetas'], array_column($ot['plantilla']['campos'], 'key'), 'solo los de cierre');
        $this->assertSame(['Potencia'], $ot['plantilla']['faltan']);
        $this->assertCount(2, $ot['plantilla']['checklist']);

        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/completar")
            ->assertStatus(422)->assertJsonPath('code', 'DATOS_CIERRE_FALTANTES');

        // Se guarda el avance sin lo obligatorio; un valor mal escrito sí se rechaza.
        $this->guardar(['coordenadas' => '19.43,-99.13'])->assertOk()->assertJsonPath('plantilla.faltan', ['Potencia']);
        $this->guardar(['potencia' => 'muy baja'])->assertStatus(422)->assertJsonStructure(['errors' => ['datos.potencia']]);
        $this->guardar(['zona' => 'Z9'])->assertStatus(422);

        $this->guardar(
            ['potencia' => '-21,5', 'coordenadas' => '19.43,-99.13', 'raquetas' => [['coordenadas' => '19.44,-99.12']]],
            // Con cantidad cuenta como usado aunque la casilla venga sin marcar (así lo manda el portal).
            [['id' => $this->materiales[0], 'checked' => false, 'cantidad_usada' => 150], ['id' => $this->materiales[1], 'checked' => false]]
        )->assertOk()->assertJsonPath('plantilla.faltan', []);

        $valores = json_decode(DB::table('talento_work_order_data')->where('tarea_id', $this->tareaId)->value('values'), true);
        $this->assertSame('Z1', $valores['zona'], 'lo de la creación no se toca');
        $this->assertEquals(-21.5, $valores['potencia']);
        $fibra = DB::table('talento_work_order_checklist_items')->find($this->materiales[0]);
        $this->assertEquals(150, $fibra->cantidad_usada);
        $this->assertSame(1, (int) $fibra->checked);
        $this->assertSame($this->tecnicoId, (int) $fibra->checked_by);

        $this->comoTecnico()->postJson("/talento/api/ots/{$this->tareaId}/completar")->assertOk();
        // Terminada, ya no se cambia.
        $this->guardar(['potencia' => -22])->assertStatus(422)->assertJsonPath('code', 'ESTADO_INVALIDO');
    }

    public function test_un_material_de_otra_orden_o_una_orden_ajena_no(): void
    {
        $otraOrden = DB::table('tasks')->insertGetId(['title' => 'Otra', 'status' => 'InProgress', 'created_at' => now(), 'updated_at' => now()]);
        $otro = DB::table('talento_work_order_checklist_items')->insertGetId([
            'tarea_id' => $otraOrden, 'label' => 'Ajeno', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->guardar([], [['id' => $otro, 'cantidad_usada' => 3]])->assertStatus(422);
        $this->guardar([], [['id' => $this->materiales[0], 'cantidad_usada' => -1]])->assertStatus(422);

        Sanctum::actingAs(User::find($this->usuario()));
        $this->putJson("/talento/api/ots/{$this->tareaId}/datos-cierre", ['datos' => ['potencia' => -20]])->assertStatus(403);
    }

    public function test_desde_el_portal_web_tambien(): void
    {
        Permission::firstOrCreate(['name' => 'portal.colaborador', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::find($this->tecnicoId)->givePermissionTo('portal.colaborador');

        $detalle = $this->actingAs(User::find($this->tecnicoId))->getJson("/talento/portal/ot/task/{$this->tareaId}")->assertOk();
        $this->assertSame(['Potencia'], $detalle->json('plantilla.faltan'));
        $this->actingAs(User::find($this->tecnicoId))
            ->putJson("/talento/portal/ot/task/{$this->tareaId}/datos-cierre", ['datos' => ['potencia' => -20]])
            ->assertOk()->assertJsonPath('plantilla.faltan', []);
    }
}
