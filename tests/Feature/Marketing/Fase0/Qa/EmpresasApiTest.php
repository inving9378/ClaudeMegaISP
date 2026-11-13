<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — API de Empresas, rutas nuevas 4–6 (§2.6) con ataque.
 * (La 3, la vista, la cubre PantallaEmpresasTest de frontend; aquí solo sus 403 en MatrizRolesTest.)
 */
class EmpresasApiTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    private const ISO_MX = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[56]:00$/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenario(conDatos: false);
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function nombreUnico(string $base = 'QA Empresa'): string
    {
        return $base . ' ' . Str::upper(Str::random(6));
    }

    // ── GET /api/marketing/empresas ──────────────────────────────────────────

    public function test_index_lista_solo_empresas_donde_soy_admin_activo(): void
    {
        $otra = $this->crearEmpresa('QA Otra');
        $deMkt = $this->crearEmpresa('QA DeMkt');
        $inactivaAsig = $this->crearEmpresa('QA AsigInactiva');
        $this->asignar($this->adminA, $deMkt->id, 'marketing');
        $this->asignar($this->adminA, $inactivaAsig->id, 'admin', activo: false);

        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/empresas');
        $r->assertOk()->assertJsonPath('ok', true);

        $ids = array_column($r->json('data'), 'id');
        $this->assertContains(1, $ids);
        $this->assertNotContains($this->empresaB->id, $ids, 'B: no es miembro');
        $this->assertNotContains($otra->id, $ids);
        $this->assertNotContains($deMkt->id, $ids, 'es marketing, no admin');
        $this->assertNotContains($inactivaAsig->id, $ids, 'asignación inactiva');
    }

    public function test_index_forma_y_conteos(): void
    {
        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/empresas');
        $fila = collect($r->json('data'))->firstWhere('id', 1);
        $this->assertNotNull($fila);

        $this->assertSame(
            ['id', 'nombre', 'slug', 'color_primario', 'color_secundario', 'activo', 'es_principal', 'miembros', 'admins', 'created_at'],
            array_keys($fila)
        );
        $this->assertTrue($fila['activo']);
        $this->assertTrue($fila['es_principal']);
        $this->assertSame(
            DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('activo', 1)->count(),
            $fila['miembros']
        );
        $this->assertSame(
            DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('activo', 1)->where('rol', 'admin')->count(),
            $fila['admins']
        );
        $this->assertMatchesRegularExpression(self::ISO_MX, (string) $fila['created_at'], 'fecha ISO-8601 con zona de México');
    }

    public function test_conteos_ignoran_asignaciones_inactivas(): void
    {
        $antes = collect($this->como($this->adminA, 1)->getJson('/api/marketing/empresas')->json('data'))->firstWhere('id', 1);
        $u = $this->crearUsuario('qa_inact', []);
        $this->asignar($u, 1, 'admin', activo: false);
        $despues = collect($this->como($this->adminA, 1)->getJson('/api/marketing/empresas')->json('data'))->firstWhere('id', 1);

        $this->assertSame($antes['miembros'], $despues['miembros']);
        $this->assertSame($antes['admins'], $despues['admins']);
    }

    // ── POST /api/marketing/empresas ─────────────────────────────────────────

    public function test_crear_empresa_201_y_quien_la_crea_queda_admin(): void
    {
        $nombre = $this->nombreUnico('Meganet Empresarial');
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', [
            'nombre' => $nombre, 'slug' => null, 'color_primario' => '#1F3A93', 'color_secundario' => null,
        ]);
        $r->assertStatus(201)->assertJsonPath('ok', true);

        $data = $r->json('data');
        $this->assertSame(
            ['id', 'nombre', 'slug', 'color_primario', 'color_secundario', 'activo', 'es_principal', 'miembros', 'admins', 'created_at'],
            array_keys($data)
        );
        $this->assertSame($nombre, $data['nombre']);
        $this->assertSame(Str::slug($nombre), $data['slug'], 'sin slug → Str::slug(nombre)');
        $this->assertTrue($data['activo']);
        $this->assertFalse($data['es_principal']);
        $this->assertSame(1, $data['miembros']);
        $this->assertSame(1, $data['admins']);

        $asig = DB::table('marketing_empresa_usuario')->where('company_id', $data['id'])->get();
        $this->assertCount(1, $asig);
        $this->assertSame($this->adminA->id, (int) $asig[0]->user_id);
        $this->assertSame('admin', $asig[0]->rol);
        $this->assertSame(1, (int) $asig[0]->activo);

        $this->assertSame($this->adminA->id, (int) DB::table('marketing_empresas')->where('id', $data['id'])->value('created_by'));
    }

    public function test_crear_ignora_activo_id_y_es_principal_del_request(): void
    {
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', [
            'nombre' => $this->nombreUnico(), 'activo' => false, 'id' => 1, 'es_principal' => true, 'company_id' => 1,
        ]);
        $r->assertStatus(201);
        $this->assertTrue($r->json('data.activo'), 'activo no se acepta al crear (siempre true)');
        $this->assertNotSame(1, $r->json('data.id'));
        $this->assertSame('Meganet', DB::table('marketing_empresas')->where('id', 1)->value('nombre'), 'la principal no se toca');
    }

    public function test_crear_recorta_espacios_del_nombre(): void
    {
        $base = $this->nombreUnico('Recortada');
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', ['nombre' => "   {$base}   "]);
        $r->assertStatus(201);
        $this->assertSame($base, $r->json('data.nombre'));
    }

    public function test_crear_con_acentos_y_emojis(): void
    {
        $nombre = 'Café Ñandú 🚀 ' . Str::upper(Str::random(4));
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', ['nombre' => $nombre, 'slug' => 'cafe-' . Str::lower(Str::random(6))]);
        $r->assertStatus(201);
        $this->assertSame($nombre, DB::table('marketing_empresas')->where('id', $r->json('data.id'))->value('nombre'));
    }

    public function test_crear_con_html_se_guarda_literal_y_no_se_interpreta(): void
    {
        $nombre = '<script>alert(1)</script>' . Str::random(4);
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', ['nombre' => $nombre, 'slug' => 'xss-' . Str::lower(Str::random(6))]);
        $r->assertStatus(201);
        $this->assertSame($nombre, $r->json('data.nombre'), 'el API devuelve el texto tal cual (el escape es del front, sin v-html)');
        $this->assertStringContainsString('application/json', (string) $r->headers->get('Content-Type'));
    }

    public static function nombresInvalidos(): array
    {
        return [
            'faltante'         => [null, false],
            'vacío'            => ['', true],
            'solo espacios'    => ['     ', true],
            '1 carácter'       => ['A', true],
            '121 caracteres'   => [str_repeat('x', 121), true],
            '10 000 caracteres'=> [str_repeat('ñ', 10000), true],
            'array'            => [['Meganet'], true],
            'objeto'           => [['a' => 'b'], true],
            'número'           => [12345, true],
        ];
    }

    /** @dataProvider nombresInvalidos */
    public function test_crear_con_nombre_invalido_da_422(mixed $nombre, bool $enviar): void
    {
        $antes = DB::table('marketing_empresas')->count();
        $cuerpo = $enviar ? ['nombre' => $nombre, 'slug' => 'qa-' . Str::lower(Str::random(8))] : ['slug' => 'qa-' . Str::lower(Str::random(8))];

        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', $cuerpo);
        if ($nombre === 12345) {
            // Un número convertible a texto de 5 caracteres puede aceptarse; lo que no puede es tronar.
            $this->assertContains($r->status(), [201, 422]);
            return;
        }
        $r->assertStatus(422)->assertJsonValidationErrors(['nombre']);
        $this->assertSame($antes, DB::table('marketing_empresas')->count());
    }

    public function test_crear_nombre_duplicado_sin_distinguir_mayusculas_da_422(): void
    {
        foreach (['MEGANET', 'meganet', ' Meganet '] as $nombre) {
            $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', ['nombre' => $nombre, 'slug' => 'qa-' . Str::lower(Str::random(8))]);
            $r->assertStatus(422)->assertJsonValidationErrors(['nombre']);
        }
    }

    public static function slugsInvalidos(): array
    {
        return [
            'espacios'       => ['con espacios'],
            'mayúsculas'     => ['ConMayus'],
            'guion inicial'  => ['-abc'],
            'guion final'    => ['abc-'],
            'doble guion'    => ['a--b'],
            'guion bajo'     => ['slug_con_guion'],
            'eñe'            => ['ñandu'],
            '1 carácter'     => ['a'],
            '61 caracteres'  => [str_repeat('a', 61)],
            'sql'            => ["x'; DROP TABLE marketing_empresas; --"],
            'html'           => ['<b>x</b>'],
            'array'          => [['abc']],
            'principal'      => ['meganet'],
        ];
    }

    /** @dataProvider slugsInvalidos */
    public function test_crear_con_slug_invalido_o_duplicado_da_422(mixed $slug): void
    {
        $antes = DB::table('marketing_empresas')->count();
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', ['nombre' => $this->nombreUnico(), 'slug' => $slug]);
        if (is_string($slug) && str_contains($slug, 'DROP')) {
            $this->assertContains($r->status(), [403, 422], 'WAF (403) o validación (422)');
        } else {
            $r->assertStatus(422)->assertJsonValidationErrors(['slug']);
        }
        $this->assertSame($antes, DB::table('marketing_empresas')->count());
        $this->assertSame(1, DB::table('marketing_empresas')->where('slug', 'meganet')->count());
    }

    public static function coloresInvalidos(): array
    {
        return [
            'corto'     => ['#12345'],
            'largo'     => ['#1234567'],
            'sin #'     => ['123456'],
            'nombre'    => ['red'],
            'no hex'    => ['#GGGGGG'],
            'rgb()'     => ['rgb(0,0,0)'],
            'array'     => [['#123456']],
            'css inyect'=> ['#123456;background:url(x)'],
        ];
    }

    /** @dataProvider coloresInvalidos */
    public function test_crear_con_color_invalido_da_422(mixed $color): void
    {
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', [
            'nombre' => $this->nombreUnico(), 'color_primario' => $color, 'color_secundario' => $color,
        ]);
        $r->assertStatus(422)->assertJsonValidationErrors(['color_primario', 'color_secundario']);
    }

    public function test_crear_acepta_colores_validos_y_nulos(): void
    {
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', [
            'nombre' => $this->nombreUnico(), 'color_primario' => '#abcDEF', 'color_secundario' => null,
        ]);
        $r->assertStatus(201);
        $this->assertNull($r->json('data.color_secundario'));
    }

    public function test_doble_envio_no_duplica_la_empresa(): void
    {
        $cuerpo = ['nombre' => $this->nombreUnico('Doble'), 'slug' => 'doble-' . Str::lower(Str::random(6))];
        $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', $cuerpo)->assertStatus(201);
        $this->contexto()->limpiar();
        $this->postJson('/api/marketing/empresas', $cuerpo)->assertStatus(422);

        $this->assertSame(1, DB::table('marketing_empresas')->where('slug', $cuerpo['slug'])->count());
    }

    public function test_crear_empresa_no_toca_roles_ni_permisos_spatie(): void
    {
        $rolesAntes = DB::table('model_has_roles')->count();
        $permsAntes = DB::table('model_has_permissions')->count();

        $this->como($this->adminA, 1)->postJson('/api/marketing/empresas', ['nombre' => $this->nombreUnico()])->assertStatus(201);

        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count());
        $this->assertSame($permsAntes, DB::table('model_has_permissions')->count());
    }

    // ── PUT /api/marketing/empresas/{id} ─────────────────────────────────────

    public function test_actualizar_subconjunto_de_campos(): void
    {
        $nuevo = $this->nombreUnico('B Renombrada');
        $r = $this->como($this->adminB, $this->empresaB->id)->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['nombre' => $nuevo]);
        $r->assertOk()->assertJsonPath('ok', true)->assertJsonPath('data.nombre', $nuevo);

        $fila = DB::table('marketing_empresas')->where('id', $this->empresaB->id)->first();
        $this->assertSame($nuevo, $fila->nombre);
        $this->assertSame($this->empresaB->slug, $fila->slug, 'lo no enviado no cambia');
        $this->assertSame($this->adminB->id, (int) $fila->updated_by);
    }

    public function test_actualizar_con_su_propio_slug_y_nombre_no_choca_consigo(): void
    {
        $this->como($this->adminB, $this->empresaB->id)
            ->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['nombre' => $this->empresaB->nombre, 'slug' => $this->empresaB->slug])
            ->assertOk();
    }

    public function test_actualizar_con_nombre_o_slug_de_otra_empresa_da_422(): void
    {
        $this->como($this->adminB, $this->empresaB->id)
            ->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['slug' => 'meganet'])
            ->assertStatus(422)->assertJsonValidationErrors(['slug']);
        $this->como($this->adminB, $this->empresaB->id)
            ->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['nombre' => 'meganet'])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre']);
    }

    public function test_desactivar_la_principal_da_422_empresa_principal(): void
    {
        $r = $this->como($this->adminA, 1)->putJson('/api/marketing/empresas/1', ['activo' => false]);
        $r->assertStatus(422)->assertExactJson([
            'ok' => false, 'error' => 'empresa_principal', 'mensaje' => 'Meganet es la empresa principal y no se puede desactivar.',
        ]);
        $this->assertSame(1, (int) DB::table('marketing_empresas')->where('id', 1)->value('activo'));

        foreach ([0, '0', 'false'] as $falso) {
            $r = $this->como($this->adminA, 1)->putJson('/api/marketing/empresas/1', ['activo' => $falso]);
            $this->assertContains($r->status(), [422], "activo={$falso}");
            $this->assertSame(1, (int) DB::table('marketing_empresas')->where('id', 1)->value('activo'));
        }
    }

    public function test_desactivar_otra_empresa_si_se_puede_y_sus_miembros_quedan_sin_empresa(): void
    {
        $this->como($this->adminB, $this->empresaB->id)->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false);

        $this->como($this->adminB)->getJson('/api/marketing/contexto')->assertStatus(403)->assertJsonPath('error', 'sin_empresa');
    }

    public function test_actualizar_empresa_ajena_inexistente_o_no_numerica_da_404(): void
    {
        $antes = $this->fila('marketing_empresas', 1);

        $this->como($this->adminB, $this->empresaB->id)->putJson('/api/marketing/empresas/1', ['nombre' => 'Hackeada'])->assertStatus(404);
        $this->como($this->adminB, $this->empresaB->id)->putJson('/api/marketing/empresas/1', ['activo' => false])->assertStatus(404);
        $this->como($this->adminB, $this->empresaB->id)->putJson('/api/marketing/empresas/999999999', ['nombre' => 'X'])->assertStatus(404);
        $this->como($this->adminB, $this->empresaB->id)->putJson('/api/marketing/empresas/abc', ['nombre' => 'X'])->assertStatus(404);

        $this->assertEquals($antes, $this->fila('marketing_empresas', 1));
    }

    public function test_actualizar_empresa_donde_solo_soy_marketing_da_404(): void
    {
        // adminA es admin de A (activa) pero solo marketing en B → PUT a B = 404, no 403.
        $this->asignar($this->adminA, $this->empresaB->id, 'marketing');
        $antes = $this->fila('marketing_empresas', $this->empresaB->id);

        $this->como($this->adminA, 1)->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['nombre' => 'Hack'])->assertStatus(404);
        $this->assertEquals($antes, $this->fila('marketing_empresas', $this->empresaB->id));
    }

    public function test_actualizar_ignora_id_y_created_by_del_request(): void
    {
        $this->como($this->adminB, $this->empresaB->id)
            ->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['id' => 1, 'created_by' => 1, 'nombre' => $this->nombreUnico()])
            ->assertOk()->assertJsonPath('data.id', $this->empresaB->id);
        $this->assertSame('Meganet', DB::table('marketing_empresas')->where('id', 1)->value('nombre'));
    }

    public function test_actualizar_con_color_invalido_da_422(): void
    {
        $this->como($this->adminB, $this->empresaB->id)
            ->putJson("/api/marketing/empresas/{$this->empresaB->id}", ['color_primario' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors(['color_primario']);
    }
}
