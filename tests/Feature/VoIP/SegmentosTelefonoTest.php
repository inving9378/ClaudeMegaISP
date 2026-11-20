<?php

namespace Tests\Feature\VoIP;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Seeders\PlanNumeracionSeeder;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use App\Modules\Addons\VoIP\Services\SegmentosTelefono;
use App\Modules\Addons\VoIP\Services\SeparadorTelefonosWeb;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Segmentos (17-nov-2026): 1000–1999 aparatos, 2000–2999 teléfonos web, uno por persona.
 * Las gemelas viejas `webNNNN` pasan a su número propio. Desde el 20-nov el número web es
 * uno disponible que no se parece a ningún aparato (con la 1003 no hay 2003). Asterisk
 * simulado: se anota qué se quitó y qué se publicó.
 */
class SegmentosTelefonoTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private array $asterisk = [];

    protected function setUp(): void
    {
        parent::setUp();
        (new PlanNumeracionSeeder())->run();

        $prov = Mockery::mock(AsteriskProvisioningService::class);
        $prov->shouldReceive('provisionarExtension')->andReturnUsing(function (Extension $e) {
            $this->asterisk[] = "+{$e->numero}";
            $e->update(['provisionado_at' => now()]);
        });
        $prov->shouldReceive('desprovisionarExtension')->andReturnUsing(function (Extension $e) {
            $this->asterisk[] = "-{$e->numero}";
        });
        $this->app->instance(AsteriskProvisioningService::class, $prov);
    }

    private function ext(string $numero, ?int $userId, array $mas = []): Extension
    {
        return Extension::create($mas + [
            'numero' => $numero, 'nombre' => "Prueba {$numero}", 'secret' => 'x-' . $numero . '-secreto',
            'user_id' => $userId, 'contexto' => 'from-internal', 'activo' => true,
            'es_webrtc' => str_starts_with($numero, 'web'), 'callerid' => preg_replace('/^web/', '', $numero),
            'transporte' => str_starts_with($numero, 'web') ? 'transport-wss' : 'transport-udp',
        ]);
    }

    /** @test */
    public function los_numeros_de_cada_segmento(): void
    {
        $this->assertTrue(SegmentosTelefono::esNumeroWeb('2000'));
        $this->assertTrue(SegmentosTelefono::esNumeroWeb('2999'));
        $this->assertFalse(SegmentosTelefono::esNumeroWeb('1999'));
        $this->assertFalse(SegmentosTelefono::esNumeroWeb('3000'));
        $this->assertFalse(SegmentosTelefono::esNumeroWeb('web1011'));
        $this->assertSame('1011', SegmentosTelefono::baseDeGemela('web1011'));
        $this->assertNull(SegmentosTelefono::baseDeGemela('1011'));
        $this->assertSame('2011', SegmentosTelefono::parecido('1011'));
        $this->assertSame('1011', SegmentosTelefono::parecido('2011'));
        $this->assertNull(SegmentosTelefono::parecido('101'));
        $this->assertNull(SegmentosTelefono::parecido('3011'));
        $this->assertSame('atencion_web', SegmentosTelefono::rangoDe('2105')->codigo);
        $this->assertSame('atencion', SegmentosTelefono::rangoDe('1105')->codigo);
    }

    /** @test */
    public function las_gemelas_pasan_a_su_numero_propio_y_las_sin_dueno_se_quitan(): void
    {
        $ana  = User::factory()->create();
        $beto = User::factory()->create();

        $fisico = $this->ext('1003', $ana->id);
        $webAna = $this->ext('web1003', $ana->id, ['callerid' => '"Ana" <1003>']);
        $mostrador = $this->ext('1002', null);
        $sinDueno  = $this->ext('web1002', null);
        // Beto tenía dos líneas web (dos extensiones físicas a su nombre): se queda la activa más antigua.
        $betoA = $this->ext('web1011', $beto->id);
        $betoB = $this->ext('web1001', $beto->id);
        $grupo = GrupoTimbrado::create(['nombre' => 'Prueba segmentos', 'estrategia' => 'ringall', 'activo' => true]);
        DB::table('voip_grupo_extension')->insert(['grupo_id' => $grupo->id, 'extension_id' => $betoB->id, 'orden' => 1]);

        $sep = $this->app->make(SeparadorTelefonosWeb::class);

        $plan = $sep->revisar(false);
        $this->assertSame([], $this->asterisk, 'revisar sin aplicar no toca Asterisk');
        $this->assertTrue(Extension::where('numero', 'web1003')->exists(), 'revisar sin aplicar no cambia nada');
        $this->assertCount(4, $plan['tareas']);

        $r = $sep->revisar(true);
        $this->assertSame([], $r['fallas']);

        $webAna->refresh();
        $this->assertTrue(SegmentosTelefono::esNumeroWeb($webAna->numero), $webAna->numero);
        $this->assertNotSame('2003', $webAna->numero, 'no se parece a su aparato 1003');
        $this->assertNoSeParece($webAna->numero);
        $this->assertSame('web1003', $webAna->numero_anterior);
        $this->assertSame("\"Ana\" <{$webAna->numero}>", $webAna->callerid, 'devolver la llamada debe timbrar el navegador');
        $this->assertSame('oficinas_web', $webAna->rango?->codigo, 'queda en el rango web de su área (1003 → 2000–2099)');
        $this->assertSame('1003', $fisico->fresh()->numero, 'el aparato no se toca');
        $this->assertSame('1002', $mostrador->fresh()->numero);

        $this->assertNull($sinDueno->fresh(), 'sin dueño se quita');
        $this->assertTrue(SegmentosTelefono::esNumeroWeb($betoA->fresh()->numero));
        $this->assertNotSame($webAna->numero, $betoA->fresh()->numero);
        $this->assertNoSeParece($betoA->fresh()->numero);
        $this->assertNull($betoB->fresh(), 'uno por persona');
        $this->assertTrue(DB::table('voip_grupo_extension')->where('grupo_id', $grupo->id)->where('extension_id', $betoA->id)->exists(),
            'el grupo pasa a la línea que se queda');

        $this->assertContains('-web1003', $this->asterisk);
        $this->assertContains("+{$webAna->numero}", $this->asterisk);
        $this->assertContains('-web1002', $this->asterisk);

        $this->assertSame([], $sep->revisar(true)['tareas'], 'una segunda vuelta no hace nada');
    }

    /** @test */
    public function toma_el_primero_disponible_de_su_area_que_no_se_parezca_a_un_aparato(): void
    {
        $ana = User::factory()->create();
        $carla = User::factory()->create();
        $this->ext('1101', null);   // aparato: la 2101 queda fuera
        $this->ext('2102', $carla->id, ['es_webrtc' => true, 'callerid' => '2102']);
        $rango = SegmentosTelefono::rangoDe('1105');
        $vieja = $this->ext('web1105', $ana->id, ['voip_rango_numeracion_id' => $rango->id]);

        $this->app->make(SeparadorTelefonosWeb::class)->revisar(true);

        $this->assertSame('2103', $vieja->fresh()->numero,
            '2100 se reserva, 2101 se parece a la 1101 y 2102 está ocupada');
    }

    /** @test */
    public function un_telefono_web_que_se_parece_a_un_aparato_pasa_a_uno_disponible(): void
    {
        $ana  = User::factory()->create();
        $beto = User::factory()->create();
        $this->ext('1003', $ana->id);
        // Como la dejó la V1.96: la gemela web1003 pasó a 2003.
        $web = $this->ext('2003', $ana->id, ['es_webrtc' => true, 'callerid' => '"Ana" <2003>',
            'numero_anterior' => 'web1003', 'transporte' => 'transport-wss']);
        $otro = $this->ext('2050', $beto->id, ['es_webrtc' => true, 'callerid' => '2050', 'transporte' => 'transport-wss']);

        $sep = $this->app->make(SeparadorTelefonosWeb::class);
        $r = $sep->revisar(true);

        $this->assertSame([], $r['fallas']);
        $web->refresh();
        $this->assertNotSame('2003', $web->numero);
        $this->assertNoSeParece($web->numero);
        $this->assertSame('web1003', $web->numero_anterior, 'se conserva la gemela vieja: es la de casi todo el historial');
        $this->assertSame("\"Ana\" <{$web->numero}>", $web->callerid);
        $this->assertSame('2050', $otro->fresh()->numero, 'el que no se parece a ningún aparato no se toca');
        $this->assertContains('-2003', $this->asterisk);
        $this->assertSame([], $sep->revisar(true)['tareas'], 'una segunda vuelta no hace nada');
    }

    /** @test */
    public function el_formulario_no_deja_numeros_parecidos_y_el_web_sin_numero_toma_uno_disponible(): void
    {
        Permission::findOrCreate('voip.extensiones.create', 'web');
        $admin = User::factory()->create();
        $admin->givePermissionTo('voip.extensiones.create');
        $ana  = User::factory()->create();
        $beto = User::factory()->create();
        $this->ext('1003', null);
        $this->ext('2050', $beto->id, ['es_webrtc' => true, 'callerid' => '2050', 'transporte' => 'transport-wss']);

        $web = fn (array $mas) => $this->actingAs($admin)->postJson('/voip/extensiones',
            $mas + ['nombre' => 'Prueba', 'es_webrtc' => true, 'user_id' => $ana->id]);

        $web(['numero' => '2003'])->assertStatus(422)->assertJsonValidationErrors('numero');
        $this->actingAs($admin)->postJson('/voip/extensiones', ['numero' => '1050', 'nombre' => 'Aparato', 'secret' => 'x-secreto-1050'])
            ->assertStatus(422)->assertJsonValidationErrors('numero');

        $nuevo = $web([])->assertStatus(201)->json('numero');
        $this->assertTrue(SegmentosTelefono::esNumeroWeb($nuevo), (string) $nuevo);
        $this->assertNoSeParece($nuevo);
        $this->assertSame($ana->id, Extension::where('numero', $nuevo)->value('user_id'));
    }

    private function assertNoSeParece(string $numero): void
    {
        $this->assertFalse(Extension::where('numero', SegmentosTelefono::parecido($numero))->exists(),
            "{$numero} se parece a un aparato que existe");
    }
}
