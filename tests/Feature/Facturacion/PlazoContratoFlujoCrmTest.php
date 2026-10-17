<?php

namespace Tests\Feature\Facturacion;

use App\Models\ClientHistorialCambio;
use App\Models\User;
use App\Support\PlazoContrato;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\CreatesApplication;

/**
 * FR-03 (#537), revisión de Irving 2026-10-17: flujo completo del plazo «Especial» por las mismas
 * rutas que usan las pantallas — crear prospecto con Especial 10 meses → editarlo → convertirlo a
 * cliente → el cliente queda «Especial (10 meses)» y el historial lo registra. BD de pruebas en
 * transacción revertida (requiere las migraciones 2026_10_17_* aplicadas en megaisp_test).
 */
class PlazoContratoFlujoCrmTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private User $user;

    private int $especial;

    private int $doce;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        PlazoContrato::olvidar();
        $this->withoutMiddleware();
        $this->user = User::query()->whereNotNull('name')->firstOrFail();
        $this->actingAs($this->user);
        $this->especial = (int) DB::table('duration_contracts')->where('name', 'Especial')->value('id');
        $this->doce = (int) DB::table('duration_contracts')->where('duration', 12)->value('id');
        $this->assertGreaterThan(0, $this->especial, 'falta la migración del plazo Especial en la BD de pruebas');
    }

    protected function tearDown(): void
    {
        PlazoContrato::olvidar();
        parent::tearDown();
    }

    private function prospecto(array $cambios = []): array
    {
        $letras = Str::upper(Str::random(8));
        $letras = preg_replace('/[^A-Z]/', 'X', $letras);

        return $cambios + [
            'name' => 'PRUEBA PLAZO ' . $letras, 'father_last_name' => 'ESPECIAL', 'mother_last_name' => 'FLUJO',
            'email' => 'plazo-' . Str::lower(Str::random(10)) . '@sistema.local', 'email_is_required' => true,
            'phone' => '5500000000', 'crm_status' => 'Nuevo', 'owner_id' => $this->user->id,
            'colony_id' => (int) DB::table('colonies')->value('id'),
            'duration_contract_id' => $this->especial, 'plazo_especial_meses' => 10,
        ];
    }

    private function crearProspecto(array $datos): int
    {
        $r = $this->postJson('/crm/add', $datos);
        $r->assertSuccessful();

        return (int) $r->json('id');
    }

    public function test_especial_exige_los_meses_al_crear_el_prospecto(): void
    {
        $this->postJson('/crm/add', $this->prospecto(['plazo_especial_meses' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('plazo_especial_meses');
    }

    public function test_crear_editar_y_convertir_conserva_especial_10_meses_y_lo_registra(): void
    {
        // 1. Crear prospecto con plazo Especial de 10 meses.
        $datos = $this->prospecto();
        $crmId = $this->crearProspecto($datos);
        $m = DB::table('crm_main_information')->where('crm_id', $crmId)->first();
        $this->assertSame([$this->especial, 10], [(int) $m->duration_contract_id, (int) $m->plazo_especial_meses]);

        // 2. Editarlo (otro dato) conserva el plazo guardado.
        // Igual que la pantalla: manda instalation_date vacío (null), con otro prospecto también sin fecha.
        $this->crearProspecto($this->prospecto());
        $this->postJson("/crm/update/{$crmId}", ['phone' => '5511111111', 'nif_pasaport' => 'XAXX010101000', 'instalation_date' => null] + $datos)->assertSuccessful();
        $m = DB::table('crm_main_information')->where('crm_id', $crmId)->first();
        $this->assertSame(['5511111111', $this->especial, 10], [$m->phone, (int) $m->duration_contract_id, (int) $m->plazo_especial_meses]);

        // 3. Convertirlo en cliente con lo que trae el prospecto.
        $medio = (int) (DB::table('medium_sales')->value('id') ?? 1);
        $r = $this->postJson("/crm/convert-to-client/{$crmId}", [
            'name' => $datos['name'], 'father_last_name' => 'ESPECIAL', 'mother_last_name' => 'FLUJO', 'email' => $datos['email'],
            'phone' => '5511111111', 'colony_id' => $datos['colony_id'], 'connection_type' => 'Fibra', 'estado' => 'Nuevo',
            'seller_id' => $this->user->id, 'medium_id' => $medio,
            'duration_contract_id' => $this->especial, 'plazo_especial_meses' => 10,
        ]);
        $r->assertSuccessful();
        $clientId = (int) $r->getContent();

        $info = \App\Models\ClientMainInformation::where('client_id', $clientId)->firstOrFail();
        $this->assertSame([$this->especial, 10], [(int) $info->duration_contract_id, (int) $info->plazo_especial_meses]);
        $this->assertSame('Especial (10 meses)', $info->contract_months);
        $this->assertSame(10, PlazoContrato::meses($info));

        // 4. El historial del cliente registra el plazo con que nació y de dónde vino.
        $h = ClientHistorialCambio::where('client_id', $clientId)->get()->keyBy('campo');
        $this->assertSame('Especial', $h['plazo']->valor_nuevo ?? null);
        $this->assertSame('10', $h['plazo_especial_meses']->valor_nuevo ?? null);
        $this->assertSame('usuario', $h['plazo']->origen);
        $this->assertSame("Convertido del prospecto CRM #{$crmId}", $h['plazo']->motivo);
        $this->assertSame($this->user->id, (int) $h['plazo']->user_id);
    }

    public function test_editar_no_choca_por_la_fecha_de_instalacion_vacia_pero_si_por_una_repetida(): void
    {
        $this->crearProspecto($this->prospecto());   // otro prospecto SIN fecha (en dev hay miles)
        $otro = $this->crearProspecto($this->prospecto());
        DB::table('crm_lead_information')->where('crm_id', $otro)->update(['instalation_date' => '2026-10-20T10:00']);
        $datos = $this->prospecto();
        $crmId = $this->crearProspecto($datos);
        $base = ['nif_pasaport' => 'XAXX010101000'] + $datos;

        $this->postJson("/crm/update/{$crmId}", ['instalation_date' => null] + $base)->assertSuccessful();
        $this->postJson("/crm/update/{$crmId}", ['instalation_date' => '2026-10-20T10:00'] + $base)
            ->assertStatus(422)->assertJsonValidationErrors('instalation_date');
        $this->postJson("/crm/update/{$otro}", ['instalation_date' => '2026-10-20T10:00', 'nif_pasaport' => 'XAXX010101000'] + $this->prospecto())
            ->assertSuccessful(); // su propia fecha no cuenta como repetida
    }

    public function test_al_cambiar_a_un_plazo_del_catalogo_los_meses_se_borran(): void
    {
        $datos = $this->prospecto();
        $crmId = $this->crearProspecto($datos);

        $this->postJson("/crm/update/{$crmId}", ['nif_pasaport' => 'XAXX010101000', 'instalation_date' => null, 'duration_contract_id' => $this->doce] + $datos)->assertSuccessful();

        $m = DB::table('crm_main_information')->where('crm_id', $crmId)->first();
        $this->assertSame($this->doce, (int) $m->duration_contract_id);
        $this->assertNull($m->plazo_especial_meses);
    }

    public function test_la_lista_de_crm_muestra_el_plazo(): void
    {
        $crmId = $this->crearProspecto($this->prospecto());
        $helper = app(\App\Http\HelpersModule\module\crm\CrmDatatableHelper::class);
        $plazos = (fn (array $ids) => $this->plazosDeContrato($ids))->call($helper, [$crmId]);

        $this->assertSame('Especial (10 meses)', $plazos[$crmId] ?? null);
    }
}
