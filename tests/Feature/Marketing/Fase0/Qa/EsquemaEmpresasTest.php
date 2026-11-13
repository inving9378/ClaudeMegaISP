<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — esquema de §2.1 + criterios 2 y 3 (semilla y permiso).
 */
class EsquemaEmpresasTest extends MarketingTestCase
{
    /** @return array<string, object> columnas por nombre */
    private function columnas(string $tabla): array
    {
        $rows = DB::select(
            'select COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n, COLUMN_DEFAULT d, EXTRA e, COLLATION_NAME col
               from information_schema.columns where table_schema = database() and table_name = ? order by ORDINAL_POSITION',
            [$tabla]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r->c] = $r;
        }

        return $out;
    }

    private function indicesUnicos(string $tabla): array
    {
        $rows = DB::select(
            "select INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME order by SEQ_IN_INDEX) c, max(NON_UNIQUE) nu
               from information_schema.statistics where table_schema = database() and table_name = ?
              group by INDEX_NAME",
            [$tabla]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r->i] = ['cols' => $r->c, 'unico' => (int) $r->nu === 0];
        }

        return $out;
    }

    private function tieneIndice(string $tabla, string $cols, bool $unico): bool
    {
        foreach ($this->indicesUnicos($tabla) as $nombre => $idx) {
            if ($nombre !== 'PRIMARY' && $idx['cols'] === $cols && $idx['unico'] === $unico) {
                return true;
            }
        }

        return false;
    }

    private function fk(string $tabla, string $columna): ?object
    {
        return DB::selectOne(
            'select k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE dr
               from information_schema.KEY_COLUMN_USAGE k
               join information_schema.REFERENTIAL_CONSTRAINTS r
                 on r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              where k.TABLE_SCHEMA = database() and k.TABLE_NAME = ? and k.COLUMN_NAME = ? and k.REFERENCED_TABLE_NAME is not null',
            [$tabla, $columna]
        );
    }

    // ── marketing_empresas ───────────────────────────────────────────────────

    public function test_marketing_empresas_tiene_las_columnas_y_tipos_del_contrato(): void
    {
        $c = $this->columnas('marketing_empresas');
        $this->assertNotEmpty($c, 'No existe marketing_empresas en megaisp_test');

        $this->assertSame('bigint unsigned', $c['id']->t);
        $this->assertStringContainsString('auto_increment', $c['id']->e);
        $this->assertSame('varchar(120)', $c['nombre']->t);
        $this->assertSame('NO', $c['nombre']->n);
        $this->assertSame('varchar(60)', $c['slug']->t);
        $this->assertSame('NO', $c['slug']->n);
        $this->assertSame('varchar(7)', $c['color_primario']->t);
        $this->assertSame('YES', $c['color_primario']->n);
        $this->assertSame('varchar(7)', $c['color_secundario']->t);
        $this->assertSame('YES', $c['color_secundario']->n);
        $this->assertSame('tinyint(1)', $c['activo']->t);
        $this->assertSame('1', (string) $c['activo']->d);
        foreach (['created_by', 'updated_by'] as $col) {
            $this->assertSame('bigint unsigned', $c[$col]->t, $col);
            $this->assertSame('YES', $c[$col]->n, $col);
        }
        $this->assertArrayHasKey('created_at', $c);
        $this->assertArrayHasKey('updated_at', $c);
    }

    public function test_id_de_empresa_es_del_mismo_tipo_que_company_id_de_las_tablas_marketing(): void
    {
        $leads = $this->columnas('marketing_leads');
        $this->assertSame($leads['company_id']->t, $this->columnas('marketing_empresas')['id']->t);
        $this->assertSame($leads['company_id']->t, $this->columnas('marketing_empresa_usuario')['company_id']->t);
    }

    public function test_slug_de_empresa_es_unico(): void
    {
        $this->assertTrue($this->tieneIndice('marketing_empresas', 'slug', true));

        $this->expectException(QueryException::class);
        DB::table('marketing_empresas')->insert([
            'nombre' => 'Duplicada QA', 'slug' => 'meganet', 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_nombre_admite_acentos_y_emojis(): void
    {
        $nombre = 'Café Ñandú 🚀 ' . Str::random(4);
        $id = DB::table('marketing_empresas')->insertGetId([
            'nombre' => $nombre, 'slug' => 'qa-utf8-' . Str::lower(Str::random(8)), 'activo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame($nombre, DB::table('marketing_empresas')->where('id', $id)->value('nombre'));
    }

    public function test_semilla_empresa_principal_meganet(): void
    {
        $e = DB::table('marketing_empresas')->where('id', 1)->first();
        $this->assertNotNull($e, 'Falta la empresa 1');
        $this->assertSame('Meganet', $e->nombre);
        $this->assertSame('meganet', $e->slug);
        $this->assertSame('#0066CC', strtoupper($e->color_primario));
        $this->assertSame('#FF6600', strtoupper($e->color_secundario));
        $this->assertSame(1, (int) $e->activo);
    }

    // ── marketing_empresa_usuario ────────────────────────────────────────────

    public function test_marketing_empresa_usuario_tiene_columnas_indices_y_fks(): void
    {
        $c = $this->columnas('marketing_empresa_usuario');
        $this->assertNotEmpty($c, 'No existe marketing_empresa_usuario');

        $this->assertSame('bigint unsigned', $c['id']->t);
        $this->assertSame('bigint unsigned', $c['company_id']->t);
        $this->assertSame('NO', $c['company_id']->n);
        $this->assertSame('bigint unsigned', $c['user_id']->t);
        $this->assertSame('NO', $c['user_id']->n);
        $this->assertSame('varchar(20)', $c['rol']->t);
        $this->assertSame('tinyint(1)', $c['activo']->t);
        $this->assertSame('1', (string) $c['activo']->d);
        $this->assertSame('YES', $c['created_by']->n);
        $this->assertSame('YES', $c['updated_by']->n);

        $this->assertTrue($this->tieneIndice('marketing_empresa_usuario', 'company_id,user_id', true), 'falta unique(company_id,user_id)');
        $this->assertTrue($this->tieneIndice('marketing_empresa_usuario', 'user_id,activo', false), 'falta index(user_id,activo)');

        $fkEmpresa = $this->fk('marketing_empresa_usuario', 'company_id');
        $this->assertNotNull($fkEmpresa);
        $this->assertSame('marketing_empresas', $fkEmpresa->rt);
        $this->assertSame('RESTRICT', $fkEmpresa->dr);

        $fkUser = $this->fk('marketing_empresa_usuario', 'user_id');
        $this->assertNotNull($fkUser);
        $this->assertSame('users', $fkUser->rt);
        $this->assertSame('CASCADE', $fkUser->dr);
    }

    public function test_no_se_puede_asignar_dos_veces_la_misma_persona_a_la_misma_empresa(): void
    {
        $u = User::factory()->create(['estado' => 'activo']);
        $fila = ['company_id' => 1, 'user_id' => $u->id, 'rol' => 'ventas', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()];
        DB::table('marketing_empresa_usuario')->insert($fila);

        $this->expectException(QueryException::class);
        DB::table('marketing_empresa_usuario')->insert($fila);
    }

    public function test_borrar_una_empresa_con_equipo_esta_restringido(): void
    {
        $id = DB::table('marketing_empresas')->insertGetId([
            'nombre' => 'QA Restrict', 'slug' => 'qa-restrict-' . Str::lower(Str::random(6)), 'activo' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $u = User::factory()->create(['estado' => 'activo']);
        DB::table('marketing_empresa_usuario')->insert([
            'company_id' => $id, 'user_id' => $u->id, 'rol' => 'admin', 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            DB::table('marketing_empresas')->where('id', $id)->delete();
            $this->fail('Se pudo borrar una empresa con asignaciones (FK debía ser RESTRICT).');
        } catch (QueryException $e) {
            $this->assertSame(1, DB::table('marketing_empresas')->where('id', $id)->count());
        }
    }

    public function test_borrar_un_usuario_borra_sus_asignaciones_en_cascada(): void
    {
        $u = User::factory()->create(['estado' => 'activo']);
        DB::table('marketing_empresa_usuario')->insert([
            'company_id' => 1, 'user_id' => $u->id, 'rol' => 'ventas', 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $u->id)->delete();

        $this->assertSame(0, DB::table('marketing_empresa_usuario')->where('user_id', $u->id)->count());
    }

    public function test_no_se_agregaron_fks_a_tablas_existentes(): void
    {
        $refs = DB::select(
            "select TABLE_NAME t, COLUMN_NAME c from information_schema.KEY_COLUMN_USAGE
              where TABLE_SCHEMA = database() and REFERENCED_TABLE_NAME = 'marketing_empresas'"
        );
        $tablas = array_values(array_unique(array_map(fn ($r) => $r->t, $refs)));

        $this->assertSame(['marketing_empresa_usuario'], $tablas, 'Solo marketing_empresa_usuario puede apuntar a marketing_empresas (§2.1).');
    }

    // ── permiso (criterio 3) ─────────────────────────────────────────────────

    public function test_permiso_gestionar_existe_y_lo_tienen_solo_los_roles_base(): void
    {
        $permiso = DB::table('permissions')->where('name', 'marketing.empresas.gestionar')->where('guard_name', 'web')->first();
        $this->assertNotNull($permiso, 'Falta el permiso marketing.empresas.gestionar');

        $roles = DB::table('role_has_permissions')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('role_has_permissions.permission_id', $permiso->id)
            ->pluck('roles.name')->sort()->values()->all();

        $this->assertSame(['DESARROLLADOR', 'super-administrator'], $roles, 'Solo super-administrator y DESARROLLADOR reciben el permiso por la fase.');

        $this->assertSame(
            0,
            DB::table('model_has_permissions')->where('permission_id', $permiso->id)->count(),
            'La fase no debe dar el permiso directo a ninguna persona.'
        );
    }

    public function test_module_json_declara_permiso_y_tarjeta_sin_subir_version(): void
    {
        $ruta = base_path('app/Modules/Addons/Marketing/module.json');
        $json = json_decode(file_get_contents($ruta), true);
        $this->assertIsArray($json);

        $permisos = array_map(fn ($p) => is_array($p) ? ($p['name'] ?? null) : $p, $json['permissions'] ?? []);
        $this->assertContains('marketing.empresas.gestionar', $permisos);

        $tarjetas = array_filter($json['admin_cards'] ?? [], fn ($c) => ($c['url'] ?? null) === '/marketing/empresas');
        $this->assertCount(1, $tarjetas, 'Falta la tarjeta «Marketing — Empresas y equipo» en admin_cards');
        $tarjeta = array_values($tarjetas)[0];
        $this->assertSame('marketing.empresas.gestionar', $tarjeta['permission'] ?? null);

        $base = shell_exec('git -C ' . escapeshellarg(base_path()) . ' show HEAD:app/Modules/Addons/Marketing/module.json 2>/dev/null');
        if ($base) {
            $this->assertSame(json_decode($base, true)['version'] ?? null, $json['version'] ?? null, 'No se sube la versión del addon a mitad del plan.');
        }
    }
}
