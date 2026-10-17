<?php

namespace Tests\Feature\Portal;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Modules\Addons\PortalCliente\Models\AparienciaImagen;
use App\Modules\Addons\PortalCliente\Models\AparienciaVersion;
use App\Modules\Addons\PortalCliente\Services\AparienciaService;
use App\Modules\Addons\PortalCliente\Support\AparienciaCatalogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Configuración → Portal Cliente: permisos, imágenes, borrador/publicación/historial, caché,
 * vista previa y que las pantallas públicas sigan funcionando. DatabaseTransactions (rollback) y
 * disco falso: no deja datos ni archivos.
 */
class AparienciaPortalTest extends PortalTestCase
{
    private const BASE = '/configuracion/portal-cliente';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Storage::fake('local');
        config(['portalcliente.apariencia.disco' => 'local']);
        // Cada prueba parte sin versiones ni imágenes, sin importar lo que haya en la BD de pruebas
        // (el borrado vive dentro de la transacción de la prueba y se deshace al terminar).
        AparienciaVersion::query()->delete();
        AparienciaImagen::query()->delete();
        app(AparienciaService::class)->olvidarCache();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function usuario(array $permisos = []): User
    {
        $id = DB::table('users')->insertGetId([
            'name'       => 'Prueba Apariencia',
            'login_user' => 'prueba_apariencia_' . Str::random(8),
            'email'      => Str::random(8) . '@apariencia.test',
            'password'   => bcrypt(Str::random(32)),
            'estado'     => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $usuario = User::findOrFail($id);
        foreach ($permisos as $p) {
            $usuario->givePermissionTo("portal-cliente.apariencia.{$p}");
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario->fresh();
    }

    private function admin(): User
    {
        return $this->usuario(['ver', 'editar', 'publicar']);
    }

    private function config(callable $cambio = null): array
    {
        $c = AparienciaCatalogo::defaults();
        if ($cambio) {
            $cambio($c);
        }

        return $c;
    }

    private function revision(): int
    {
        return (int) (AparienciaVersion::where('estado', 'borrador')->value('revision') ?? 0);
    }

    private function guardar(User $u, array $config)
    {
        return $this->actingAs($u)->postJson(self::BASE . '/borrador', ['config' => $config, 'revision' => $this->revision()]);
    }

    private function publicar(User $u, ?string $nota = null)
    {
        return $this->actingAs($u)->postJson(self::BASE . '/publicar', ['revision' => $this->revision(), 'nota' => $nota]);
    }

    private function imagen(string $formato, int $ancho, int $alto, string $nombre, bool $alfa = false): UploadedFile
    {
        $gd = imagecreatetruecolor($ancho, $alto);
        if ($alfa) {
            imagesavealpha($gd, true);
            imagefill($gd, 0, 0, imagecolorallocatealpha($gd, 0, 0, 0, 127));
        }
        imagefilledrectangle($gd, 10, 10, (int) ($ancho / 2), (int) ($alto / 2), imagecolorallocate($gd, 0, 140, 255));
        $ruta = tempnam(sys_get_temp_dir(), 'apariencia');
        match ($formato) {
            'jpg'  => imagejpeg($gd, $ruta, 90),
            'png'  => imagepng($gd, $ruta),
            'webp' => imagewebp($gd, $ruta, 90),
            'gif'  => imagegif($gd, $ruta),
        };
        imagedestroy($gd);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    private function subir(User $u, string $tipo, UploadedFile $archivo)
    {
        return $this->actingAs($u)->post(self::BASE . '/imagenes', ['tipo' => $tipo, 'archivo' => $archivo], ['Accept' => 'application/json']);
    }

    // ── Acceso por roles ─────────────────────────────────────────────────

    public function test_invitado_es_enviado_al_login_administrativo(): void
    {
        $this->get(self::BASE)->assertRedirect();
        $this->getJson(self::BASE . '/estado')->assertStatus(401);
    }

    public function test_usuario_sin_permiso_no_entra_ni_escribe(): void
    {
        $u = $this->usuario();
        $this->actingAs($u)->get(self::BASE)->assertForbidden();
        $this->actingAs($u)->getJson(self::BASE . '/estado')->assertForbidden();
        $this->guardar($u, $this->config())->assertForbidden();
        $this->actingAs($u)->get(self::BASE . '/vista-previa/login')->assertForbidden();
    }

    public function test_lector_ve_pero_no_edita_ni_publica(): void
    {
        $u = $this->usuario(['ver']);
        $this->actingAs($u)->get(self::BASE)->assertOk()->assertSee('portal-cliente-apariencia', false);
        $this->actingAs($u)->getJson(self::BASE . '/estado')->assertOk()
            ->assertJsonPath('puede.editar', false)->assertJsonPath('puede.publicar', false);
        $this->guardar($u, $this->config())->assertForbidden();
        $this->subir($u, 'escritorio', $this->imagen('jpg', 1600, 900, 'f.jpg'))->assertForbidden();
        $this->publicar($u)->assertForbidden();
    }

    public function test_editor_guarda_borrador_pero_no_publica(): void
    {
        $u = $this->usuario(['ver', 'editar']);
        $this->guardar($u, $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'Hola Meganet'))->assertOk();
        $this->publicar($u)->assertForbidden();
        $this->assertSame(0, AparienciaVersion::where('estado', 'publicada')->count());
    }

    public function test_seccion_en_configuracion_solo_para_autorizados(): void
    {
        $buscar = fn ($resp) => collect($resp->json('sections_flat'))->firstWhere('key', 'portal_cliente_apariencia');

        $this->assertNull($buscar($this->actingAs($this->usuario())->getJson('/api/modules/config-sections')->assertOk()));
        $this->assertNotNull($buscar($this->actingAs($this->usuario(['ver']))->getJson('/api/modules/config-sections')->assertOk()));
    }

    // ── Imágenes ─────────────────────────────────────────────────────────

    public function test_imagenes_validas_se_recodifican_a_webp(): void
    {
        $u = $this->admin();
        foreach ([
            ['escritorio', $this->imagen('jpg', 3000, 1600, 'fachada.jpg')],
            ['movil', $this->imagen('png', 900, 1600, 'movil.png')],
            ['respaldo', $this->imagen('webp', 1600, 900, 'respaldo.webp')],
            ['logo', $this->imagen('png', 600, 200, 'logo.png', true)],
        ] as [$tipo, $archivo]) {
            $r = $this->subir($u, $tipo, $archivo)->assertOk()->json('imagen');
            $registro = AparienciaImagen::where('uuid', $r['uuid'])->firstOrFail();
            $this->assertSame('image/webp', $registro->mime);
            $this->assertTrue(Storage::disk('local')->exists($registro->ruta));
            $this->assertStringEndsWith($r['uuid'] . '.webp', $registro->ruta, 'nombre generado por el servidor');
            $info = getimagesizefromstring(Storage::disk('local')->get($registro->ruta));
            $this->assertSame('image/webp', $info['mime']);
        }
        // La fotografía de 3000 px se redujo al máximo de escritorio sin deformarse.
        $esc = AparienciaImagen::where('tipo', 'escritorio')->latest('id')->first();
        $this->assertSame(2560, $esc->ancho);
        $this->assertSame(1365, $esc->alto);
    }

    public function test_rechaza_archivos_peligrosos_o_invalidos(): void
    {
        $u = $this->admin();
        $tmp = function (string $contenido, string $nombre) {
            $ruta = tempnam(sys_get_temp_dir(), 'malo');
            file_put_contents($ruta, $contenido);

            return new UploadedFile($ruta, $nombre, null, null, true);
        };

        $casos = [
            'svg'                => $tmp('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.svg'),
            'php disfrazado'     => $tmp("<?php echo 'x'; ?>", 'fachada.jpg'),
            'png con nombre jpg' => $this->imagen('png', 1600, 900, 'fachada.jpg'),
            'gif'                => $this->imagen('gif', 1600, 900, 'fachada.gif'),
            'gif con nombre jpg' => $this->imagen('gif', 1600, 900, 'fachada2.jpg'),
            'muy pequena'        => $this->imagen('jpg', 800, 400, 'chica.jpg'),
            'html'               => $tmp('<html><body>hola</body></html>', 'pagina.webp'),
        ];
        foreach ($casos as $caso => $archivo) {
            $this->subir($u, 'escritorio', $archivo)->assertStatus(422, "Debió rechazar: {$caso}");
        }
        $this->subir($u, 'otro', $this->imagen('jpg', 1600, 900, 'x.jpg'))->assertStatus(422);
        $this->assertSame(0, AparienciaImagen::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ── Mensajes, tarjetas y validación ──────────────────────────────────

    public function test_textos_se_guardan_como_texto_y_se_escapan(): void
    {
        $u = $this->admin();
        $this->guardar($u, $this->config(function (&$c) {
            $c['mensajes']['titulo'] = '<script>alert("x")</script> Gracias Meganet';
            $c['mensajes']['descripcion'] = "<img src=x onerror=alert(1)>\u{202E}texto";
            $c['tarjetas'][0]['titulo'] = '<b>Internet</b>';
        }))->assertOk();
        $this->publicar($u)->assertOk();

        $html = $this->get('/portal/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Internet</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("\u{202E}", $html, 'marca bidi eliminada');
    }

    public function test_validacion_rechaza_valores_fuera_de_catalogo(): void
    {
        $u = $this->admin();
        $this->guardar($u, $this->config(fn (&$c) => $c['identidad']['colores']['primario'] = 'red;}body{display:none'))->assertStatus(422);
        $this->guardar($u, $this->config(fn (&$c) => $c['tarjetas'][0]['icono'] = '<svg onload=alert(1)>'))->assertStatus(422);
        $this->guardar($u, $this->config(fn (&$c) => $c['fondo']['posicion_x'] = 500))->assertStatus(422);
        $this->guardar($u, $this->config(fn (&$c) => $c['fondo']['escritorio'] = (string) Str::uuid()))->assertStatus(422);
        $this->guardar($u, $this->config(fn (&$c) => $c['tarjetas'][1]['clave'] = 'internet'))->assertStatus(422);
        $this->assertSame(0, AparienciaVersion::count());
    }

    public function test_tarjetas_respetan_orden_y_visibilidad(): void
    {
        $u = $this->admin();
        $this->guardar($u, $this->config(function (&$c) {
            $c['tarjetas'] = array_reverse($c['tarjetas']);           // soporte primero
            $c['tarjetas'][1]['activa'] = false;                     // oculta "Administra tus servicios"
            $c['tarjetas'][0]['titulo'] = 'Ayuda inmediata';
            $c['tarjetas'][0]['icono'] = 'chat';
            $c['tarjetas'][0]['color'] = 'rojo';
        }))->assertOk();
        $this->publicar($u)->assertOk();

        $html = $this->get('/portal/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('Administra tus servicios', $html);
        $this->assertTrue(strpos($html, 'Ayuda inmediata') < strpos($html, 'Descarga tus facturas'));
        $this->assertStringContainsString('#E11D48', $html, 'color rojo del catálogo');
    }

    // ── Borrador, publicación, historial y caché ─────────────────────────

    public function test_borrador_no_es_visible_para_clientes_y_vista_previa_si(): void
    {
        $u = $this->admin();
        $this->guardar($u, $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'Texto del borrador Meganet'))->assertOk();

        $this->get('/portal/login')->assertOk()
            ->assertDontSee('Texto del borrador')
            ->assertSee('Gracias por ser parte de');

        $this->actingAs($u)->get(self::BASE . '/vista-previa/login?fuente=borrador')->assertOk()
            ->assertSee('Texto del borrador')
            ->assertSee('Vista previa')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->actingAs($u)->get(self::BASE . '/vista-previa/registro?fuente=publicada')->assertOk()->assertSee('Crear Cuenta');
    }

    public function test_conflicto_entre_administradores_no_sobrescribe(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        $this->actingAs($a)->postJson(self::BASE . '/borrador', ['config' => $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'De A'), 'revision' => 0])->assertOk();
        // B cargó antes (revisión 0) e intenta guardar encima.
        $this->actingAs($b)->postJson(self::BASE . '/borrador', ['config' => $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'De B'), 'revision' => 0])
            ->assertStatus(409)->assertJsonPath('conflicto.por', 'Prueba Apariencia');
        $this->assertSame('De A', AparienciaVersion::where('estado', 'borrador')->first()->config['mensajes']['titulo']);
        // Publicar con una revisión vieja también se rechaza.
        $this->actingAs($a)->postJson(self::BASE . '/publicar', ['revision' => 99])->assertStatus(409);
    }

    public function test_publicar_crea_version_registra_cambios_e_invalida_cache(): void
    {
        $u = $this->admin();
        $this->get('/portal/login')->assertSee('Gracias por ser parte de');          // calienta la caché

        $this->guardar($u, $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'Bienvenido a Meganet'))->assertOk();
        $r = $this->publicar($u, 'Campaña de octubre')->assertOk();
        $this->assertSame(1, $r->json('publicada.numero'));
        $this->assertNull($r->json('borrador'));

        $this->get('/portal/login')->assertSee('Bienvenido a')->assertDontSee('Gracias por ser parte de');

        $v1 = AparienciaVersion::where('numero', 1)->first();
        $this->assertSame($u->id, (int) $v1->published_by);
        $this->assertContains('Mensajes › Título principal', $v1->resumen_cambios);
        $this->assertSame('Campaña de octubre', $v1->nota);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'portal_cliente_apariencia', 'description' => 'Publicó la versión 1', 'causer_id' => $u->id]);

        // Publicar sin cambios no crea una versión vacía.
        $this->guardar($u, $v1->config)->assertOk();
        $this->publicar($u)->assertStatus(422);

        $this->guardar($u, $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'Segunda Meganet'))->assertOk();
        $this->publicar($u)->assertOk()->assertJsonPath('publicada.numero', 2);
        $this->assertSame('historial', $v1->fresh()->estado);
        $this->assertSame(1, AparienciaVersion::where('estado', 'publicada')->count());
        $this->actingAs($u)->getJson(self::BASE . '/versiones')->assertOk()->assertJsonCount(2, 'versiones');
    }

    public function test_descartar_borrador_conserva_lo_publicado(): void
    {
        $u = $this->admin();
        $this->guardar($u, $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'Descartable Meganet'))->assertOk();
        $this->actingAs($u)->deleteJson(self::BASE . '/borrador', ['revision' => $this->revision()])->assertOk()->assertJsonPath('borrador', null);
        $this->get('/portal/login')->assertDontSee('Descartable');
    }

    public function test_restaurar_version_conserva_sus_imagenes(): void
    {
        $u = $this->admin();
        $uuid = $this->subir($u, 'escritorio', $this->imagen('jpg', 1600, 900, 'fachada.jpg'))->assertOk()->json('imagen.uuid');

        $this->guardar($u, $this->config(function (&$c) use ($uuid) {
            $c['mensajes']['titulo'] = 'Versión con fachada Meganet';
            $c['fondo']['escritorio'] = $uuid;
        }))->assertOk();
        $this->publicar($u)->assertOk();
        $v1 = AparienciaVersion::where('numero', 1)->first();

        $this->guardar($u, $this->config(fn (&$c) => $c['mensajes']['titulo'] = 'Versión sin fachada Meganet'))->assertOk();
        $this->publicar($u)->assertOk();
        $this->get("/portal/apariencia/imagen/{$uuid}")->assertNotFound();          // ya no está publicada

        // La imagen de v1 sobrevive a la limpieza aunque pasen días: v1 es recuperable.
        $this->travel(3)->days();
        app(AparienciaService::class)->purgarHuerfanas();
        $this->assertTrue(Storage::disk('local')->exists(AparienciaImagen::where('uuid', $uuid)->value('ruta')));

        $this->actingAs($u)->postJson(self::BASE . "/versiones/{$v1->id}/restaurar", ['revision' => 0])
            ->assertOk()->assertJsonPath('borrador.restaurado_de', 1);
        $this->publicar($u)->assertOk();
        $this->assertContains('Restaurada desde la versión 1', AparienciaVersion::where('numero', 3)->first()->resumen_cambios);

        $html = $this->get('/portal/login')->assertOk()->getContent();
        $this->assertStringContainsString('Versión con fachada', $html);
        $this->assertStringContainsString("/portal/apariencia/imagen/{$uuid}", $html);
        $this->assertStringContainsString('con-fachada', $html);
        $this->get("/portal/apariencia/imagen/{$uuid}")->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_imagen_publica_solo_si_esta_publicada_y_huerfanas_se_limpian(): void
    {
        $u = $this->admin();
        $uuid = $this->subir($u, 'escritorio', $this->imagen('jpg', 1600, 900, 'fachada.jpg'))->assertOk()->json('imagen.uuid');
        $this->guardar($u, $this->config(fn (&$c) => $c['fondo']['escritorio'] = $uuid))->assertOk();

        $this->get("/portal/apariencia/imagen/{$uuid}")->assertNotFound();                    // solo en borrador
        $this->actingAs($u)->get(self::BASE . "/imagenes/{$uuid}")->assertOk();                // el admin sí la ve
        $this->get('/portal/apariencia/imagen/no-es-uuid')->assertNotFound();

        // Huérfana: subida y nunca usada.
        $huerfana = $this->subir($u, 'movil', $this->imagen('jpg', 900, 1600, 'm.jpg'))->assertOk()->json('imagen.uuid');
        $this->travel(2)->days();
        $this->assertSame(1, app(AparienciaService::class)->purgarHuerfanas());
        $this->assertNull(AparienciaImagen::where('uuid', $huerfana)->first());
        $this->assertNotNull(AparienciaImagen::where('uuid', $uuid)->first());             // la del borrador se queda
    }

    // ── Pantallas públicas ───────────────────────────────────────────────

    public function test_sin_configuracion_ni_imagen_las_pantallas_usan_el_diseno_de_fabrica(): void
    {
        foreach (['/portal/login' => 'portal.login.post', '/portal/registro' => 'portal.registro.post', '/portal/recuperar' => 'portal.recuperar.post'] as $url => $ruta) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('action="' . route($ruta) . '"', $html);
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringNotContainsString('class="con-fachada', $html);
            $this->assertStringNotContainsString('los formularios no envían datos', $html);
        }
        $this->get('/portal/login')->assertSee('Gracias por ser parte de')->assertSee('Consulta tu internet');
        $this->get('/portal/registro')->assertSee('name="numero_cliente"', false)->assertSee('name="contrasena_confirmation"', false);
    }
}
