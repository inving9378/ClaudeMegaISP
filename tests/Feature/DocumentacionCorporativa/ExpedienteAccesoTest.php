<?php

namespace Tests\Feature\DocumentacionCorporativa;

use App\Models\User;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcAccesoLog;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcApartado;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use App\Modules\Addons\DocumentacionCorporativa\Resolvers\ResolverFactory;
use App\Modules\Addons\DocumentacionCorporativa\Services\CompletitudService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\CreatesApplication;

/**
 * Permisos, bitácora, resolvedores y completitud del expediente corporativo.
 *
 * USA DatabaseTransactions (rollback al terminar cada test), NO migrate:fresh.
 * Ejecutar selectivo:  php artisan test --filter=ExpedienteAccesoTest
 */
class ExpedienteAccesoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private DcEmpresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->empresa = DcEmpresa::activas()->firstOrFail();
        session(['dc.empresa_id' => $this->empresa->id]);
    }

    private function usuarioCon(array $permisos, string $sufijo): User
    {
        $user = User::create([
            'name'       => 'Prueba DC ' . $sufijo,
            'login_user' => 'prueba_dc_' . $sufijo . '_' . uniqid(),
        ]);

        foreach ($permisos as $nombre) {
            $user->givePermissionTo(Permission::where('name', $nombre)->firstOrFail());
        }

        return $user->fresh();
    }

    private function apartado(string $clave): DcApartado
    {
        return DcApartado::deEmpresa($this->empresa->id)->where('clave', $clave)->firstOrFail();
    }

    // ── Permisos ────────────────────────────────────────────────────────────

    public function test_sin_el_permiso_del_modulo_no_se_entra(): void
    {
        $user = $this->usuarioCon([], 'sin_nada');

        // Política de denegación silenciosa del sistema (item #537): la
        // navegación de página completa redirige en silencio al dashboard...
        $this->actingAs($user)
            ->get('/documentacion-corporativa')
            ->assertStatus(302);

        // ...pero las llamadas JSON conservan el 403 real.
        $this->actingAs($user)
            ->getJson('/documentacion-corporativa/api/tablero')
            ->assertStatus(403);
    }

    public function test_el_tablero_solo_devuelve_los_apartados_que_el_usuario_puede_ver(): void
    {
        $user = $this->usuarioCon([
            'documentacion-corporativa.view',
            'documentacion-corporativa.apartado.i.view',
            'documentacion-corporativa.apartado.iv.view',
        ], 'dos_apartados');

        $respuesta = $this->actingAs($user)->getJson('/documentacion-corporativa/api/tablero');

        $respuesta->assertOk();
        $claves = array_column($respuesta->json('apartados'), 'clave');

        $this->assertEqualsCanonicalizing(['I', 'IV'], $claves);
        $this->assertSame(2, $respuesta->json('global.apartados'));
    }

    public function test_el_detalle_de_un_apartado_sin_permiso_responde_403(): void
    {
        $user = $this->usuarioCon([
            'documentacion-corporativa.view',
            'documentacion-corporativa.apartado.i.view',
        ], 'solo_i');

        $this->actingAs($user)->getJson('/documentacion-corporativa/api/apartado/I')->assertOk();
        $this->actingAs($user)->getJson('/documentacion-corporativa/api/apartado/XI')->assertStatus(403);
    }

    public function test_el_rol_consejo_ve_trece_apartados_pero_nunca_el_de_bancos(): void
    {
        $consejo = Role::where('name', 'consejo')->firstOrFail();

        $user = User::create([
            'name'       => 'Consejero de prueba',
            'login_user' => 'prueba_dc_consejo_' . uniqid(),
        ]);
        $user->assignRole($consejo);

        $respuesta = $this->actingAs($user->fresh())->getJson('/documentacion-corporativa/api/tablero');

        $respuesta->assertOk();
        $claves = array_column($respuesta->json('apartados'), 'clave');

        $this->assertCount(13, $claves);
        $this->assertNotContains('XI', $claves);

        // Y tampoco puede descargar, ni leer la bitácora que lo registra a él.
        $this->assertFalse($user->can('documentacion-corporativa.documento.download'));
        $this->assertFalse($user->can('documentacion-corporativa.bitacora.view'));
    }

    // ── Bitácora ────────────────────────────────────────────────────────────

    public function test_ver_el_tablero_deja_rastro_en_la_bitacora(): void
    {
        $user = $this->usuarioCon([
            'documentacion-corporativa.view',
            'documentacion-corporativa.apartado.i.view',
        ], 'bitacora_tablero');

        $antes = DcAccesoLog::count();

        $this->actingAs($user)->getJson('/documentacion-corporativa/api/tablero')->assertOk();

        $this->assertSame($antes + 1, DcAccesoLog::count());

        $fila = DcAccesoLog::recientes()->first();
        $this->assertSame(DcAccesoLog::ACCION_VER, $fila->accion);
        $this->assertSame($user->id, $fila->user_id);
        $this->assertSame('tablero', $fila->contexto['pantalla']);
        $this->assertSame(['I'], $fila->contexto['apartados_visibles']);
    }

    public function test_abrir_un_apartado_registra_cual_fue(): void
    {
        $user = $this->usuarioCon([
            'documentacion-corporativa.view',
            'documentacion-corporativa.apartado.iv.view',
        ], 'bitacora_apartado');

        $this->actingAs($user)->getJson('/documentacion-corporativa/api/apartado/IV')->assertOk();

        $fila = DcAccesoLog::recientes()->first();
        $this->assertSame($this->apartado('IV')->id, $fila->apartado_id);
        $this->assertSame('IV', $fila->contexto['clave']);
    }

    public function test_un_apartado_denegado_no_ensucia_la_bitacora(): void
    {
        $user = $this->usuarioCon(['documentacion-corporativa.view'], 'denegado');

        $antes = DcAccesoLog::where('apartado_id', $this->apartado('XI')->id)->count();

        $this->actingAs($user)->getJson('/documentacion-corporativa/api/apartado/XI')->assertStatus(403);

        // Lo que no se sirvió no se registra como visto.
        $this->assertSame(
            $antes,
            DcAccesoLog::where('apartado_id', $this->apartado('XI')->id)->count()
        );
    }

    public function test_la_bitacora_es_append_only(): void
    {
        $this->assertNull(DcAccesoLog::UPDATED_AT);
        $this->assertNotContains(
            'deleted_at',
            \Illuminate\Support\Facades\Schema::getColumnListing('dc_accesos_log'),
            'La bitácora no debe poder borrarse ni en suave.'
        );
    }

    // ── Resolvedores ────────────────────────────────────────────────────────

    public function test_un_concepto_sin_fuente_registrada_cae_a_pendiente_y_lo_explica(): void
    {
        $concepto = DcConcepto::deEmpresa($this->empresa->id)
            ->where('slug', 'cartera-de-clientes')->firstOrFail();

        $resultado = app(ResolverFactory::class)
            ->para($concepto, $this->empresa->id)
            ->resolver($concepto, $this->empresa->id);

        $this->assertSame(ResultadoConcepto::SIN_FUENTE, $resultado->estado);
        $this->assertFalse($resultado->cuentaComoResuelto());
        $this->assertStringContainsString('Sin fuente configurada', $resultado->mensaje);
    }

    public function test_una_fuente_registrada_hace_que_el_concepto_se_resuelva(): void
    {
        // Así es como la Fase 1 conecta sus fuentes: registrando, sin tocar resolvedores.
        app(FuenteRegistry::class)->registrar(
            'clientes.cartera',
            fn (array $config, int $empresaId) => [
                'datos'    => [['cliente' => 'ACME', 'saldo' => 100]],
                'metricas' => ['registros' => 1],
            ]
        );

        $concepto = DcConcepto::deEmpresa($this->empresa->id)
            ->where('slug', 'cartera-de-clientes')->firstOrFail();

        $resultado = app(ResolverFactory::class)
            ->para($concepto, $this->empresa->id)
            ->resolver($concepto, $this->empresa->id);

        $this->assertSame(ResultadoConcepto::RESUELTO, $resultado->estado);
        $this->assertTrue($resultado->cuentaComoResuelto());
        $this->assertCount(1, $resultado->datos);
    }

    public function test_una_fuente_que_revienta_no_tumba_el_apartado(): void
    {
        app(FuenteRegistry::class)->registrar(
            'finanzas.saldos_pendientes',
            fn () => throw new \RuntimeException('la fuente se cayó')
        );

        $concepto = DcConcepto::deEmpresa($this->empresa->id)
            ->where('slug', 'saldos-pendientes-de-cobro')->firstOrFail();

        $resultado = app(ResolverFactory::class)
            ->para($concepto, $this->empresa->id)
            ->resolver($concepto, $this->empresa->id);

        $this->assertSame(ResultadoConcepto::SIN_FUENTE, $resultado->estado);
        $this->assertTrue($resultado->metricas['error']);
    }

    public function test_un_documento_vigente_resuelve_el_concepto_y_uno_vencido_no(): void
    {
        $concepto = DcConcepto::deEmpresa($this->empresa->id)
            ->where('slug', 'acta-constitutiva-y-modificaciones')->firstOrFail();

        $factory = app(ResolverFactory::class);

        // Sin documentos: vacío.
        $this->assertSame(
            ResultadoConcepto::VACIO,
            $factory->para($concepto, $this->empresa->id)->resolver($concepto, $this->empresa->id)->estado
        );

        $vencido = $this->documentoPara($concepto, now()->subDay());
        $this->assertSame(
            ResultadoConcepto::PARCIAL,
            $factory->para($concepto, $this->empresa->id)->resolver($concepto, $this->empresa->id)->estado
        );

        $this->documentoPara($concepto, now()->addYear());
        $resultado = $factory->para($concepto, $this->empresa->id)->resolver($concepto, $this->empresa->id);

        $this->assertSame(ResultadoConcepto::RESUELTO, $resultado->estado);
        $this->assertTrue($resultado->cuentaComoResuelto());
        $this->assertSame(1, $resultado->metricas['vencidos']);
        $this->assertSame(1, $resultado->metricas['documentos_vigentes']);
        $this->assertNotNull($vencido->id);
    }

    private function documentoPara(DcConcepto $concepto, $vigenciaFin): DcDocumento
    {
        return DcDocumento::create([
            'empresa_id'              => $this->empresa->id,
            'concepto_id'             => $concepto->id,
            'titulo'                  => 'Documento de prueba',
            'archivo_uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'archivo_nombre_original' => 'prueba.pdf',
            'mime'                    => 'application/pdf',
            'bytes'                   => 1024,
            'hash'                    => str_repeat('a', 64),
            'vigencia_fin'            => $vigenciaFin,
        ]);
    }

    // ── Completitud ─────────────────────────────────────────────────────────

    public function test_la_completitud_se_mide_sobre_los_obligatorios(): void
    {
        $apartado = $this->apartado('I');
        $empresaId = $this->empresa->id;

        $resumen = app(CompletitudService::class)->apartado($apartado, $empresaId);

        // Los 10 conceptos del apartado I son obligatorios y ninguno está resuelto aún.
        $this->assertSame(10, $resumen['obligatorios']);
        $this->assertSame(0, $resumen['resueltos']);
        $this->assertSame(0, $resumen['porcentaje']);
        $this->assertSame('rojo', $resumen['semaforo']);
        $this->assertCount(10, $resumen['faltantes']);

        // Resolver uno mueve la aguja exactamente 1/10.
        $this->documentoPara(
            DcConcepto::deEmpresa($empresaId)->where('slug', 'acta-constitutiva-y-modificaciones')->firstOrFail(),
            now()->addYear()
        );

        $resumen = app(CompletitudService::class)->apartado($apartado, $empresaId);

        $this->assertSame(1, $resumen['resueltos']);
        $this->assertSame(10, $resumen['porcentaje']);
    }

    public function test_un_apartado_sin_obligatorios_no_es_medible_y_nunca_sale_verde(): void
    {
        // El apartado IV (cartera, saldos, proveedores, ingresos) no declara
        // conceptos obligatorios. La primera versión lo pintaba VERDE al 100%
        // con cero datos dentro: un tablero que dice "completo" sobre un
        // apartado vacío no se vuelve a revisar.
        $resumen = app(CompletitudService::class)->apartado($this->apartado('IV'), $this->empresa->id);

        $this->assertSame(0, $resumen['obligatorios']);
        $this->assertFalse($resumen['medible']);
        $this->assertSame('gris', $resumen['semaforo']);
        $this->assertNotSame('verde', $resumen['semaforo']);
        $this->assertSame(0, $resumen['porcentaje']);
    }

    public function test_el_global_del_tablero_lo_calcula_el_servicio_no_el_controlador(): void
    {
        // Una sola definición de porcentaje y semáforo. Si el controlador
        // volviera a calcularlo por su cuenta, este test no lo detectaría —
        // pero sí detecta que el contrato del agregado es el mismo.
        $user = $this->usuarioCon([
            'documentacion-corporativa.view',
            'documentacion-corporativa.apartado.i.view',
        ], 'global');

        $respuesta = $this->actingAs($user)->getJson('/documentacion-corporativa/api/tablero');

        $esperado = app(CompletitudService::class)->agregarGlobal($respuesta->json('apartados'));

        $this->assertSame($esperado, $respuesta->json('global'));
    }
}
