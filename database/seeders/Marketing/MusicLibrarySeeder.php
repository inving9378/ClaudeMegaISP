<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\Asset;
use Illuminate\Database\Seeder;

class MusicLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $tracks = [
            'epic_electronic'    => ['niche' => 'gamer', 'name' => 'Epic Electronic'],
            'uplifting_family'   => ['niche' => 'familia', 'name' => 'Uplifting Family'],
            'corporate_clean'    => ['niche' => 'home_office', 'name' => 'Corporate Clean'],
            'modern_creative'    => ['niche' => 'streamer', 'name' => 'Modern Creative'],
            'corporate_confident'=> ['niche' => 'pequeno_negocio', 'name' => 'Corporate Confident'],
            'warm_acoustic'      => ['niche' => 'adulto_mayor', 'name' => 'Warm Acoustic'],
        ];

        foreach ($tracks as $mood => $info) {
            $path = "marketing/assets/music/{$mood}.mp3";
            $abs  = storage_path("app/{$path}");

            Asset::updateOrCreate(
                ['company_id' => 1, 'type' => 'music', 'category' => $mood],
                [
                    'name'       => "Music: {$info['name']}",
                    'path'       => $path,
                    'mime_type'  => 'audio/mpeg',
                    'source'     => 'internal',
                    'tags'       => [$mood, $info['niche']],
                    'license'    => 'cc0',
                    'size_bytes' => file_exists($abs) ? filesize($abs) : 0,
                    'metadata'   => ['mood' => $mood, 'placeholder' => !file_exists($abs) || filesize($abs) < 50000],
                ]
            );
        }
    }
}
