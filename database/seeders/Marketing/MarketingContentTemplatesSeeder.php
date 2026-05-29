<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\ContentTemplate;
use Illuminate\Database\Seeder;

class MarketingContentTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Bienvenida WhatsApp',
                'category' => 'copy_dm',
                'tone' => 'amigable',
                'template_text' => "¡Hola {{nombre}}! Bienvenido a {{empresa}}. ¿En qué te podemos ayudar hoy?",
                'variables' => ['nombre', 'empresa'],
            ],
            [
                'name' => 'Recordatorio de cotización',
                'category' => 'copy_dm',
                'tone' => 'profesional',
                'template_text' => "Estimado {{nombre}}, le recordamos que su cotización para el plan {{plan}} sigue disponible. ¿Tiene alguna duda?",
                'variables' => ['nombre', 'plan'],
            ],
            [
                'name' => 'Promoción flash 24h',
                'category' => 'copy_post',
                'tone' => 'urgente',
                'template_text' => "¡SOLO POR HOY! {{descuento}}% de descuento en {{plan}}. ¡No te lo pierdas! Válido hasta {{fecha_fin}}.",
                'variables' => ['descuento', 'plan', 'fecha_fin'],
            ],
            [
                'name' => 'Anuncio de cobertura nueva',
                'category' => 'copy_post',
                'tone' => 'profesional',
                'template_text' => "¡Tenemos cobertura en {{colonia}}! Ahora puedes disfrutar de internet de alta velocidad en tu zona. Contáctanos.",
                'variables' => ['colonia'],
            ],
            [
                'name' => 'Testimonio cliente',
                'category' => 'copy_post',
                'tone' => 'amigable',
                'template_text' => '"{{testimonio}}" — {{nombre_cliente}}, cliente desde {{fecha}}.',
                'variables' => ['testimonio', 'nombre_cliente', 'fecha'],
            ],
        ];

        foreach ($templates as $tpl) {
            ContentTemplate::firstOrCreate(
                ['company_id' => 1, 'name' => $tpl['name']],
                array_merge($tpl, ['company_id' => 1, 'use_ai' => true, 'active' => true])
            );
        }
    }
}
