<?php

namespace Tests\Feature\VoIP;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\PerfilExtension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;
use App\Modules\Addons\VoIP\Seeders\ExtensionesArranqueSeeder;
use App\Modules\Addons\VoIP\Seeders\PlanNumeracionSeeder;
use App\Modules\Addons\VoIP\Services\ResolverPerfilEfectivo;
use App\Modules\Addons\VoIP\Services\ValidadorRangos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Formaliza lo que se verificó a mano contra la base de dev (#9990718 §7 y §8).
 *
 * `DatabaseTransactions` y no `RefreshDatabase`: la suite ya corre migrate:fresh
 * en su arranque, y cada prueba solo necesita que sus escrituras no persistan.
 */
class PlanNumeracionTest extends TestCase
{
    use DatabaseTransactions;

    private ValidadorRangos $validador;
    private ResolverPerfilEfectivo $resolver;

    public function setUp(): void
    {
        parent::setUp();
        $this->validador = new ValidadorRangos;
        $this->resolver  = new ResolverPerfilEfectivo;
        (new PlanNumeracionSeeder)->run();
    }

    /** @test */
    public function el_seeder_crea_los_nueve_rangos_y_los_ocho_perfiles(): void
    {
        $this->assertSame(9, RangoNumeracion::count());
        $this->assertSame(8, PerfilExtension::count());
    }

    /** @test */
    public function el_seeder_es_idempotente(): void
    {
        (new PlanNumeracionSeeder)->run();
        (new PlanNumeracionSeeder)->run();

        $this->assertSame(9, RangoNumeracion::count(), 'duplicó rangos');
        $this->assertSame(8, PerfilExtension::count(), 'duplicó perfiles');
    }

    /** @test */
    public function internacional_y_premium_estan_desactivados_en_todos_los_perfiles(): void
    {
        // Son los destinos donde cobra el fraude telefónico: habilitarlos tiene que
        // ser deliberado, nunca el resultado de que nadie configuró nada.
        foreach (PerfilExtension::all() as $p) {
            $this->assertFalse((bool) $p->permite_internacional, "perfil {$p->codigo} trae internacional activo");
            $this->assertFalse((bool) $p->permite_premium, "perfil {$p->codigo} trae premium activo");
        }
    }

    /** @test */
    public function el_rango_de_sistema_rechaza_altas_de_usuario(): void
    {
        foreach (['1900', '1950', '1999'] as $n) {
            $motivo = $this->validador->motivoRechazoAlta($n);
            $this->assertNotNull($motivo, "el número {$n} debía rechazarse");
            // El mensaje explica QUÉ hay ahí, no dice "número inválido".
            $this->assertStringContainsString('reservado para el sistema', $motivo);
        }
    }

    /** @test */
    public function los_rangos_normales_permiten_altas(): void
    {
        foreach (['1001', '1099', '1201', '1305'] as $n) {
            $this->assertNull($this->validador->motivoRechazoAlta($n), "el número {$n} no debía rechazarse");
        }
    }

    /** @test */
    public function rechaza_traslapes_parciales_y_totales(): void
    {
        $this->assertNotEmpty($this->validador->validar('1250', '1270'), 'traslape parcial no detectado');
        $this->assertNotEmpty($this->validador->validar('1000', '1099'), 'traslape total no detectado');
        $this->assertNotEmpty($this->validador->validar('1050', '1150'), 'traslape a caballo no detectado');
    }

    /** @test */
    public function acepta_un_rango_adyacente_valido(): void
    {
        $this->assertEmpty($this->validador->validar('2000', '2099'));
    }

    /** @test */
    public function rechaza_longitudes_distintas_y_desde_mayor_que_hasta(): void
    {
        $this->assertNotEmpty($this->validador->validar('100', '1099'));
        $this->assertNotEmpty($this->validador->validar('1500', '1400'));
    }

    /** @test */
    public function dos_rangos_de_distinta_longitud_no_se_traslapan(): void
    {
        // '0100' < '99' es verdadero como cadena y falso como número: comparar
        // casteando a int abriría un traslape silencioso.
        $r = RangoNumeracion::porCodigo('oficinas')->first();

        $this->assertFalse($r->seTraslapaCon('100', '199'), 'longitudes distintas no deben traslaparse');
    }

    /** @test */
    public function una_extension_hereda_el_perfil_de_su_rango(): void
    {
        $rango = RangoNumeracion::porCodigo('tecnicos')->first();
        $e     = $this->extension('1250', $rango->id);

        $r = $this->resolver->resolver($e);

        $this->assertSame('rango', $r['origen']['permite_internacional']);
        $this->assertFalse($r['valores']['permite_internacional']);
    }

    /** @test */
    public function la_sobrescritura_de_la_extension_es_parcial(): void
    {
        // Solo pisa lo que declara: si pisara el perfil entero, cambiar un permiso
        // obligaría a redefinir códecs y límites, y al cambiar el perfil del rango
        // la extensión sobrescrita se quedaría atrás sin aviso.
        $rango = RangoNumeracion::porCodigo('tecnicos')->first();
        $sobre = PerfilExtension::create([
            'codigo' => 'excepcion_test', 'nombre' => 'Excepción',
            'permite_internacional' => true, 'permite_premium' => false,
            'permite_nacional_fijo' => true, 'permite_nacional_movil' => true,
            'permite_entrantes_exterior' => false, 'graba_llamadas' => false,
            'codecs' => null,   // no opina: debe seguir viniendo del rango
        ]);

        $e = $this->extension('1251', $rango->id, $sobre->id);
        $r = $this->resolver->resolver($e);

        $this->assertTrue($r['valores']['permite_internacional']);
        $this->assertSame('extension', $r['origen']['permite_internacional']);
        $this->assertSame('rango', $r['origen']['codecs'], 'los códecs debían seguir viniendo del rango');
    }

    /** @test */
    public function sin_rango_caen_los_defaults_del_sistema(): void
    {
        $r = $this->resolver->resolver($this->extension('9998', null));

        $this->assertSame('sistema', $r['origen']['permite_internacional']);
        $this->assertFalse($r['valores']['permite_internacional']);
    }

    /** @test */
    public function preguntar_por_un_destino_inexistente_devuelve_false(): void
    {
        // Un typo no puede abrir la puerta.
        $this->assertFalse($this->resolver->permite($this->extension('9997', null), 'inventado'));
    }

    /** @test */
    public function la_siembra_de_extensiones_es_idempotente_y_no_pisa_las_existentes(): void
    {
        $previa = Extension::create([
            'numero' => '1201', 'nombre' => 'NO ME TOQUES', 'secret' => 'x',
            'tipo_dispositivo' => 'softphone', 'contexto' => 'from-internal',
            'codecs' => 'alaw', 'transporte' => 'udp', 'activo' => true,
        ]);

        (new ExtensionesArranqueSeeder)->run();
        $tras = Extension::count();
        (new ExtensionesArranqueSeeder)->run();

        $this->assertSame($tras, Extension::count(), 'la segunda corrida duplicó');
        $this->assertSame('NO ME TOQUES', $previa->fresh()->nombre, 'pisó una extensión existente');
    }

    /** @test */
    public function las_extensiones_sembradas_tienen_secreto_distinto_y_sin_su_numero(): void
    {
        (new ExtensionesArranqueSeeder)->run();
        $s = Extension::where('sembrada_por_sistema', true)->get();

        $this->assertGreaterThan(0, $s->count());
        $this->assertSame($s->count(), $s->pluck('secret')->unique()->count(), 'hay secretos repetidos');

        foreach ($s as $e) {
            // `extensión + 1234` es la puerta por la que entra el fraude.
            $this->assertStringNotContainsString($e->numero, $e->secret_plain);
            $this->assertGreaterThanOrEqual(32, strlen($e->secret_plain));
        }
    }

    /** @test */
    public function la_siembra_no_crea_extensiones_en_el_rango_protegido(): void
    {
        (new ExtensionesArranqueSeeder)->run();

        $this->assertSame(0, Extension::whereBetween('numero', ['1900', '1999'])->count());
    }

    private function extension(string $numero, ?int $rangoId, ?int $perfilId = null): Extension
    {
        return Extension::create([
            'numero' => $numero, 'nombre' => 'Prueba ' . $numero, 'secret' => 'secreto-de-prueba',
            'tipo_dispositivo' => 'softphone', 'contexto' => 'from-internal',
            'codecs' => 'alaw,ulaw', 'transporte' => 'udp', 'activo' => true,
            'voip_rango_numeracion_id' => $rangoId, 'voip_perfil_extension_id' => $perfilId,
        ]);
    }
}
