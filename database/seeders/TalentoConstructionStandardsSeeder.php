<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalentoConstructionStandardsSeeder extends Seeder
{
    public function run(): void
    {
        $standards = [
            [
                'name'        => 'Pérdida de fusión óptica',
                'type'        => 'fusion_loss',
                'ideal_value' => '≤ 0.3 dB',
                'active'      => true,
            ],
            [
                'name'        => 'Pérdida de fusión aceptable',
                'type'        => 'fusion_loss',
                'ideal_value' => '0.3 – 0.5 dB (alerta)',
                'active'      => true,
            ],
            [
                'name'        => 'Potencia óptica cliente residencial',
                'type'        => 'power',
                'ideal_value' => '-18 a -24 dBm',
                'active'      => true,
            ],
            [
                'name'        => 'Potencia óptica cliente corporativo',
                'type'        => 'power',
                'ideal_value' => '-14 a -20 dBm',
                'active'      => true,
            ],
            [
                'name'        => 'Organización de cables en caja',
                'type'        => 'organization',
                'ideal_value' => 'Sin cruces, curvaturas ≥ 30 mm, etiquetado legible',
                'active'      => true,
            ],
            [
                'name'        => 'Raqueta / bandeja de empalme',
                'type'        => 'raqueta',
                'ideal_value' => 'Empalmes asegurados, tapa cerrada, sin suciedad',
                'active'      => true,
            ],
            [
                'name'        => 'Sellado y hermeticidad de caja',
                'type'        => 'other',
                'ideal_value' => 'Caja cerrada con tornillos, sin evidencia de humedad',
                'active'      => true,
            ],
        ];

        foreach ($standards as $std) {
            DB::table('talento_construction_standards')->updateOrInsert(
                ['name' => $std['name']],
                array_merge($std, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
