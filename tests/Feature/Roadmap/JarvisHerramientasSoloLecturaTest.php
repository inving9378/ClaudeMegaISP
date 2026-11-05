<?php

namespace Tests\Feature\Roadmap;

use App\Models\User;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\Roadmap\Models\JarvisConversacion;
use App\Modules\Addons\Roadmap\Services\JarvisChatService;
use App\Modules\Addons\Roadmap\Services\JarvisHerramientas\RegistroJarvis;
use App\Modules\Addons\Roadmap\Services\JarvisPropuestas\PropuestaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesApplication;

/**
 * #1118 — Las herramientas de Jarvis son SOLO LECTURA (+ «proponer_items», que no crea nada).
 * Una herramienta nueva hace fallar esta prueba hasta que alguien la revise y la agregue a la lista blanca.
 */
class JarvisHerramientasSoloLecturaTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private const LECTURA = ['estado_circuito', 'bandeja_irving', 'merges_pendientes', 'cambios_para_probar', 'item',
        'items_por_modulo', 'actividad_reciente', 'gasto_ia', 'alertas'];

    private function usuario(bool $conPermiso): User
    {
        $u = User::create(['name' => 'Prueba 1118', 'login_user' => 'prueba_1118_' . uniqid()])->fresh();
        if ($conPermiso) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => PropuestaService::PERMISO, 'guard_name' => 'web']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    /** Conversa con la IA simulada y devuelve los nombres de herramientas que viajaron en la petición. */
    private function herramientasEnviadas(User $u, string $mensaje): array
    {
        IAProveedor::query()->update(['activo' => false]);
        IAProveedor::create(['nombre' => 'Claude de prueba', 'driver' => 'claude', 'api_key' => 'clave-de-prueba',
            'endpoint_url' => 'https://ia.example.test/v1/messages', 'modelo_default' => 'claude-haiku-5-5', 'activo' => true]);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['ia.example.test/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Listo.']],
            'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 5, 'output_tokens' => 5]])]);
        $this->actingAs($u);
        $conv = JarvisConversacion::create(['sugerencia_clave' => 'c1118-' . uniqid(), 'categoria' => 'conversacion',
            'texto_sugerencia' => 'x', 'estado' => 'abierta', 'created_by' => $u->id]);
        app(JarvisChatService::class)->enviarMensaje($conv, $mensaje);

        $nombres = [];
        Http::assertSent(function (Request $r) use (&$nombres) {
            foreach ((array) ($r->data()['tools'] ?? []) as $t) {
                $nombres[] = $t['name'] ?? null;
            }

            return true;
        });

        return array_values(array_unique(array_filter($nombres)));
    }

    public function test_el_registro_es_exactamente_la_lista_blanca_de_lectura(): void
    {
        $nombres = app(RegistroJarvis::class)->nombres();
        $this->assertEqualsCanonicalizing(self::LECTURA, $nombres);
    }

    public function test_a_la_ia_viajan_solo_lectura_y_a_lo_mucho_proponer_items(): void
    {
        $con = $this->herramientasEnviadas($this->usuario(true), 'Crea un item de ejemplo');
        $this->assertSame([], array_values(array_diff($con, array_merge(self::LECTURA, ['proponer_items']))));
        $this->assertContains('proponer_items', $con);

        $sinPedir = $this->herramientasEnviadas($this->usuario(true), '¿Cómo va la cola?');
        $this->assertNotContains('proponer_items', $sinPedir);
        $this->assertEqualsCanonicalizing(self::LECTURA, $sinPedir);

        $sinPermiso = $this->herramientasEnviadas($this->usuario(false), 'Crea un item de ejemplo');
        $this->assertNotContains('proponer_items', $sinPermiso);
        $this->assertEqualsCanonicalizing(self::LECTURA, $sinPermiso);
    }

    public function test_ejecutar_las_herramientas_de_lectura_no_escribe_nada(): void
    {
        $tablas = ['roadmap_items', 'settings', 'jarvis_propuestas'];
        $cuenta = fn () => array_map(fn ($t) => DB::table($t)->count(), $tablas);
        $antes = $cuenta();

        $registro = app(RegistroJarvis::class);
        $ejemplos = ['item' => ['id' => 1], 'items_por_modulo' => ['modulo' => 'Roadmap', 'estado' => 'completado'],
            'actividad_reciente' => ['desde' => '2026-10-01'], 'gasto_ia' => ['rango' => 'mes']];
        foreach (self::LECTURA as $nombre) {
            $registro->ejecutar($nombre, $ejemplos[$nombre] ?? []);
        }

        $this->assertSame($antes, $cuenta());
    }
}
