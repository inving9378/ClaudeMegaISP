<?php

namespace Tests\Feature\Modules\Talento;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoHallazgo;
use App\Modules\Addons\Talento\Models\TalentoHallazgoCategoria;
use App\Modules\Addons\Talento\Models\TalentoHallazgoFoto;
use App\Modules\Addons\Talento\Models\TalentoRecorrido;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;
use Tests\Unit\Talento\RecorridoFixtures as F;

/**
 * Item #1311 — Recorridos, fase (a): importar la ruta y las fotos de Geo Tracker, revisar los
 * hallazgos, recortar y enviar. Rutas, fotos, usuarios y colaborador 100 % sintéticos.
 * Correr SOLO por nombre: megaisp_test dentro de una transacción.
 */
class RecorridosImportarTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const PERMISOS = ['talento.recorridos.view', 'talento.recorridos.manage', 'talento.recorridos.categorias.manage'];

    private User $gestor;
    private TalentoColaborador $col;
    private int $t0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Storage::fake('local');
        foreach (self::PERMISOS as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->gestor = $this->usuario(self::PERMISOS);
        $this->col = TalentoColaborador::create(['user_id' => $this->usuario()->id, 'type' => 'interno', 'status' => 'active']);
        $this->t0 = Carbon::parse('2026-11-10 10:00:00', config('app.timezone'))->getTimestamp();
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

    /** 121 puntos hacia el este, uno cada 5 s y 5 m: 10 minutos, 600 m, de 10:00 a 10:10. */
    private function puntos(): array
    {
        return F::linea(121, $this->t0, 5, 5.0);
    }

    private function importar(string $contenido, string $nombre = 'ruta.gpx', ?User $como = null)
    {
        return $this->actingAs($como ?? $this->gestor)->post('/talento/api/recorridos', [
            'colaborador_id' => $this->col->id, 'modo' => 'a_pie', 'titulo' => 'Zona de prueba',
            'archivo' => UploadedFile::fake()->createWithContent($nombre, $contenido),
        ], ['Accept' => 'application/json']);
    }

    private function foto(int $recorrido, string $jpg, string $nombre = 'foto.jpg')
    {
        return $this->actingAs($this->gestor)->post("/talento/api/recorridos/$recorrido/fotos", [
            'foto' => UploadedFile::fake()->createWithContent($nombre, $jpg),
        ], ['Accept' => 'application/json']);
    }

    public function test_importa_gpx_y_ubica_las_fotos_por_exif_por_hora_o_las_deja_sin_ubicar(): void
    {
        $gpx = F::gpx($this->puntos());
        $r = $this->importar($gpx)->assertCreated();
        $rec = TalentoRecorrido::findOrFail($r->json('recorrido.id'));

        $this->assertSame(['gpx', 'borrador', 'supervisor', 121, 121], [$rec->fuente, $rec->estado, $rec->iniciado_por, $rec->puntos_total, $rec->puntos]);
        $this->assertEqualsWithDelta(600, $rec->distancia_m, 2);
        $this->assertCount(2, $rec->geometria, 'Una recta se simplifica a sus dos extremos');
        $this->assertSame(['2026-11-10 10:00:00', '2026-11-10 10:10:00'], [$rec->iniciado_at->format('Y-m-d H:i:s'), $rec->terminado_at->format('Y-m-d H:i:s')]);
        $this->assertSame($gpx, Storage::disk('local')->get("talento/recorridos/{$rec->id}/original.gpx"), 'El original se conserva tal cual');
        $this->assertArrayNotHasKey('archivo_ruta', $r->json('recorrido'));

        $conGps = F::jpeg(2400, 1800, 19.4011111, -99.1488888, '2026:11:10 10:02:00');
        $this->foto($rec->id, $conGps, 'gps.jpg')->assertCreated()->assertJsonPath('ubicacion', 'exif');
        $this->foto($rec->id, F::jpeg(1200, 900, null, null, '2026:11:10 10:05:00'))->assertCreated()->assertJsonPath('ubicacion', 'por_hora');
        $this->foto($rec->id, F::jpeg(800, 600))->assertCreated()->assertJsonPath('ubicacion', null);
        $this->foto($rec->id, $conGps, 'otra-vez.jpg')->assertOk()->assertJsonPath('duplicada', true);

        $h = TalentoHallazgo::where('recorrido_id', $rec->id)->orderBy('id')->get();
        $this->assertCount(3, $h);
        $this->assertSame([19.4011111, -99.1488888], [$h[0]->lat, $h[0]->lng]);
        $this->assertEqualsWithDelta($this->puntos()[60][1], $h[1]->lng, 0.0000002, '10:05 = punto 60 de la ruta');
        $this->assertNull($h[2]->lat);

        $foto = TalentoHallazgoFoto::where('hallazgo_id', $h[0]->id)->firstOrFail();
        $this->assertSame([1600, 1200], [$foto->ancho, $foto->alto], 'Se reduce a 1600 px por lado');
        $this->assertLessThan($foto->bytes_original, $foto->bytes);
        $this->assertSame('2026-11-10 10:02:00', $foto->exif_tomada_at->format('Y-m-d H:i:s'));
        [$ancho] = getimagesizefromstring(Storage::disk('local')->get($foto->ruta));
        $this->assertSame(1600, $ancho);

        $show = $this->actingAs($this->gestor)->getJson("/talento/api/recorridos/{$rec->id}")->assertOk();
        $this->assertSame(['categoría', 'severidad', 'descripción', 'ubicación'], $show->json('hallazgos.2.faltantes'));
        $this->assertTrue($show->json('puede_editar'));
        $this->assertCount(9, $show->json('categorias'));
        $this->actingAs($this->gestor)->get($show->json('hallazgos.0.fotos.0.url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->gestor)->get("/talento/api/recorridos/{$rec->id}/original")->assertOk();
    }

    public function test_recortar_recalcula_y_volver_a_la_ruta_completa_quita_el_recorte(): void
    {
        $id = $this->importar(F::gpx($this->puntos()))->json('recorrido.id');

        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id", ['recorte_desde' => 20, 'recorte_hasta' => 100])->assertOk();
        $rec = TalentoRecorrido::findOrFail($id);
        $this->assertSame([20, 100, 81, 121], [$rec->recorte_desde, $rec->recorte_hasta, $rec->puntos, $rec->puntos_total]);
        $this->assertEqualsWithDelta(400, $rec->distancia_m, 2);
        $this->assertSame('2026-11-10 10:01:40', $rec->iniciado_at->format('Y-m-d H:i:s'));

        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id", ['recorte_desde' => 50, 'recorte_hasta' => 50])->assertStatus(422);
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id", ['recorte_desde' => 0, 'recorte_hasta' => 120])->assertOk();
        $rec->refresh();
        $this->assertSame([null, null, 121], [$rec->recorte_desde, $rec->recorte_hasta, $rec->puntos]);
    }

    public function test_kml_con_horas_kmz_sin_horas_y_archivo_que_no_es_ruta(): void
    {
        $kml = $this->importar(F::kmlTrack($this->puntos()), 'geotracker.kml')->assertCreated();
        $rec = TalentoRecorrido::findOrFail($kml->json('recorrido.id'));
        $this->assertSame(['kml', '2026-11-10 10:00:00'], [$rec->fuente, $rec->iniciado_at?->format('Y-m-d H:i:s')]);

        $kmz = $this->importar(F::kmz(F::kmlLinea($this->puntos())), 'ruta.kmz')->assertCreated();
        $rec = TalentoRecorrido::findOrFail($kmz->json('recorrido.id'));
        $this->assertSame(['kmz', null, 121], [$rec->fuente, $rec->iniciado_at, $rec->puntos_total]);

        $this->importar('no soy una ruta', 'ruta.gpx')->assertStatus(422)->assertJsonPath('error', 'El archivo de ruta no es GPX, KML ni KMZ.');
        $this->importar('<gpx><trk><trkseg><trkpt lat="19.4" lon="-99.15"/></trkseg></trk></gpx>')->assertStatus(422);
        $this->assertSame(2, TalentoRecorrido::where('colaborador_id', $this->col->id)->count());
    }

    public function test_enviar_exige_hallazgos_completos_y_despues_ya_no_se_cambia_nada(): void
    {
        $id = $this->importar(F::gpx($this->puntos()))->json('recorrido.id');
        $this->foto($id, F::jpeg(800, 600, 19.4005, -99.1495))->assertCreated();
        $hid = TalentoHallazgo::where('recorrido_id', $id)->value('id');

        $this->actingAs($this->gestor)->postJson("/talento/api/recorridos/$id/enviar")->assertStatus(422)
            ->assertJsonPath("errors.hallazgos.$hid", ['categoría', 'severidad', 'descripción']);

        $otro = TalentoHallazgoCategoria::where('nombre', 'Otro')->value('id');
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$hid", [
            'categoria_id' => $otro, 'severidad' => 'alta', 'descripcion' => 'Cable de prueba colgando',
        ])->assertOk()->assertJsonPath('hallazgos.0.faltantes', []);

        $this->actingAs($this->gestor)->postJson("/talento/api/recorridos/$id/enviar")->assertOk()
            ->assertJsonPath('recorrido.estado', 'enviado')->assertJsonPath('puede_editar', false);

        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$hid", ['severidad' => 'baja'])->assertStatus(422);
        $this->foto($id, F::jpeg(640, 480))->assertStatus(422);
        $this->actingAs($this->gestor)->deleteJson("/talento/api/recorridos/$id")->assertStatus(422);
        $this->assertSame('alta', TalentoHallazgo::find($hid)->severidad);
    }

    public function test_ubicar_a_mano_unir_y_eliminar_hallazgos(): void
    {
        $id = $this->importar(F::gpx($this->puntos()))->json('recorrido.id');
        $this->foto($id, F::jpeg(800, 600))->assertCreated();
        $this->foto($id, F::jpeg(801, 600, 19.4002, -99.1497))->assertCreated();
        $this->foto($id, F::jpeg(802, 600))->assertCreated();
        [$a, $b, $c] = TalentoHallazgo::where('recorrido_id', $id)->orderBy('id')->pluck('id')->all();

        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$a", ['lat' => 19.4003, 'lng' => -99.1496])->assertOk();
        $this->assertSame(['manual', 19.4003], [TalentoHallazgo::find($a)->fuente_ubicacion, TalentoHallazgo::find($a)->lat]);

        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$b", ['descripcion' => 'Primera parte'])->assertOk();
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$c", ['descripcion' => 'Segunda parte'])->assertOk();
        $this->actingAs($this->gestor)->postJson("/talento/api/recorridos/$id/hallazgos/$c/unir", ['destino_id' => $b])->assertOk();
        $this->assertNull(TalentoHallazgo::withTrashed()->find($c));
        $this->assertSame(2, TalentoHallazgoFoto::where('hallazgo_id', $b)->count());
        $this->assertSame("Primera parte\nSegunda parte", TalentoHallazgo::find($b)->descripcion);

        $ruta = TalentoHallazgoFoto::where('hallazgo_id', $a)->value('ruta');
        $this->actingAs($this->gestor)->deleteJson("/talento/api/recorridos/$id/hallazgos/$a")->assertOk();
        $this->assertFalse(Storage::disk('local')->exists($ruta), 'Se borra también el archivo');
        $this->assertSame(1, TalentoHallazgo::where('recorrido_id', $id)->count());

        $otro = $this->importar(F::gpx($this->puntos()))->json('recorrido.id');
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$otro/hallazgos/$b", ['severidad' => 'baja'])->assertNotFound();
    }

    public function test_permisos_ver_editar_y_catalogo(): void
    {
        $id = $this->importar(F::gpx($this->puntos()))->json('recorrido.id');
        $lector = $this->usuario(['talento.recorridos.view']);
        $nadie = $this->usuario();

        $this->actingAs($lector)->getJson('/talento/api/recorridos?colaborador_id=' . $this->col->id)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($lector)->getJson("/talento/api/recorridos/$id")->assertOk()->assertJsonPath('puede_editar', false);
        $this->importar(F::gpx($this->puntos()), 'ruta.gpx', $lector)->assertForbidden();
        $this->actingAs($lector)->putJson("/talento/api/recorridos/$id", ['titulo' => 'x'])->assertForbidden();
        $this->actingAs($lector)->postJson('/talento/api/hallazgo-categorias', ['nombre' => 'Nueva'])->assertForbidden();
        $this->actingAs($nadie)->getJson('/talento/api/recorridos')->assertForbidden();

        $apellido = $this->col->user->father_last_name;
        $this->actingAs($lector)->getJson('/talento/api/recorridos/colaboradores?search=' . $apellido)->assertForbidden();
        $this->actingAs($this->gestor)->getJson('/talento/api/recorridos/colaboradores?search=Sint%C3%A9tico%20' . $apellido)->assertOk()
            ->assertJsonPath('0.id', $this->col->id)->assertJsonCount(1);

        $nueva = $this->actingAs($this->gestor)->postJson('/talento/api/hallazgo-categorias', [
            'nombre' => 'Categoría de prueba', 'severidad_sugerida' => 'media',
        ])->assertCreated();
        $this->assertGreaterThan(90, $nueva->json('orden'));
        $this->actingAs($this->gestor)->postJson('/talento/api/hallazgo-categorias', ['nombre' => 'Otro'])->assertStatus(422);

        $this->foto($id, F::jpeg(640, 480))->assertCreated();
        $hid = TalentoHallazgo::where('recorrido_id', $id)->value('id');
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$hid", ['categoria_id' => $nueva->json('id')])
            ->assertOk()->assertJsonPath('hallazgos.0.severidad', 'media');

        $this->actingAs($this->gestor)->putJson('/talento/api/hallazgo-categorias/' . $nueva->json('id'), [
            'nombre' => 'Categoría de prueba', 'activo' => false,
        ])->assertOk()->assertJsonPath('activo', false);
        $poste = TalentoHallazgoCategoria::where('nombre', 'Poste dañado o inclinado')->value('id');
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$hid", ['categoria_id' => $poste])->assertOk();
        $this->actingAs($this->gestor)->putJson("/talento/api/recorridos/$id/hallazgos/$hid", ['categoria_id' => $nueva->json('id')])
            ->assertStatus(422)->assertJsonValidationErrors('categoria_id');
    }
}
