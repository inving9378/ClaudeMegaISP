<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\VideoTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — las 4 migraciones (§2.3): columnas, plantillas «Guion por escenas» y permisos del motor tras
 * migrar; `up()` otra vez no cambia nada; el `down()` de plantillas no borra una plantilla con videos.
 * Solo se vuelven a correr pasos sin DDL (las guardas lo evitan): el rollback real se probó con
 * `migrate:rollback --step=4` y re-`migrate` sobre megaisp_test.
 */
class MigracionesFase2BackendTest extends MarketingTestCase
{
    private const DIR = 'app/Modules/Addons/Marketing/migrations/';

    private function migracion(string $archivo): object
    {
        return require base_path(self::DIR . $archivo);
    }

    public function test_columnas_nuevas(): void
    {
        $this->assertTrue(Schema::hasColumns('marketing_campaigns', [
            'objetivo', 'publico', 'oferta', 'canales', 'estrategia', 'estrategia_estado', 'estrategia_error',
            'estrategia_generada_at', 'creado_por_user_id',
        ]));
        $this->assertTrue(Schema::hasColumns('marketing_generated_content', [
            'pieza', 'variante', 'contenido', 'pieza_origen_id', 'editado_at', 'regeneraciones',
        ]));

        $indices = collect(DB::select('SHOW INDEX FROM marketing_generated_content'))->pluck('Key_name')->unique()->all();
        $this->assertContains('mkt_gc_campana_pieza_idx', $indices);
        $this->assertContains('mkt_gc_pieza_origen_idx', $indices);

        $regen = collect(DB::select("SHOW COLUMNS FROM marketing_generated_content WHERE Field = 'regeneraciones'"))->first();
        $this->assertSame('0', (string) $regen->Default);
    }

    public function test_plantillas_guion_por_escenas(): void
    {
        foreach (['guion-escenas-9-16' => ['9:16', 30], 'guion-escenas-16-9' => ['16:9', 60]] as $slug => [$formato, $dur]) {
            $t = VideoTemplate::query()->where('company_id', 1)->where('slug', $slug)->firstOrFail();
            $this->assertSame('guion_escenas', $t->category);
            $this->assertSame([$formato], $t->aspect_ratios);
            $this->assertSame($dur, (int) $t->duration_seconds);
            $this->assertTrue($t->active);
            $this->assertTrue($t->requires_voiceover);
            $this->assertSame('1.1', $t->engine_version);
            $this->assertTrue($t->schema['escenas_desde_variables']);
            $this->assertSame([], $t->schema['scenes']);
            $this->assertSame('{{voiceover_text}}', $t->schema['voiceover_script']);
            $this->assertSame([$formato], $t->schema['supported_aspect_ratios']);
        }
    }

    public function test_permisos_del_motor_existen(): void
    {
        foreach (['view-video-templates', 'view-video-content', 'generate-video-content', 'delete-video-content', 'download-video'] as $p) {
            $this->assertTrue(Permission::query()->where('name', $p)->where('guard_name', 'web')->exists(), $p);
        }
    }

    public function test_up_dos_veces_no_cambia_nada(): void
    {
        $antes = [
            'plantillas' => VideoTemplate::query()->where('category', 'guion_escenas')->orderBy('id')->get(['id', 'slug', 'schema'])->toArray(),
            'permisos'   => Permission::query()->count(),
            'roles'      => DB::table('role_has_permissions')->count(),
        ];

        foreach ([
            '2026_11_13_200000_agrega_campos_360_a_marketing_campaigns.php',
            '2026_11_13_200100_agrega_pieza_a_marketing_generated_content.php',
            '2026_11_13_200200_crea_plantillas_guion_por_escenas.php',
            '2026_11_13_200300_asegura_permisos_motor_video.php',
        ] as $archivo) {
            $this->migracion($archivo)->up();
        }

        $this->assertSame($antes, [
            'plantillas' => VideoTemplate::query()->where('category', 'guion_escenas')->orderBy('id')->get(['id', 'slug', 'schema'])->toArray(),
            'permisos'   => Permission::query()->count(),
            'roles'      => DB::table('role_has_permissions')->count(),
        ]);
    }

    public function test_up_revive_una_plantilla_borrada_o_desactivada(): void
    {
        VideoTemplate::query()->where('slug', 'guion-escenas-9-16')->update(['active' => false]);
        VideoTemplate::query()->where('slug', 'guion-escenas-16-9')->delete();

        $this->migracion('2026_11_13_200200_crea_plantillas_guion_por_escenas.php')->up();

        $this->assertSame(2, VideoTemplate::query()->where('category', 'guion_escenas')->where('active', true)->count());
    }

    public function test_down_de_plantillas_respeta_las_que_tienen_videos(): void
    {
        $usada = VideoTemplate::query()->where('slug', 'guion-escenas-9-16')->firstOrFail();
        $g = new GeneratedContent();
        $g->forceFill(['company_id' => 1, 'type' => 'video', 'status' => 'ready', 'template_id' => $usada->id,
            'template_type' => VideoTemplate::class, 'generation_engine' => 'mvm_ffmpeg'])->save();

        $this->migracion('2026_11_13_200200_crea_plantillas_guion_por_escenas.php')->down();

        $this->assertTrue(DB::table('marketing_video_templates')->where('slug', 'guion-escenas-9-16')->exists(), 'con videos se queda');
        $this->assertFalse(DB::table('marketing_video_templates')->where('slug', 'guion-escenas-16-9')->exists(), 'sin videos se borra');
    }
}
