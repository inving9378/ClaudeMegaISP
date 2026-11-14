<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 2 (§2.3 y §2.6, decisión F2-1 de Henry) — 2 plantillas NUEVAS del motor Video MVM:
 * «Guion por escenas» en Reel (9:16) y Video (16:9). Sus escenas no vienen de la plantilla sino del guion de la
 * campaña (`schema.escenas_desde_variables = true`, ver VideoTemplateRenderer). Las plantillas existentes no se tocan.
 *
 * Catálogo de Meganet (`company_id = 1`). Idempotente: `updateOrInsert` por (company_id, slug).
 * `down()` las borra SOLO si ningún contenido generado las usa (si ya hay videos producidos con ellas, se quedan).
 */
return new class extends Migration
{
    private const TABLA = 'marketing_video_templates';

    private const PLANTILLAS = [
        ['slug' => 'guion-escenas-9-16', 'name' => 'Guion por escenas — Reel (9:16)', 'formato' => '9:16', 'duracion' => 30],
        ['slug' => 'guion-escenas-16-9', 'name' => 'Guion por escenas — Video (16:9)', 'formato' => '16:9', 'duracion' => 60],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        $ahora = now();

        foreach (self::PLANTILLAS as $p) {
            $schema = [
                'engine_version'          => '1.1',
                'escenas_desde_variables' => true,
                'scenes'                  => [],
                'voiceover_script'        => '{{voiceover_text}}',
                'audio'                   => [
                    'voiceover'        => ['enabled' => true, 'volume' => 1],
                    'background_music' => ['volume' => 0.18],
                ],
                'variables'               => [
                    ['key' => 'voiceover_text', 'label' => 'Narración', 'required' => false],
                ],
                'supported_aspect_ratios' => [$p['formato']],
            ];

            $existe = DB::table(self::TABLA)->where('company_id', 1)->where('slug', $p['slug'])->exists();

            $valores = [
                'name'                     => $p['name'],
                'description'              => 'Solo desde Campañas 360: las escenas, la voz y la música vienen del guion de la campaña.',
                'category'                 => 'guion_escenas',
                'aspect_ratios'            => json_encode([$p['formato']]),
                'duration_seconds'         => $p['duracion'],
                'schema'                   => json_encode($schema, JSON_UNESCAPED_UNICODE),
                'engine_version'           => '1.1',
                'requires_voiceover'       => 1,
                'active'                   => 1,
                'deleted_at'               => null,
                'updated_at'               => $ahora,
            ];

            if (! $existe) {
                $valores['created_at'] = $ahora;
            }

            DB::table(self::TABLA)->updateOrInsert(['company_id' => 1, 'slug' => $p['slug']], $valores);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        foreach (self::PLANTILLAS as $p) {
            $id = DB::table(self::TABLA)->where('company_id', 1)->where('slug', $p['slug'])->value('id');

            if (! $id) {
                continue;
            }

            $enUso = Schema::hasTable('marketing_generated_content')
                && DB::table('marketing_generated_content')->where('template_id', $id)->exists();

            if (! $enUso) {
                DB::table(self::TABLA)->where('id', $id)->delete();
            }
        }
    }
};
