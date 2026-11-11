<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * Item #1200 Fase C: pantalla «Plantillas de orden» (tipos y plantillas de planta externa).
 * Datos sintéticos en megaisp_test; DatabaseTransactions: nada queda escrito.
 */
class PlantillasOrdenAdminTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const API = '/talento/api/plantillas-orden';

    private User $admin;

    private int $proyecto;

    private int $evFoto;

    private int $evDbm;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'talento.work_order_templates.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'talento.work_orders.view', 'guard_name' => 'web']);
        $this->admin = $this->persona('admin', ['super-administrator']);
        $this->proyecto = DB::table('projects')->insertGetId(['title' => 'Proyecto de prueba planta externa', 'project_lead' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->evFoto = DB::table('talento_evidence_types')->insertGetId(['name' => 'Foto de prueba', 'active' => 1, 'es_lectura_dbm' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->evDbm = DB::table('talento_evidence_types')->insertGetId(['name' => 'Lectura de prueba dBm', 'active' => 1, 'es_lectura_dbm' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function persona(string $sufijo, array $roles): User
    {
        $user = User::create([
            'name' => 'Prueba Plantillas ' . $sufijo,
            'login_user' => 'prueba_plantillas_' . $sufijo . '_' . uniqid(),
            'estado' => 'activo',
        ])->fresh();
        foreach ($roles as $rol) {
            $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']));
        }
        if (in_array('super-administrator', $roles, true)) {
            $user->givePermissionTo(['talento.work_order_templates.manage', 'talento.work_orders.view']);
        }

        return $user;
    }

    private function crearTipo(array $extra = []): array
    {
        return $this->actingAs($this->admin)->postJson(self::API . '/tipos', $extra + [
            'name' => 'Tipo sintético ' . uniqid(), 'points' => 0, 'project_id' => $this->proyecto, 'evidencias' => [$this->evFoto],
        ])->assertCreated()->json();
    }

    /** Un campo de cada uno de los 9 tipos (la tabla con filas fijas). */
    private function nueveCampos(): array
    {
        return [
            ['label' => 'Zona', 'type' => 'texto', 'required' => true, 'identificador' => true],
            ['label' => 'Observaciones', 'type' => 'texto_largo'],
            ['label' => 'Caja', 'type' => 'numero', 'identificador' => true],
            ['label' => 'Tipo de caja', 'type' => 'seleccion', 'options' => ['NAP', 'Terminal']],
            ['label' => 'Herrajes', 'type' => 'multi_seleccion', 'options' => ['Brazo', 'Fleje', 'Hebilla']],
            ['label' => 'Etiquetado', 'type' => 'si_no', 'when' => 'cierre'],
            ['label' => 'Fecha de visita', 'type' => 'fecha'],
            ['label' => 'Coordenadas', 'type' => 'coordenadas', 'required' => true],
            ['label' => 'Conectores', 'type' => 'tabla', 'when' => 'cierre', 'rows_count' => 4, 'row_label_prefix' => 'Conector',
                'columns' => [['label' => 'Potencia', 'type' => 'numero', 'unit' => 'dBm'], ['label' => 'Estado', 'type' => 'seleccion', 'options' => ['Bien', 'Mal']]]],
        ];
    }

    private function crearPlantilla(int $tipoId, array $extra = [])
    {
        return $this->actingAs($this->admin)->postJson(self::API . '/plantillas', $extra + [
            'type_id' => $tipoId, 'name' => 'Plantilla sintética', 'title' => '', 'priority' => 'Media',
            'fields' => $this->nueveCampos(), 'checklist' => [['label' => 'Fleje', 'cantidad' => 3, 'unidad' => 'm'], ['label' => 'Cinchos']],
        ]);
    }

    public function test_tipo_de_planta_externa_con_reglas_fijas(): void
    {
        $tipo = $this->crearTipo();
        $this->assertSame('planta_externa', $tipo['area']);
        $this->assertSame('campo', $tipo['category']);
        $this->assertFalse($tipo['is_billable']);
        $this->assertSame([$this->evFoto], $tipo['evidencias']);
        $this->assertSame(0, (int) DB::table('talento_work_order_types')->where('id', $tipo['id'])->value('is_billable'));

        $this->actingAs($this->admin)->postJson(self::API . '/tipos', ['name' => 'Otro', 'points' => 0, 'project_id' => $this->proyecto, 'is_billable' => true])
            ->assertStatus(422)->assertJsonPath('errors.is_billable', fn ($m) => str_contains($m, '#1203'));
        $this->actingAs($this->admin)->postJson(self::API . '/tipos', ['name' => 'Otro', 'points' => 0, 'project_id' => $this->proyecto, 'evidencias' => [$this->evDbm]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['evidencias.0']]);
        $this->actingAs($this->admin)->postJson(self::API . '/tipos', ['name' => mb_strtoupper($tipo['name']), 'points' => 0, 'project_id' => $this->proyecto])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['name']]);
        $this->actingAs($this->admin)->postJson(self::API . '/tipos', ['name' => 'Sin proyecto', 'points' => 0, 'project_id' => 999999999])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['project_id']]);

        // Activar y desactivar: nunca se borra.
        $this->actingAs($this->admin)->patchJson(self::API . "/tipos/{$tipo['id']}/activo", ['active' => false])->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('talento_work_order_types', ['id' => $tipo['id'], 'active' => 0]);
    }

    public function test_los_tipos_de_cliente_no_se_tocan_aqui(): void
    {
        $cliente = DB::table('talento_work_order_types')->insertGetId(['name' => 'Tipo cliente sintético ' . uniqid(), 'category' => 'campo', 'area' => 'cliente', 'points' => 3, 'is_billable' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $ids = collect($this->actingAs($this->admin)->getJson(self::API . '/tipos')->assertOk()->json())->pluck('id');
        $this->assertFalse($ids->contains($cliente));
        $this->actingAs($this->admin)->putJson(self::API . "/tipos/$cliente", ['name' => 'X', 'points' => 0, 'project_id' => $this->proyecto])->assertNotFound();
        $this->actingAs($this->admin)->postJson(self::API . '/plantillas', ['type_id' => $cliente, 'name' => 'X', 'priority' => 'Media', 'fields' => []])->assertNotFound();
        $this->assertDatabaseHas('talento_work_order_types', ['id' => $cliente, 'area' => 'cliente', 'points' => 3, 'is_billable' => 1]);
    }

    public function test_plantilla_con_los_nueve_tipos_y_claves_automaticas(): void
    {
        $tipo = $this->crearTipo();
        $p = $this->crearPlantilla($tipo['id'])->assertCreated()->json();

        $this->assertSame(['zona', 'observaciones', 'caja', 'tipo_de_caja', 'herrajes', 'etiquetado', 'fecha_de_visita', 'coordenadas', 'conectores'], array_column($p['fields'], 'key'));
        $this->assertSame(['potencia', 'estado'], array_column($p['fields'][8]['columns'], 'key'));
        $this->assertSame(9, $p['campos']);
        $this->assertSame(2, $p['materiales']);
        $this->assertSame('Plantilla sintética', $p['title'], 'Sin título usa el nombre');

        // Lo que pide el modal «Nueva orden» (endpoint de la Fase B) ya trae los 9 tipos.
        $modal = $this->actingAs($this->admin)->getJson('/talento/api/order-templates?type_id=' . $tipo['id'])->assertOk()->json();
        $tipos = collect($modal)->firstWhere('id', $p['id'])['fields'] ?? [];
        $this->assertEqualsCanonicalizing(['texto', 'texto_largo', 'numero', 'seleccion', 'multi_seleccion', 'si_no', 'fecha', 'coordenadas', 'tabla'], array_column($tipos, 'type'));

        // Renombrar la etiqueta no cambia la clave.
        $fields = $p['fields'];
        $fields[0]['label'] = 'Zona de trabajo';
        $editada = $this->actingAs($this->admin)->putJson(self::API . "/plantillas/{$p['id']}", ['name' => $p['name'], 'priority' => 'Alta', 'fields' => $fields, 'checklist' => $p['checklist']])
            ->assertOk()->json();
        $this->assertSame('zona', $editada['fields'][0]['key']);
        $this->assertSame('Zona de trabajo', $editada['fields'][0]['label']);
    }

    public function test_reglas_del_servidor_devuelven_422_junto_al_campo(): void
    {
        $tipo = $this->crearTipo();
        $casos = [
            'fields.0.type'          => [['label' => 'X', 'type' => 'desconocido']],
            'fields.0.options'       => [['label' => 'Tipo', 'type' => 'seleccion', 'options' => ['Una']]],
            'fields.0.identificador' => [['label' => 'Potencia', 'type' => 'numero', 'when' => 'cierre', 'identificador' => true]],
            'fields.0.label'         => [['label' => str_repeat('x', 81), 'type' => 'texto']],
        ];
        foreach ($casos as $ruta => $fields) {
            $this->crearPlantilla($tipo['id'], ['name' => 'Caso ' . $ruta, 'fields' => $fields])
                ->assertStatus(422)->assertJsonStructure(['error', 'errors' => [$ruta]]);
        }
        $this->crearPlantilla($tipo['id'], ['name' => 'Muchos', 'fields' => array_fill(0, 41, ['label' => 'C', 'type' => 'texto'])])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['fields']]);

        // Clave repetida: dos campos que dicen ser el mismo campo guardado.
        $p = $this->crearPlantilla($tipo['id'])->assertCreated()->json();
        $fields = $p['fields'];
        $fields[] = $fields[0];
        $this->actingAs($this->admin)->putJson(self::API . "/plantillas/{$p['id']}", ['name' => $p['name'], 'priority' => 'Media', 'fields' => $fields])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['fields.9.key']]);

        // Nombre repetido dentro del tipo.
        $this->crearPlantilla($tipo['id'], ['name' => 'PLANTILLA SINTÉTICA'])->assertStatus(422)->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_editar_la_plantilla_no_cambia_las_ordenes_ya_creadas(): void
    {
        $tipo = $this->crearTipo();
        $p = $this->crearPlantilla($tipo['id'])->assertCreated()->json();
        $tarea = DB::table('tasks')->insertGetId(['title' => '[Sintético] orden de prueba', 'talento_type_id' => $tipo['id'], 'created_at' => now(), 'updated_at' => now()]);
        $snapshot = json_encode($p['fields']);
        DB::table('talento_work_order_data')->insert(['tarea_id' => $tarea, 'template_id' => $p['id'], 'schema_snapshot' => $snapshot, 'values' => json_encode(['zona' => '3']), 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(1, $this->actingAs($this->admin)->getJson(self::API . "/plantillas/{$p['id']}")->json('ordenes'));
        $this->actingAs($this->admin)->putJson(self::API . "/plantillas/{$p['id']}", ['name' => $p['name'], 'priority' => 'Media', 'fields' => [['label' => 'Solo uno', 'type' => 'texto']]])->assertOk();

        $this->assertSame(json_decode($snapshot, true), json_decode(DB::table('talento_work_order_data')->where('tarea_id', $tarea)->value('schema_snapshot'), true));
    }

    public function test_duplicar_reordenar_activar_y_vista_previa(): void
    {
        $tipo = $this->crearTipo();
        $a = $this->crearPlantilla($tipo['id'], ['name' => 'Primera'])->assertCreated()->json();
        $copia = $this->actingAs($this->admin)->postJson(self::API . "/plantillas/{$a['id']}/duplicar")->assertCreated()->json();
        $this->assertSame('Primera (copia)', $copia['name']);
        $this->assertFalse($copia['active']);
        $this->assertSame(array_column($a['fields'], 'key'), array_column($copia['fields'], 'key'));

        $orden = $this->actingAs($this->admin)->postJson(self::API . "/plantillas/{$copia['id']}/mover", ['direccion' => 'arriba'])->assertOk()->json();
        $this->assertSame([$copia['id'], $a['id']], array_column($orden, 'id'));

        $this->actingAs($this->admin)->patchJson(self::API . "/plantillas/{$a['id']}/activo", ['active' => false])->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('talento_work_order_templates', ['id' => $a['id'], 'active' => 0, 'deleted_at' => null]);

        $vista = $this->actingAs($this->admin)->postJson(self::API . '/vista-previa', [
            'type_id' => $tipo['id'], 'title' => '', 'description' => 'Instrucciones sintéticas.',
            'fields' => $this->nueveCampos(), 'checklist' => [['label' => 'Fleje', 'cantidad' => 3, 'unidad' => 'm']],
            'valores' => ['zona' => 'Zona 3', 'caja' => 12],
        ])->assertOk()->json();
        $this->assertSame([], $vista['errors']);
        $this->assertSame('[' . $tipo['name'] . '] Zona 3 · Caja 12', $vista['titulo']);
        $this->assertStringContainsString("MATERIALES:\n- Fleje: 3 m", $vista['descripcion']);
        $this->assertStringContainsString('AL CERRAR SE CAPTURA: Etiquetado, Conectores', $vista['descripcion']);
    }

    public function test_exportar_e_importar_por_nombre_sin_ids(): void
    {
        $tipo = $this->crearTipo();
        $p = $this->crearPlantilla($tipo['id'], ['name' => 'Para exportar'])->assertCreated()->json();
        // Renombrada después de creada: la clave vieja se conserva al importar.
        $fields = $p['fields'];
        $fields[0]['label'] = 'Zona renombrada';
        $this->actingAs($this->admin)->putJson(self::API . "/plantillas/{$p['id']}", ['name' => $p['name'], 'priority' => 'Media', 'fields' => $fields, 'checklist' => $p['checklist']])->assertOk();

        $res = $this->actingAs($this->admin)->get(self::API . "/plantillas/{$p['id']}/exportar")->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $json = $res->json();
        $this->assertSame('megaisp.plantilla_orden', $json['formato']);
        $this->assertSame($tipo['name'], $json['tipo']['nombre']);
        $this->assertSame('Proyecto de prueba planta externa', $json['tipo']['proyecto']);
        $this->assertSame(['Foto de prueba'], $json['tipo']['evidencias']);
        $plano = json_encode($json);
        foreach (['"id"', '"type_id"', '"project_id"', '"created_by"', '"updated_by"'] as $llave) {
            $this->assertStringNotContainsString($llave, $plano, "El JSON no lleva $llave");
        }

        // Importar: misma plantilla, inactiva, con nombre libre y las claves del archivo.
        $imp = $this->actingAs($this->admin)->postJson(self::API . '/importar', ['archivo' => $json])->assertCreated()->json('plantilla');
        $this->assertSame('Para exportar (importada)', $imp['name']);
        $this->assertFalse($imp['active']);
        $this->assertSame('zona', $imp['fields'][0]['key']);
        $this->assertSame('Zona renombrada', $imp['fields'][0]['label']);
        $this->assertSame(count($p['fields']), count($imp['fields']));

        // Tipo o proyecto inexistente por nombre: error claro y nada creado.
        $antes = DB::table('talento_work_order_templates')->count();
        $sinTipo = $json;
        $sinTipo['tipo']['nombre'] = 'Tipo que no existe ' . uniqid();
        $this->actingAs($this->admin)->postJson(self::API . '/importar', ['archivo' => $sinTipo])->assertStatus(422)->assertJsonStructure(['error', 'errors' => ['tipo']]);
        $sinProyecto = $json;
        $sinProyecto['tipo']['proyecto'] = 'Proyecto que no existe ' . uniqid();
        $this->actingAs($this->admin)->postJson(self::API . '/importar', ['archivo' => $sinProyecto])->assertStatus(422)->assertJsonStructure(['errors' => ['proyecto']]);
        $this->actingAs($this->admin)->postJson(self::API . '/importar', ['archivo' => ['formato' => 'otro']])->assertStatus(422);
        $invalido = $json;
        $invalido['plantilla']['campos'][3]['options'] = ['Una sola'];
        $this->actingAs($this->admin)->postJson(self::API . '/importar', ['archivo' => $invalido])->assertStatus(422)->assertJsonStructure(['errors' => ['fields.3.options']]);
        $this->assertSame($antes, DB::table('talento_work_order_templates')->count());
    }

    public function test_sin_permiso_403_en_todas_las_rutas(): void
    {
        $tecnico = $this->persona('tecnico', ['TECNICO']);
        $tipo = $this->crearTipo();
        $p = $this->crearPlantilla($tipo['id'])->assertCreated()->json();

        $rutas = [
            ['get', self::API . '/catalogos'], ['get', self::API . '/tipos'], ['post', self::API . '/tipos'],
            ['put', self::API . "/tipos/{$tipo['id']}"], ['patch', self::API . "/tipos/{$tipo['id']}/activo"],
            ['get', self::API . "/tipos/{$tipo['id']}/plantillas"], ['post', self::API . '/plantillas'],
            ['post', self::API . '/vista-previa'], ['get', self::API . "/plantillas/{$p['id']}"],
            ['put', self::API . "/plantillas/{$p['id']}"], ['patch', self::API . "/plantillas/{$p['id']}/activo"],
            ['post', self::API . "/plantillas/{$p['id']}/duplicar"], ['post', self::API . "/plantillas/{$p['id']}/mover"],
            ['get', self::API . "/plantillas/{$p['id']}/exportar"], ['post', self::API . '/importar'],
            ['get', '/talento/plantillas-orden'],
        ];
        foreach ($rutas as [$metodo, $url]) {
            $this->actingAs($tecnico)->json(strtoupper($metodo), $url, [])->assertForbidden();
        }
        $this->assertSame(1, DB::table('talento_work_order_templates')->where('type_id', $tipo['id'])->count());
    }
}
