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
 * Item #1285: documentación de la orden en su detalle (web): evidencias obligatorias, fotos, dBm,
 * nota del técnico, incidencias y cronología. La ve quien puede abrir el detalle; las fotos además
 * exigen talento.media.view y las sensibles talento.media.view_sensitive.
 * Datos sintéticos en megaisp_test; DatabaseTransactions: nada queda escrito.
 */
class OrdenDocumentacionDetalleTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private int $tarea;

    private int $evFoto;

    private int $evDbm;

    private int $evFirma;

    private int $evSerial;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['talento.work_orders.view', 'talento.work_orders.manage', 'talento.media.view', 'talento.media.view_sensitive'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $tipo = DB::table('talento_work_order_types')->insertGetId(['name' => 'Tipo documentación ' . uniqid(), 'category' => 'campo', 'area' => 'cliente', 'points' => 0, 'is_billable' => 0, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->evFoto = DB::table('talento_evidence_types')->insertGetId(['name' => 'Foto sintética', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->evDbm = DB::table('talento_evidence_types')->insertGetId(['name' => 'Lectura sintética', 'active' => 1, 'es_lectura_dbm' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->evFirma = DB::table('talento_evidence_types')->insertGetId(['name' => 'Firma sintética', 'active' => 1, 'es_firma' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->evSerial = DB::table('talento_evidence_types')->insertGetId(['name' => 'Serial sintético', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$this->evFoto, null], [$this->evDbm, null], [$this->evFirma, null], [$this->evSerial, 'cambio_equipo']] as [$ev, $cond]) {
            DB::table('talento_ot_type_evidence_requirements')->insert(['ot_type_id' => $tipo, 'evidence_type_id' => $ev, 'condition' => $cond, 'created_at' => now(), 'updated_at' => now()]);
        }

        $creador = User::create(['name' => 'Creador', 'father_last_name' => 'Sintético', 'login_user' => 'prueba_doc_creador_' . uniqid(), 'estado' => 'activo'])->fresh();
        $this->tarea = DB::table('tasks')->insertGetId([
            'title' => '[Sintético] orden documentada', 'talento_type_id' => $tipo, 'nota_tecnico' => "Se cambió el conector.\nCliente conforme.",
            'created_by' => $creador->id, 'finish_at' => now()->subHour(), 'completed_by' => $creador->id, 'created_at' => now()->subDay(), 'updated_at' => now(),
        ]);
        DB::table('talento_work_order_media')->insert([
            ['tarea_id' => $this->tarea, 'evidence_type_id' => $this->evFoto, 'type' => 'completion', 'file_path' => 'sintetico/a.jpg', 'potencia_dbm' => null, 'location_flagged' => 1, 'location_distance_m' => 140, 'captured_at' => now(), 'created_at' => now()],
            ['tarea_id' => $this->tarea, 'evidence_type_id' => $this->evFoto, 'type' => 'completion', 'file_path' => 'sintetico/a2.jpg', 'potencia_dbm' => null, 'location_flagged' => 0, 'location_distance_m' => null, 'captured_at' => now(), 'created_at' => now()],
            ['tarea_id' => $this->tarea, 'evidence_type_id' => $this->evDbm, 'type' => 'completion', 'file_path' => 'sintetico/d.jpg', 'potencia_dbm' => -19.5, 'location_flagged' => 0, 'location_distance_m' => null, 'captured_at' => now(), 'created_at' => now()],
            ['tarea_id' => $this->tarea, 'evidence_type_id' => null, 'type' => 'ine_front', 'file_path' => 'sintetico/b.jpg', 'potencia_dbm' => null, 'location_flagged' => 0, 'location_distance_m' => null, 'captured_at' => now(), 'created_at' => now()],
        ]);
        DB::table('talento_work_order_incidents')->insert(['tarea_id' => $this->tarea, 'motivo' => 'cliente_ausente', 'nota' => 'Regresé más tarde', 'server_at' => now()->subHours(3), 'created_by' => $creador->id, 'created_at' => now()->subHours(3), 'updated_at' => now()]);
    }

    private function persona(string $sufijo, array $roles, array $permisos = []): User
    {
        $user = User::create(['name' => 'Prueba Doc ' . $sufijo, 'login_user' => 'prueba_doc_' . $sufijo . '_' . uniqid(), 'estado' => 'activo'])->fresh();
        foreach ($roles as $rol) {
            $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']));
        }
        if ($permisos) {
            $user->givePermissionTo($permisos);
        }

        return $user;
    }

    private function url(): string
    {
        return "/talento/api/ordenes/{$this->tarea}/documentacion";
    }

    public function test_administracion_ve_toda_la_documentacion(): void
    {
        foreach (['Administrador', 'super-administrator', 'DESARROLLADOR'] as $rol) {
            $u = $this->persona(strtolower($rol), [$rol], ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.media.view', 'talento.media.view_sensitive']);
            $d = $this->actingAs($u)->getJson($this->url())->assertOk()->json();

            $this->assertTrue($d['puede_ver_fotos'], $rol);
            $this->assertCount(4, $d['fotos']);
            $this->assertTrue($d['fotos'][0]['fuera_de_lugar']);
            $this->assertStringContainsString('/talento/media/', $d['fotos'][0]['url']);
            $this->assertNotNull($d['fotos'][3]['url'], 'Con view_sensitive también la sensible');

            $req = collect($d['requeridas'])->keyBy('nombre');
            $this->assertSame(2, $req['Foto sintética']['fotos'], 'Varias fotos del mismo tipo');
            $this->assertTrue($req['Lectura sintética']['cumplida']);
            $this->assertFalse($req['Firma sintética']['cumplida'], 'La firma falta');
            $this->assertSame('cambio_equipo', $req['Serial sintético']['condicion']);
            $this->assertFalse($req['Serial sintético']['cumplida']);

            $this->assertSame(-19.5, $d['lecturas_dbm'][0]['valor']);
            $this->assertSame("Se cambió el conector.\nCliente conforme.", $d['nota_tecnico']);
            $this->assertSame('Cliente ausente', $d['incidencias'][0]['motivo']);
            $this->assertSame('Creador Sintético', $d['incidencias'][0]['quien']);
            $this->assertSame(['Creada', 'Terminada por el técnico'], array_column($d['cronologia'], 'paso'));
        }
    }

    public function test_sin_permiso_de_fotos_ve_lo_demas_y_sensible_sin_url(): void
    {
        $sinFotos = $this->persona('sinfotos', ['Mostrador'], ['talento.work_orders.view', 'talento.work_orders.manage']);
        $d = $this->actingAs($sinFotos)->getJson($this->url())->assertOk()->json();
        $this->assertFalse($d['puede_ver_fotos']);
        $this->assertNull($d['fotos']);
        $this->assertNotEmpty($d['requeridas']);

        $sinSensible = $this->persona('sinsensible', ['Mostrador'], ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.media.view']);
        $fotos = $this->actingAs($sinSensible)->getJson($this->url())->assertOk()->json('fotos');
        $this->assertNotNull($fotos[0]['url']);
        $this->assertTrue($fotos[3]['restringida']);
        $this->assertNull($fotos[3]['url']);
    }

    public function test_sin_acceso_a_la_orden_403_y_sin_sesion_401(): void
    {
        // Puede ver fotos pero la orden no es suya ni de su equipo.
        $ajeno = $this->persona('ajeno', ['TECNICO'], ['talento.work_orders.view', 'talento.media.view']);
        $this->actingAs($ajeno)->getJson($this->url())->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_orden_inexistente_404(): void
    {
        $u = $this->persona('admin404', ['super-administrator'], ['talento.work_orders.view', 'talento.work_orders.manage', 'talento.media.view']);
        $this->actingAs($u)->getJson('/talento/api/ordenes/999999999/documentacion')->assertNotFound();
    }
}
