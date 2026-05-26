<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\LeadSource;
use Illuminate\Database\Seeder;

class MarketingLeadSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['code' => 'meta_ads',          'name' => 'Meta Ads (FB/IG)',    'is_paid' => true,  'color' => '#1877F2', 'icon' => 'fab fa-facebook'],
            ['code' => 'google_ads',         'name' => 'Google Ads',          'is_paid' => true,  'color' => '#4285F4', 'icon' => 'fab fa-google'],
            ['code' => 'web_form',           'name' => 'Formulario web',      'is_paid' => false, 'color' => '#28A745', 'icon' => 'fas fa-globe'],
            ['code' => 'organic_facebook',   'name' => 'Orgánico Facebook',   'is_paid' => false, 'color' => '#1877F2', 'icon' => 'fab fa-facebook'],
            ['code' => 'organic_instagram',  'name' => 'Orgánico Instagram',  'is_paid' => false, 'color' => '#E4405F', 'icon' => 'fab fa-instagram'],
            ['code' => 'referral',           'name' => 'Referido',            'is_paid' => false, 'color' => '#FFC107', 'icon' => 'fas fa-user-friends'],
            ['code' => 'walk_in',            'name' => 'Visita en oficina',   'is_paid' => false, 'color' => '#6C757D', 'icon' => 'fas fa-walking'],
            ['code' => 'whatsapp_direct',    'name' => 'WhatsApp directo',    'is_paid' => false, 'color' => '#25D366', 'icon' => 'fab fa-whatsapp'],
        ];

        foreach ($sources as $source) {
            LeadSource::firstOrCreate(
                ['company_id' => 1, 'code' => $source['code']],
                array_merge($source, ['company_id' => 1, 'active' => true])
            );
        }
    }
}
