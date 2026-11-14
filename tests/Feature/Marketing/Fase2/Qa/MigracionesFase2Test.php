<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — las 4 migraciones (contrato §2.3; criterio 13).
 * Columnas, plantillas y permisos tras migrar; `up()` dos veces deja todo igual (idempotente, sin DDL la segunda
 * vez: si una guarda faltara, la prueba truena aquí y no en producción); `down()` de las plantillas NO borra si hay
 * videos que las usan. El rollback real con DDL (`rollback --step=4` + re-migrate) se prueba aparte en
 * `megaisp_dryrun` (skill pasos 2a/2b), nunca dentro de una transacción de prueba (el DDL hace commit implícito).
 */
class MigracionesFase2Test extends MarketingTestCase
{
    private const DIR = 'app/Modules/Addons/Marketing/migrations/';
    private const MIGRACIONES = [
        '2026_11_13_200000_agrega_campos_360_a_marketing_campaigns',
        '2026_11_13_200100_agrega_pieza_a_marketing_generated_content',
        '2026_11_13_200200_crea_plantillas_guion_por_escenas',
        '2026_11_13_200300_asegura_permisos_motor_video',
    ];

    private function migracion(string $nombre): object
    {
        return require base_path(self::DIR . $nombre . '.php');
    }

    private function foto(): array
    {
        return [
            'campaigns' => Schema::getColumnListing('marketing_campaigns'),
            'gc' => Schema::getColumnListing('marketing_generated_content'),
            'idx' => DB::table('information_schema.statistics')->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'marketing_generated_content')->orderBy('index_name')->orderBy('seq_in_index')
                ->selectRaw('index_name as i, column_name as c')->get()->map(fn ($r) => $r->i . ':' . $r->c)->all(),
            'plantillas' => DB::table('marketing_video_templates')->where('category', 'guion_escenas')->orderBy('slug')
                ->get(['id', 'slug', 'name', 'category', 'aspect_ratios', 'duration_seconds', 'schema', 'engine_version', 'requires_voiceover', 'active'])
                ->map(fn ($r) => (array) $r)->all(),
            'permisos' => Permission::query()->whereIn('name', ['view-video-templates', 'view-video-content', 'generate-video-content', 'delete-video-content', 'download-video'])
                ->orderBy('name')->get(['id', 'name'])->map(fn ($p) => $p->id . ':' . $p->name)->all(),
            'role_perm' => DB::table('role_has_permissions')->count(),
        ];
    }

    public function test_las_4_migraciones_existen_y_estan_aplicadas(): void
    {
        foreach (self::MIGRACIONES as $m) {
            $this->assertFileExists(base_path(self::DIR . $m . '.php'));
            $this->assertTrue(DB::table('migrations')->where('migration', $m)->exists(), "{$m} no está aplicada en megaisp_test");
        }
    }

    public function test_columnas_nuevas_con_tipo_y_nulidad(): void
    {
        $tipos = function (string $tabla): array {
            $o = [];
            foreach (DB::select("SHOW COLUMNS FROM {$tabla}") as $c) {
                $o[$c->Field] = [strtolower($c->Type), $c->Null, $c->Default];
            }

            return $o;
        };
        $c = $tipos('marketing_campaigns');
        $this->assertSame('varchar(40)', $c['objetivo'][0]);
        foreach (['publico', 'oferta', 'canales', 'estrategia'] as $j) {
            $this->assertContains($c[$j][0], ['json', 'longtext'], "{$j} json");
        }
        $this->assertSame('varchar(20)', $c['estrategia_estado'][0]);
        $this->assertSame('varchar(500)', $c['estrategia_error'][0]);
        $this->assertSame('timestamp', $c['estrategia_generada_at'][0]);
        $this->assertStringStartsWith('bigint', $c['creado_por_user_id'][0]);
        $this->assertStringContainsString('unsigned', $c['creado_por_user_id'][0]);
        foreach (['objetivo', 'publico', 'oferta', 'canales', 'estrategia', 'estrategia_estado', 'estrategia_error', 'estrategia_generada_at', 'creado_por_user_id'] as $col) {
            $this->assertSame('YES', $c[$col][1], "marketing_campaigns.{$col} debe ser nula (aditiva)");
        }
        $this->assertSame('decimal(12,2)', $c['budget'][0], 'budget sigue en decimal(12,2)');

        $g = $tipos('marketing_generated_content');
        $this->assertSame('varchar(30)', $g['pieza'][0]);
        $this->assertSame('varchar(2)', $g['variante'][0]);
        $this->assertContains($g['contenido'][0], ['json', 'longtext']);
        $this->assertStringStartsWith('bigint', $g['pieza_origen_id'][0]);
        $this->assertSame('timestamp', $g['editado_at'][0]);
        $this->assertStringStartsWith('smallint', $g['regeneraciones'][0]);
        $this->assertSame('0', (string) $g['regeneraciones'][2]);
        foreach (['pieza', 'variante', 'contenido', 'pieza_origen_id', 'editado_at'] as $col) {
            $this->assertSame('YES', $g[$col][1], "marketing_generated_content.{$col} nula");
        }
        $idx = $this->foto()['idx'];
        $this->assertContains('mkt_gc_campana_pieza_idx:source_campaign_id', $idx);
        $this->assertContains('mkt_gc_campana_pieza_idx:pieza', $idx);
        $this->assertContains('mkt_gc_pieza_origen_idx:pieza_origen_id', $idx);
    }

    public function test_permisos_del_motor_existen(): void
    {
        $this->assertCount(5, $this->foto()['permisos']);
    }

    public function test_up_dos_veces_deja_todo_igual(): void
    {
        $antes = $this->foto();
        foreach (self::MIGRACIONES as $m) {
            $this->migracion($m)->up();
        }
        foreach (self::MIGRACIONES as $m) {
            $this->migracion($m)->up();
        }
        $despues = $this->foto();
        $this->assertSame($antes['campaigns'], $despues['campaigns']);
        $this->assertSame($antes['gc'], $despues['gc']);
        $this->assertSame($antes['idx'], $despues['idx']);
        $this->assertSame($antes['permisos'], $despues['permisos']);
        $this->assertSame($antes['role_perm'], $despues['role_perm'], 'Re-correr no reparte permisos (ya existían)');
        $this->assertSame(array_column($antes['plantillas'], 'id'), array_column($despues['plantillas'], 'id'), 'Mismas plantillas, sin duplicar');
        $this->assertSame($antes['plantillas'], $despues['plantillas']);
    }

    public function test_up_no_toca_las_plantillas_existentes_de_otras_categorias(): void
    {
        $id = DB::table('marketing_video_templates')->insertGetId([
            'company_id' => 1, 'name' => 'QA2 genérica', 'slug' => 'qa2-generica-' . uniqid(), 'description' => 'QA', 'category' => 'generic',
            'aspect_ratios' => '["9:16"]', 'duration_seconds' => 20, 'schema' => '{"scenes":[]}', 'engine_version' => '1.0',
            'requires_voiceover' => 0, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $antes = (array) DB::table('marketing_video_templates')->find($id);
        $this->migracion(self::MIGRACIONES[2])->up();
        $this->assertSame($antes, (array) DB::table('marketing_video_templates')->find($id));
    }

    public function test_down_de_plantillas_no_borra_si_hay_videos_que_las_usan(): void
    {
        $p916 = DB::table('marketing_video_templates')->where('company_id', 1)->where('slug', 'guion-escenas-9-16')->value('id');
        $p169 = DB::table('marketing_video_templates')->where('company_id', 1)->where('slug', 'guion-escenas-16-9')->value('id');
        DB::table('marketing_generated_content')->insert([
            'company_id' => 1, 'type' => 'video', 'template_id' => $p916, 'status' => 'ready', 'generation_engine' => 'mvm_ffmpeg',
            'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketing_generated_content')->where('template_id', $p169)->update(['template_id' => null]);
        $this->migracion(self::MIGRACIONES[2])->down();
        $this->assertTrue(DB::table('marketing_video_templates')->where('id', $p916)->exists(), 'Con videos que la usan, down() no la borra');
        $this->assertFalse(DB::table('marketing_video_templates')->where('id', $p169)->exists(), 'Sin uso, down() la borra');
    }

    public function test_down_de_permisos_esta_vacio(): void
    {
        $antes = $this->foto()['permisos'];
        $this->migracion(self::MIGRACIONES[3])->down();
        $this->assertSame($antes, $this->foto()['permisos']);
    }

    public function test_las_migraciones_son_aditivas_sin_drop_en_up(): void
    {
        foreach (self::MIGRACIONES as $m) {
            $src = (string) file_get_contents(base_path(self::DIR . $m . '.php'));
            $up = substr($src, strpos($src, 'function up'), strpos($src, 'function down') - strpos($src, 'function up'));
            $this->assertDoesNotMatchRegularExpression('/drop|->change\(|truncate|DB::statement|syncPermissions|syncRoles|delete\(/i', $up, "{$m}: algo destructivo en up()");
            $this->assertStringNotContainsString('migrate:fresh', $src);
        }
    }
}
