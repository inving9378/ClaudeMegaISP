<?php

namespace Database\Seeders;

use App\Models\Marketing\PublicationChannel;
use Illuminate\Database\Seeder;

class MarketingPublicationChannelsSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [
                'platform'               => 'facebook',
                'channel_type'           => 'page_feed',
                'name'                   => 'Facebook Page Feed',
                'slug'                   => 'fb-page-feed',
                'supported_aspect_ratios'=> ['1:1', '16:9', '9:16'],
                'max_duration_seconds'   => 240,
                'max_file_size_mb'       => 4096,
                'credentials_status_message' => 'Conecta tu cuenta Meta en /marketing/publishing/setup',
            ],
            [
                'platform'               => 'instagram',
                'channel_type'           => 'reels',
                'name'                   => 'Instagram Reels',
                'slug'                   => 'ig-reels',
                'supported_aspect_ratios'=> ['9:16'],
                'max_duration_seconds'   => 90,
                'max_file_size_mb'       => 1000,
                'credentials_status_message' => 'Conecta tu cuenta Meta en /marketing/publishing/setup',
            ],
            [
                'platform'               => 'instagram',
                'channel_type'           => 'feed_square',
                'name'                   => 'Instagram Feed',
                'slug'                   => 'ig-feed',
                'supported_aspect_ratios'=> ['1:1', '4:5'],
                'max_duration_seconds'   => 60,
                'max_file_size_mb'       => 250,
                'credentials_status_message' => 'Conecta tu cuenta Meta en /marketing/publishing/setup',
            ],
            [
                'platform'               => 'instagram',
                'channel_type'           => 'stories',
                'name'                   => 'Instagram Stories',
                'slug'                   => 'ig-stories',
                'supported_aspect_ratios'=> ['9:16'],
                'max_duration_seconds'   => 60,
                'max_file_size_mb'       => 250,
                'credentials_status_message' => 'Conecta tu cuenta Meta en /marketing/publishing/setup',
            ],
            [
                'platform'               => 'whatsapp',
                'channel_type'           => 'status',
                'name'                   => 'WhatsApp Status',
                'slug'                   => 'whatsapp-status',
                'supported_aspect_ratios'=> ['9:16'],
                'max_duration_seconds'   => 30,
                'max_file_size_mb'       => 64,
                'credentials_status_message' => 'Se usará Evolution API si está conectada',
            ],
            [
                'platform'               => 'email',
                'channel_type'           => 'blast',
                'name'                   => 'Email Blast',
                'slug'                   => 'email-blast',
                'supported_aspect_ratios'=> ['1:1', '16:9', '9:16'],
                'max_duration_seconds'   => 300,
                'max_file_size_mb'       => 50,
                'credentials_status_message' => 'Configura MAIL_HOST en .env',
            ],
        ];

        foreach ($channels as $data) {
            PublicationChannel::firstOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['company_id' => 1, 'active' => true, 'credentials_ready' => false])
            );
        }
    }
}
