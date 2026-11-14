<?php
// QA · Fase 2 — casos del motor que deben dar las MISMAS escenas que en la base (criterio 12).
// Los usa MotorEscenasTest y el script que capturó fixtures/escenas-base.json con el código de 08d58e7e3.
return [
    'generica_9_16' => ['generic_9_16', [
        'headline' => 'Fibra QA', 'subheadline' => 'Sub QA', 'body_text' => 'Cuerpo de prueba con acentos: añadir', 'cta_text' => 'Llama hoy',
        'voiceover_text' => 'Narración de prueba',
    ], '9:16'],
    'generica_16_9' => ['generic_16_9', ['headline' => 'Fibra QA 16', 'voiceover_text' => 'Narración 16'], '16:9'],
    'nicho_gamer_9_16' => ['multivariant-plan-promo-gamer', [
        'plan_name' => 'Plan QA', 'plan_speed' => '500 MEGAS', 'plan_price' => '599', 'phone_cta' => '55 0000 0000',
        '_creative_brief' => ['hook' => 'Sin lag', 'cta' => 'Contrata', 'voiceover_script' => 'Juega sin lag',
            'kinetic_text_segments' => [['text' => 'SIN LAG', 'duration_sec' => 1.5, 'emphasis' => 'high']], 'niche_slug' => 'gamer'],
        '_niche_slug' => 'gamer',
    ], '9:16'],
    'nicho_gamer_16_9' => ['multivariant-plan-promo-gamer', [
        'plan_name' => 'Plan QA', '_creative_brief' => ['hook' => 'Sin lag', 'cta' => 'Contrata', 'niche_slug' => 'gamer'], '_niche_slug' => 'gamer',
    ], '16:9'],
];
