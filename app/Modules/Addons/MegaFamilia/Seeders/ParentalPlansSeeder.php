<?php

namespace App\Modules\Addons\MegaFamilia\Seeders;

use App\Modules\Addons\MegaFamilia\Models\ParentalPlan;
use Illuminate\Database\Seeder;

class ParentalPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'basico',
                'name' => 'Básico',
                'price_monthly' => 90.00,
                'max_children' => 2,
                'max_devices' => 3,
                'max_parents' => 1,
                'features' => [
                    'control_pantalla' => true,
                    'limites_diarios' => true,
                    'bloqueo_apps' => true,
                    'gps' => false,
                    'mikrotik' => false,
                    'ia' => false,
                ],
                'active' => true,
            ],
            [
                'slug' => 'plus',
                'name' => 'Plus',
                'price_monthly' => 150.00,
                'max_children' => 5,
                'max_devices' => 10,
                'max_parents' => 2,
                'features' => [
                    'control_pantalla' => true,
                    'limites_diarios' => true,
                    'bloqueo_apps' => true,
                    'bloqueo_web' => true,
                    'geofence' => true,
                    'tareas_recompensas' => true,
                    'gps' => true,
                    'mikrotik' => false,
                    'ia' => false,
                ],
                'active' => true,
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'price_monthly' => 220.00,
                'max_children' => 0,   // 0 = ilimitado
                'max_devices' => 0,
                'max_parents' => 0,
                'features' => [
                    'control_pantalla' => true,
                    'limites_diarios' => true,
                    'bloqueo_apps' => true,
                    'bloqueo_web' => true,
                    'geofence' => true,
                    'tareas_recompensas' => true,
                    'gps' => true,
                    'mikrotik' => true,
                    'ia' => true,
                    'soporte_prioritario' => true,
                ],
                'active' => true,
            ],
        ];

        foreach ($plans as $p) {
            ParentalPlan::updateOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }
    }
}
