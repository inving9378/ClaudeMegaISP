<?php

namespace App\Modules\Addons\Marketing\Services\Personalization;

use App\Models\Marketing\MarketingNiche;

class CustomerProfilerService
{
    public function profileLead($lead): array
    {
        $niche = $this->guessNicheFromBasicData($lead);

        return [
            'detected_niche'       => $niche,
            'confidence'           => 0.3,
            'data_richness'        => $this->assessDataRichness($lead),
            'recommended_strategy' => 'use_multivariant',
        ];
    }

    protected function guessNicheFromBasicData($lead): string
    {
        try {
            $messages = $lead->conversations->flatMap->messages->pluck('content')->implode(' ');
        } catch (\Throwable) {
            return 'familia';
        }

        $keywords = [
            'gamer'          => ['gaming', 'juego', 'ps5', 'xbox', 'steam', 'twitch', 'fortnite', 'valorant'],
            'streamer'        => ['stream', 'youtube', 'tiktok', 'obs', 'contenido', 'subo videos'],
            'home_office'     => ['trabajo', 'remoto', 'zoom', 'teams', 'reuniones', 'oficina'],
            'familia'         => ['familia', 'hijos', 'niños', 'netflix', 'smart tv'],
            'pequeno_negocio' => ['negocio', 'empresa', 'tpv', 'clientes', 'cámaras'],
            'adulto_mayor'    => ['nietos', 'soporte', 'sencillo', 'no entiendo', 'no sé usar'],
        ];

        $scores     = [];
        $msgsLower  = strtolower($messages);
        foreach ($keywords as $niche => $words) {
            $scores[$niche] = 0;
            foreach ($words as $word) {
                if (str_contains($msgsLower, $word)) {
                    $scores[$niche]++;
                }
            }
        }

        $maxScore = max($scores);
        $maxNiche = array_keys($scores, $maxScore)[0];

        return $maxScore > 0 ? $maxNiche : 'familia';
    }

    protected function assessDataRichness($lead): string
    {
        $signals = 0;
        try {
            if ($lead->conversations->isNotEmpty()) {
                $signals++;
            }
            if ($lead->conversations->sum(fn($c) => $c->messages->count()) > 5) {
                $signals++;
            }
        } catch (\Throwable) {}

        if (!empty($lead->plan_interest_id)) {
            $signals++;
        }
        if (!empty($lead->lead_score)) {
            $signals++;
        }

        return match (true) {
            $signals >= 3 => 'rich',
            $signals >= 1 => 'medium',
            default       => 'poor',
        };
    }
}
