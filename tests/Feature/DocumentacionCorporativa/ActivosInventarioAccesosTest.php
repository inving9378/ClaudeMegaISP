<?php

namespace Tests\Feature\DocumentacionCorporativa;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcActivo;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcActivoDigital;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcInventarioAcceso;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPendiente;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

/**
 * Fase 3.1 (item #750): dc_activos, dc_activos_digitales y dc_inventario_accesos.
 *
 * USA DatabaseTransactions (rollback al terminar cada test), NO migrate:fresh.
 * Ejecutar selectivo:  php artisan test --filter=ActivosInventarioAccesosTest
 */
class ActivosInventarioAccesosTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private DcEmpresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = DcEmpresa::activas()->firstOrFail();
    }

    // ── dc_activos: alta mínima viable ─────────────────────────────────────

    public function test_un_activo_fisico_se_crea_con_su_categoria(): void
    {
        $activo = DcActivo::create([
            'empresa_id' => $this->empresa->id,
            'categoria'  => 'torre',
            'nombre'     => 'Torre de prueba',
        ]);

        $this->assertSame('torre', $activo->fresh()->categoria);
        $this->assertSame('activo', $activo->fresh()->estado);
    }

    // ── dc_inventario_accesos: nunca expone el secreto ──────────────────────

    public function test_dc_inventario_accesos_no_tiene_ningun_atributo_escribible_de_secreto(): void
    {
        $prohibidas = ['password', 'secret', 'token_value', 'clabe', 'pan', 'tarjeta', 'llave_privada'];

        $fillable = (new DcInventarioAcceso())->getFillable();

        foreach ($fillable as $campo) {
            foreach ($prohibidas as $palabra) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $palabra,
                    $campo,
                    "El campo fillable '{$campo}' no debe insinuar una palabra de secreto ('{$palabra}')."
                );
            }
        }
    }

    public function test_el_accessor_credencial_siempre_es_la_constante_sin_importar_los_atributos(): void
    {
        $acceso = DcInventarioAcceso::create([
            'empresa_id'            => $this->empresa->id,
            'tipo'                  => 'usuario_sistema',
            'institucion_o_sistema' => 'Sistema de prueba',
            // Un atacante que consiguiera escribir aquí (no hay columna real
            // para esto, pero el accessor tampoco debe leer ningún atributo
            // dinámico) seguiría sin poder inyectar el secreto en la salida.
            'titular'               => 'Cualquier cosa, incluida una contraseña123',
        ]);

        $this->assertSame(DcInventarioAcceso::CREDENCIAL_OCULTA, $acceso->credencial);
        $this->assertSame('********', $acceso->credencial);
        $this->assertStringContainsString(
            'No almacenada en el sistema por política de seguridad',
            $acceso->credencial_leyenda
        );
    }

    public function test_identificador_publico_se_trunca_a_los_ultimos_4_caracteres(): void
    {
        $acceso = DcInventarioAcceso::create([
            'empresa_id'            => $this->empresa->id,
            'tipo'                  => 'cuenta_bancaria',
            'institucion_o_sistema' => 'Banco de prueba',
            'identificador_publico' => '0123456789',
        ]);

        $this->assertSame('6789', $acceso->fresh()->identificador_publico);
    }

    // ── dc_activos_digitales: regla de titularidad ───────────────────────────

    public function test_un_activo_digital_a_nombre_de_meganet_queda_regular(): void
    {
        $activo = DcActivoDigital::create([
            'empresa_id' => $this->empresa->id,
            'tipo'       => 'sistema',
            'nombre'     => 'Sistema interno de prueba',
            'titular'    => 'MEGANET Telecomunicaciones',
        ]);

        $this->assertSame(DcActivoDigital::TITULARIDAD_REGULAR, $activo->fresh()->titularidad_estado);
    }

    public function test_un_activo_digital_con_titular_distinto_de_meganet_queda_a_regularizar_y_genera_pendiente(): void
    {
        $antes = DcPendiente::where('empresa_id', $this->empresa->id)->count();

        $activo = DcActivoDigital::create([
            'empresa_id' => $this->empresa->id,
            'tipo'       => 'sistema',
            'nombre'     => 'Sistema a nombre de un tercero',
            'titular'    => 'Juan Pérez (persona física, no la empresa)',
        ]);

        $this->assertSame(DcActivoDigital::TITULARIDAD_A_REGULARIZAR, $activo->fresh()->titularidad_estado);

        $this->assertSame($antes + 1, DcPendiente::where('empresa_id', $this->empresa->id)->count());

        $pendiente = DcPendiente::where('empresa_id', $this->empresa->id)
            ->latest('id')->first();

        $this->assertStringContainsString('Titularidad a regularizar', $pendiente->comentarios);
        $this->assertSame('pendiente', $pendiente->estado);
    }

    public function test_dos_activos_del_mismo_tipo_irregulares_no_duplican_el_pendiente(): void
    {
        DcActivoDigital::create([
            'empresa_id' => $this->empresa->id,
            'tipo'       => 'sistema',
            'nombre'     => 'Primer sistema irregular',
            'titular'    => 'Un tercero cualquiera',
        ]);

        $antes = DcPendiente::where('empresa_id', $this->empresa->id)->count();

        DcActivoDigital::create([
            'empresa_id' => $this->empresa->id,
            'tipo'       => 'sistema',
            'nombre'     => 'Segundo sistema irregular',
            'titular'    => 'Otro tercero distinto',
        ]);

        $this->assertSame($antes, DcPendiente::where('empresa_id', $this->empresa->id)->count());
    }
}
