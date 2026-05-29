<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReferralDemoDataSeeder extends Seeder
{
    const ADMIN_ID = 3986;

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $this->cleanPreviousData();

        $now        = Carbon::now();
        $clientIds  = [];     // [level => [clientId, ...]]
        $referralCodes = [];  // [clientId => code]

        // ─── 1. CREAR 31 CLIENTES (árbol binario 5 niveles) ──────────────
        $names = [
            0 => ['Ana Torres'],
            1 => ['Carlos Méndez', 'Laura Jiménez'],
            2 => ['Pedro Ruiz', 'María López', 'Jorge Soto', 'Elena Vargas'],
            3 => ['Luis Mora', 'Carmen Díaz', 'Roberto Silva', 'Patricia Núñez',
                  'Miguel Reyes', 'Sandra Castro', 'Felipe Herrera', 'Diana Flores'],
            4 => ['Oscar Ramos', 'Verónica Cruz', 'Andrés Vega', 'Sofía Peña',
                  'Héctor Rojas', 'Gabriela Ortiz', 'Ernesto Lara', 'Natalia Campos',
                  'Raúl Aguilar', 'Claudia Ríos', 'Marcos Delgado', 'Irene Salazar',
                  'Tomás Guerrero', 'Alicia Mendoza', 'Javier Espinoza', 'Rosa Fuentes'],
        ];

        $planTypes = ['multilevel', 'multilevel', 'single_reward'];
        $index = 1;

        foreach ($names as $level => $levelNames) {
            foreach ($levelNames as $name) {
                $parts = explode(' ', $name, 2);
                $slug  = 'demo' . $index;
                $ascii = preg_replace('/[^a-zA-Z]/', '', $parts[0]);
                $code  = 'MGN-' . str_pad(strtoupper(substr($ascii, 0, 3)), 3, 'X') . '-' . strtoupper(Str::random(4));

                $clientId = DB::table('clients')->insertGetId([
                    'is_test_data' => 1,
                    'created_by'   => self::ADMIN_ID,
                    'updated_by'   => self::ADMIN_ID,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                DB::table('client_main_information')->insert([
                    'client_id'       => $clientId,
                    'name'            => $parts[0],
                    'father_last_name'=> $parts[1] ?? null,
                    'email'           => $slug . '@demo.meganet.com',
                    'password'        => 'Demo1234!',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                DB::table('client_referral_profiles')->insert([
                    'client_id'    => $clientId,
                    'referral_code'=> $code,
                    'plan_type'    => $planTypes[$index % 3],
                    'is_eligible'  => 1,
                    'activated_at' => $now->copy()->subDays(30),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                $clientIds[$level][] = $clientId;
                $referralCodes[$clientId] = $code;
                $index++;
            }
        }

        // ─── 2. REFERRALS con chain_path coherente ────────────────────────
        for ($level = 1; $level <= 4; $level++) {
            foreach ($clientIds[$level] as $childIndex => $referredClientId) {
                $parentIndex = (int) floor($childIndex / 2);
                $embajadorId = $clientIds[$level - 1][$parentIndex];
                $chainPath   = $this->buildChainPath($clientIds, $level - 1, $parentIndex) . $embajadorId . '/';

                DB::table('clients')
                    ->where('id', $referredClientId)
                    ->update(['referred_by_code' => $referralCodes[$embajadorId]]);

                $createdAt = $now->copy()->subDays(30 - $level * 5);

                $referralId = DB::table('referrals')->insertGetId([
                    'embajador_id'                   => $embajadorId,
                    'referred_client_id'             => $referredClientId,
                    'chain_path'                     => $chainPath,
                    'chain_depth'                    => $level,
                    'status'                         => 'active',
                    'referred_threshold_amount_paid' => 1500.00,
                    'referred_threshold_covered_at'  => $createdAt,
                    'commission_window_start'        => $createdAt,
                    'commissions_paid_count'         => 1,
                    'created_at'                     => $createdAt,
                    'updated_at'                     => $now,
                ]);

                // ─── 3. COMISIONES ────────────────────────────────────────
                $pct              = [1 => 15.00, 2 => 7.00, 3 => 4.00, 4 => 3.00][$level];
                $tierId           = $level <= 1 ? 3 : ($level <= 3 ? 2 : 1);
                $baseAmount       = 500.00;
                $commissionAmount = round($baseAmount * ($pct / 100), 2);

                DB::table('referral_commissions')->insert([
                    'beneficiary_id'    => $embajadorId,
                    'referral_id'       => $referralId,
                    'invoice_id'        => $referralId, // FK deshabilitado — dato demo
                    'level'             => $level,
                    'commission_pct'    => $pct,
                    'base_amount'       => $baseAmount,
                    'commission_amount' => $commissionAmount,
                    'period_month'      => (int) $now->format('m'),
                    'period_year'       => (int) $now->format('Y'),
                    'status'            => 'approved',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $total = DB::table('clients')->where('is_test_data', 1)->count();
        $this->command->info("✅ ReferralDemoDataSeeder completo — {$total} clientes demo creados");
    }

    private function cleanPreviousData(): void
    {
        $ids = DB::table('clients')->where('is_test_data', 1)->pluck('id');
        if ($ids->isEmpty()) return;

        DB::table('referral_commissions')->whereIn('beneficiary_id', $ids)->delete();
        DB::table('referrals')->whereIn('embajador_id', $ids)->orWhereIn('referred_client_id', $ids)->delete();
        DB::table('client_referral_profiles')->whereIn('client_id', $ids)->delete();
        DB::table('client_main_information')->whereIn('client_id', $ids)->delete();
        DB::table('clients')->where('is_test_data', 1)->delete();
    }

    private function buildChainPath(array $clientIds, int $level, int $index): string
    {
        if ($level === 0) return '/';
        $parentIndex = (int) floor($index / 2);
        return $this->buildChainPath($clientIds, $level - 1, $parentIndex)
            . $clientIds[$level - 1][$parentIndex] . '/';
    }
}
