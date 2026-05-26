<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Channel;
use Illuminate\Database\Seeder;

class MarketingChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['code' => 'whatsapp',  'name' => 'WhatsApp Business',    'order' => 1],
            ['code' => 'facebook',  'name' => 'Facebook Page',         'order' => 2],
            ['code' => 'instagram', 'name' => 'Instagram Business',    'order' => 3],
            ['code' => 'email',     'name' => 'Correo electrónico',    'order' => 4],
            ['code' => 'sms',       'name' => 'SMS',                   'order' => 5],
            ['code' => 'voice',     'name' => 'Voz (Asterisk)',        'order' => 6],
        ];

        foreach ($channels as $channel) {
            Channel::firstOrCreate(
                ['company_id' => 1, 'code' => $channel['code']],
                array_merge($channel, ['company_id' => 1, 'active' => true, 'config' => null])
            );
        }
    }
}
