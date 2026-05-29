<?php

namespace Database\Seeders;

use App\Models\Referrals\ReferralNotificationTemplate;
use Illuminate\Database\Seeder;

class ReferralNotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'event_type'    => 'prospect_converted',
                'label'         => 'Prospecto convertido a cliente',
                'body_template' => '¡Hola {{embajador_name}}! Tu prospecto {{prospect_name}} acaba de contratar Meganet. Cuando complete sus primeros $1,500 en pagos comenzarás a ganar comisiones. ¡Gracias por compartir!',
            ],
            [
                'event_type'    => 'threshold_covered',
                'label'         => 'Referido cruzó umbral — comisiones activadas',
                'body_template' => '¡Hola {{embajador_name}}! Tu referido {{referido_name}} ya completó los pagos requeridos. A partir de ahora ganarás comisión por cada pago que realice durante los próximos 12 meses.',
            ],
            [
                'event_type'    => 'commission_generated',
                'label'         => 'Comisión generada por pago de referido',
                'body_template' => '¡Hola {{embajador_name}}! Ganaste ${{amount}} de comisión por el pago de {{referido_name}}. Se acreditará a tu saldo en {{days_until_apply}} días, siempre que no haya reversos.',
            ],
            [
                'event_type'    => 'commissions_applied',
                'label'         => 'Comisiones acreditadas al saldo',
                'body_template' => '¡Hola {{embajador_name}}! Se acreditaron ${{total_amount}} a tu saldo por {{count}} comisión(es) del programa Embajadores. Consulta tu cuenta para más detalle.',
            ],
            [
                'event_type'    => 'reward_expiring_soon',
                'label'         => 'Mes gratis próximo a vencer',
                'body_template' => '¡Hola {{embajador_name}}! Tu mes gratis vence en {{days_remaining}} días. Contáctanos para canjearlo antes de que expire.',
            ],
        ];

        foreach ($templates as $data) {
            ReferralNotificationTemplate::updateOrCreate(
                ['event_type' => $data['event_type']],
                array_merge($data, ['channel' => 'whatsapp', 'active' => true]),
            );
        }
    }
}
