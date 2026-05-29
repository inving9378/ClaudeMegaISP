<?php

namespace Tests\Feature\Referrals;

use App\Jobs\Referrals\ProcessReferralCommissions;
use App\Models\Internet;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralCommissionTier;
use App\Models\Referrals\ReferralReward;
use App\Models\Referrals\ReferralSetting;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientInternetService;
use App\Modules\Core\Clientes\Models\ClientInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\CreatesApplication;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

/**
 * Base: DatabaseTransactions en lugar de migrate:fresh — no destruye datos de producción.
 * Cada test envuelve sus writes en una transacción que se revierte al finalizar.
 */
abstract class ReferralTestCase extends LaravelTestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected ReferralSetting $settings;
    protected ReferralCommissionTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = ReferralSetting::firstOrCreate([], [
            'program_active'   => true,
            'threshold_amount' => 1500.00,
            'duration_months'  => 12,
            'max_levels'       => 5,
            'program_name'     => 'Test Embajadores',
        ]);

        $this->settings->update(['program_active' => true]);

        // Limpiar tiers de producción que interferirían con los asserts de porcentaje.
        // La transacción los restaura al finalizar cada test.
        ReferralCommissionTier::query()->delete();
        $this->tier = ReferralCommissionTier::factory()->create();
    }

    // ── Helpers de construcción de escenarios ──────────────────────────────

    protected function makeEligibleEmbajador(string $planType = 'multilevel'): array
    {
        $client  = Client::factory()->create();
        $profile = ClientReferralProfile::factory()
            ->forClient($client)
            ->eligible()
            ->state(['plan_type' => $planType])
            ->create();

        Internet::factory()->speedKbps(102400)->create(); // 100 Mbps
        ClientInternetService::factory()->create([
            'client_id'   => $client->id,
            'internet_id' => Internet::factory()->speedKbps(102400)->create()->id,
            'estado'      => ClientInternetService::STATE_ACTIVE,
        ]);

        return [$client, $profile];
    }

    protected function makeReferredClient(Client $embajador, string $status = 'pending_threshold', int $commissionsCount = 0): array
    {
        $referred = Client::factory()->create();
        $internet = Internet::factory()->speedKbps(102400)->create();

        ClientInternetService::factory()->create([
            'client_id'   => $referred->id,
            'internet_id' => $internet->id,
            'estado'      => ClientInternetService::STATE_ACTIVE,
        ]);

        $referral = Referral::factory()
            ->forEmbajador($embajador)
            ->forReferredClient($referred)
            ->state([
                'status'                         => $status,
                'commissions_paid_count'         => $commissionsCount,
                'referred_threshold_amount_paid' => $status === 'active' ? 1500.00 : 0,
                'referred_threshold_covered_at'  => $status === 'active' ? now()->subDays(10) : null,
                'commission_window_start'        => $status === 'active' ? now()->subDays(10) : null,
            ])
            ->create();

        return [$referred, $referral];
    }

    protected function makeInvoice(Client $client, float $total = 299.00): ClientInvoice
    {
        return ClientInvoice::factory()->forClient($client)->total($total)->create();
    }

    protected function dispatchJob(ClientInvoice $invoice): void
    {
        (new ProcessReferralCommissions($invoice))->handle();
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TESTS
// ═══════════════════════════════════════════════════════════════════════════

class ProcessReferralCommissionsTest extends ReferralTestCase
{
    // 1. Plan A: reward al cruzar umbral
    public function test_plan_a_genera_reward_al_cruzar_umbral(): void
    {
        [$embajador, $profile] = $this->makeEligibleEmbajador('single_reward');
        [$referred, $referral] = $this->makeReferredClient($embajador, 'pending_threshold');

        // Factura que lleva el acumulado de $0 a $1,800 (cruza los $1,500)
        $invoice = $this->makeInvoice($referred, 1800.00);
        $this->dispatchJob($invoice);

        $this->assertDatabaseHas('referral_rewards', [
            'referral_id'  => $referral->id,
            'embajador_id' => $embajador->id,
            'type'         => 'free_month',
            'status'       => 'available',
        ]);

        $this->assertEquals('completed', $referral->fresh()->status);
    }

    // 2. Plan B: comisión nivel 1 correcta
    public function test_plan_b_genera_comision_nivel_1_correctamente(): void
    {
        [$embajador, $profile] = $this->makeEligibleEmbajador('multilevel');
        [$referred, $referral] = $this->makeReferredClient($embajador, 'active');

        $invoice = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice);

        $commission = ReferralCommission::where('invoice_id', $invoice->id)
            ->where('beneficiary_id', $embajador->id)
            ->where('level', 1)
            ->first();

        $this->assertNotNull($commission);
        $this->assertEquals('approved', $commission->status);
        $this->assertNotNull($commission->apply_after_at);

        $expectedAmount = round(299.00 * 5.00 / 100, 2); // tier level_1_pct = 5%
        $this->assertEquals($expectedAmount, (float) $commission->commission_amount);
    }

    // 3. Plan B: cadena multinivel 5 niveles
    public function test_plan_b_genera_cadena_multinivel_5_niveles(): void
    {
        // Construir cadena: L5 → L4 → L3 → L2 → L1 (embajador directo)
        $clients  = collect(range(1, 5))->map(fn() => Client::factory()->create());
        $profiles = $clients->map(function ($c) {
            ClientInternetService::factory()->create([
                'client_id'   => $c->id,
                'internet_id' => Internet::factory()->speedKbps(102400)->create()->id,
                'estado'      => ClientInternetService::STATE_ACTIVE,
            ]);
            return ClientReferralProfile::factory()->forClient($c)->multilevel()->eligible()->create();
        });

        // chain_path: '/L5/L4/L3/L2/L1/' — getUpstreamChain() → [L1, L2, L3, L4, L5]
        $chainPath = '/' . $clients->reverse()->map->id->implode('/') . '/';

        $referred = Client::factory()->create();
        $internet = Internet::factory()->speedKbps(102400)->create();
        ClientInternetService::factory()->create([
            'client_id'   => $referred->id,
            'internet_id' => $internet->id,
            'estado'      => ClientInternetService::STATE_ACTIVE,
        ]);

        $referral = Referral::factory()->create([
            'embajador_id'                   => $clients->last()->id, // nivel 1
            'referred_client_id'             => $referred->id,
            'chain_path'                     => $chainPath,
            'chain_depth'                    => 5,
            'status'                         => 'active',
            'referred_threshold_amount_paid' => 1500.00,
            'referred_threshold_covered_at'  => now()->subDays(10),
            'commission_window_start'        => now()->subDays(10),
            'commissions_paid_count'         => 0,
        ]);

        $invoice = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice);

        $commissions = ReferralCommission::where('invoice_id', $invoice->id)->get();
        $this->assertCount(5, $commissions, 'Deben generarse 5 comisiones (una por nivel)');

        foreach (range(1, 5) as $level) {
            $this->assertTrue(
                $commissions->where('level', $level)->isNotEmpty(),
                "Falta comisión de nivel $level"
            );
        }
    }

    // 4. No genera comisión si embajador no es elegible
    public function test_no_genera_comision_si_embajador_no_es_elegible(): void
    {
        $embajador = Client::factory()->create();
        // Perfil no elegible (no ha cruzado su propio umbral de $1,500)
        ClientReferralProfile::factory()->forClient($embajador)->notYetEligible()->create();

        [$referred, $referral] = $this->makeReferredClient($embajador, 'active');

        $invoice = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice);

        $this->assertDatabaseMissing('referral_commissions', ['invoice_id' => $invoice->id]);
    }

    // 5. No genera comisión si referido no cruzó umbral
    public function test_no_genera_comision_si_referido_no_cruzo_umbral(): void
    {
        [$embajador] = $this->makeEligibleEmbajador('multilevel');
        [$referred, $referral] = $this->makeReferredClient($embajador, 'pending_threshold');

        // Factura pequeña que no cruza los $1,500
        $invoice = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice);

        $this->assertDatabaseMissing('referral_commissions', ['invoice_id' => $invoice->id]);
        $this->assertEquals('pending_threshold', $referral->fresh()->status);
    }

    // 6. No genera comisión si se alcanzaron 12 pagos (R4)
    public function test_no_genera_comision_si_se_alcanzaron_12_pagos(): void
    {
        [$embajador] = $this->makeEligibleEmbajador('multilevel');
        [$referred, $referral] = $this->makeReferredClient($embajador, 'active', 12);

        $invoice = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice);

        $this->assertDatabaseMissing('referral_commissions', ['invoice_id' => $invoice->id]);
        $this->assertEquals('completed', $referral->fresh()->status);
    }

    // 7. Baja de referido congela pero no borra comisiones aprobadas
    public function test_baja_de_referido_congela_pero_no_borra_comisiones(): void
    {
        [$embajador, $profile] = $this->makeEligibleEmbajador('multilevel');
        [$referred, $referral] = $this->makeReferredClient($embajador, 'active');

        // Primer pago genera comisión
        $invoice1 = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice1);

        $this->assertDatabaseHas('referral_commissions', [
            'invoice_id' => $invoice1->id,
            'status'     => 'approved',
        ]);

        // Simular baja del referido (cancela el referral)
        $referral->update(['status' => 'cancelled']);

        // Segundo pago NO debe generar comisión nueva
        $invoice2 = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice2);

        $this->assertDatabaseMissing('referral_commissions', ['invoice_id' => $invoice2->id]);

        // La primera comisión sigue intacta (no fue borrada)
        $this->assertDatabaseHas('referral_commissions', [
            'invoice_id' => $invoice1->id,
            'status'     => 'approved',
        ]);
    }

    // 8. No genera comisión duplicada si se reprocesa la misma factura
    public function test_no_se_genera_comision_duplicada_si_se_reprocesa_misma_factura(): void
    {
        [$embajador] = $this->makeEligibleEmbajador('multilevel');
        [$referred, $referral] = $this->makeReferredClient($embajador, 'active');

        $invoice = $this->makeInvoice($referred, 299.00);

        // Disparar el job dos veces para la misma factura
        $this->dispatchJob($invoice);
        $this->dispatchJob($invoice);

        $count = ReferralCommission::where('invoice_id', $invoice->id)
            ->where('beneficiary_id', $embajador->id)
            ->where('level', 1)
            ->count();

        $this->assertEquals(1, $count, 'No debe haber comisiones duplicadas para la misma factura');
    }

    // 9. Cálculo respeta el tier del paquete del referido
    public function test_calculo_porcentaje_respeta_tier_del_paquete_del_referido(): void
    {
        // Crear tier específico para velocidad alta (200 Mbps)
        $tierAlto = ReferralCommissionTier::factory()->create([
            'tier_name'      => 'Premium',
            'speed_min_mbps' => 150,
            'speed_max_mbps' => 300,
            'level_1_pct'    => 8.00,
            'level_2_pct'    => 4.00,
            'level_3_pct'    => 2.00,
            'level_4_pct'    => 1.00,
            'level_5_pct'    => 0.50,
            'active'         => 1,
        ]);

        // Actualizar el tier default para no solapar rangos
        $this->tier->update(['speed_max_mbps' => 149]);

        [$embajador] = $this->makeEligibleEmbajador('multilevel');

        $referred = Client::factory()->create();
        $internet = Internet::factory()->speedKbps(204800)->create(); // 200 Mbps → tier premium
        ClientInternetService::factory()->create([
            'client_id'   => $referred->id,
            'internet_id' => $internet->id,
            'estado'      => ClientInternetService::STATE_ACTIVE,
        ]);

        Referral::factory()->create([
            'embajador_id'                   => $embajador->id,
            'referred_client_id'             => $referred->id,
            'chain_path'                     => '/' . $embajador->id . '/',
            'chain_depth'                    => 1,
            'status'                         => 'active',
            'referred_threshold_amount_paid' => 1500.00,
            'referred_threshold_covered_at'  => now()->subDays(10),
            'commission_window_start'        => now()->subDays(10),
            'commissions_paid_count'         => 0,
        ]);

        $invoice = $this->makeInvoice($referred, 299.00);
        $this->dispatchJob($invoice);

        $commission = ReferralCommission::where('invoice_id', $invoice->id)
            ->where('level', 1)
            ->first();

        $this->assertNotNull($commission);

        $expectedAmount = round(299.00 * 8.00 / 100, 2); // tier premium level_1_pct = 8%
        $this->assertEquals($expectedAmount, (float) $commission->commission_amount);
        $this->assertEquals(8.00, (float) $commission->commission_pct);
    }
}
