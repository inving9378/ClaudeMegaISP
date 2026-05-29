<?php

namespace Database\Seeders\Marketing;

use App\Models\Marketing\VideoTemplate;
use Illuminate\Database\Seeder;

class AllVideoTemplatesSeeder extends Seeder
{
    private const TEMPLATES = [
        ['slug_base' => 'coverage_announcement', 'category' => 'coverage_announcement', 'ratios' => [['9:16', 65], ['1:1', 55], ['16:9', 70]]],
        ['slug_base' => 'testimonial',            'category' => 'testimonial',            'ratios' => [['9:16', 55], ['1:1', 50], ['16:9', 60]]],
        ['slug_base' => 'payment_reminder',       'category' => 'payment_reminder',       'ratios' => [['9:16', 45], ['1:1', 40], ['16:9', 50]]],
        ['slug_base' => 'welcome',                'category' => 'welcome',                'ratios' => [['9:16', 60], ['1:1', 55], ['16:9', 65]]],
        ['slug_base' => 'flash_promo',            'category' => 'flash_promo',            'ratios' => [['9:16', 55], ['1:1', 50], ['16:9', 60]]],
        ['slug_base' => 'maintenance_notice',     'category' => 'maintenance_notice',     'ratios' => [['9:16', 45], ['1:1', 40], ['16:9', 50]]],
        ['slug_base' => 'generic',                'category' => 'generic',                'ratios' => [['9:16', 40], ['1:1', 35], ['16:9', 45]]],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $tpl) {
            foreach ($tpl['ratios'] as [$ratio, $estimatedSec]) {
                $ratioSlug = str_replace(':', '_', $ratio);
                $slug      = $tpl['slug_base'] . '_' . $ratioSlug;
                $jsonPath  = storage_path("app/marketing/templates/{$slug}.json");

                $schema = file_exists($jsonPath)
                    ? json_decode(file_get_contents($jsonPath), true)
                    : [];

                VideoTemplate::updateOrCreate(
                    ['slug' => $slug, 'company_id' => 1],
                    [
                        'company_id'               => 1,
                        'name'                     => $schema['name']        ?? $slug,
                        'description'              => $schema['description'] ?? null,
                        'category'                 => $tpl['category'],
                        'aspect_ratios'            => [$ratio],
                        'duration_seconds'         => $schema['duration_seconds'] ?? 20,
                        'schema'                   => $schema,
                        'preview_thumbnail'        => null,
                        'active'                   => true,
                        'engine_version'           => $schema['engine_version']     ?? '1.0',
                        'requires_voiceover'       => $schema['requires_voiceover'] ?? true,
                        'estimated_render_seconds' => $estimatedSec,
                        'default_variables'        => [],
                    ]
                );
            }
        }
    }
}
