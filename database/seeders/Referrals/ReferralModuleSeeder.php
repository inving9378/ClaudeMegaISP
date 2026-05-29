<?php

namespace Database\Seeders\Referrals;

use App\Models\Referrals\ReferralCommissionTier;
use App\Models\Referrals\ReferralSetting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReferralModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedCommissionTiers();
        $this->seedPermissions();
    }

    private function seedSettings(): void
    {
        ReferralSetting::firstOrCreate([], [
            'program_active'         => true,
            'threshold_amount'       => 1500.00,
            'duration_months'        => 12,
            'max_levels'             => 5,
            'program_name'           => 'Embajadores Meganet',
            'welcome_message'        => '¡Bienvenido al programa Embajadores Meganet! Recomienda el servicio y gana beneficios por cada cliente que contrate.',
            'share_template_default' => '¡Te invito a conocer Meganet! El mejor internet de la zona. Usa mi código y obtén una instalación preferencial: {referral_link}',
            'terms_conditions'       => 'El programa Embajadores Meganet es un programa de lealtad por recomendación. Las comisiones y recompensas se generan únicamente por la contratación real del servicio de internet. No existe pago por reclutamiento.',
        ]);
    }

    private function seedCommissionTiers(): void
    {
        $tiers = [
            [
                'tier_name'      => 'basic',
                'speed_min_mbps' => 0,
                'speed_max_mbps' => 50,
                'level_1_pct'    => 3.00,
                'level_2_pct'    => 1.50,
                'level_3_pct'    => 1.00,
                'level_4_pct'    => 0.50,
                'level_5_pct'    => 0.30,
                'active'         => true,
            ],
            [
                'tier_name'      => 'standard',
                'speed_min_mbps' => 51,
                'speed_max_mbps' => 150,
                'level_1_pct'    => 10.00,
                'level_2_pct'    => 5.00,
                'level_3_pct'    => 3.00,
                'level_4_pct'    => 2.00,
                'level_5_pct'    => 1.00,
                'active'         => true,
            ],
            [
                'tier_name'      => 'premium',
                'speed_min_mbps' => 151,
                'speed_max_mbps' => 9999,
                'level_1_pct'    => 15.00,
                'level_2_pct'    => 7.00,
                'level_3_pct'    => 4.00,
                'level_4_pct'    => 2.50,
                'level_5_pct'    => 1.50,
                'active'         => true,
            ],
        ];

        foreach ($tiers as $tier) {
            ReferralCommissionTier::firstOrCreate(
                ['tier_name' => $tier['tier_name']],
                $tier
            );
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'embajadores.view',
            'embajadores.configure',
            'embajadores.tiers.manage',
            'embajadores.commissions.approve',
            'embajadores.commissions.cancel',
            'embajadores.rewards.manage',
            'embajadores.payouts.generate',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Asignar a DESARROLLADOR y ADMINISTRADOR
        foreach (['DESARROLLADOR', 'ADMINISTRADOR'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }
    }
}
