<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #851 (Fase A) — backfill de `permission_scopes` leyendo `scope_propios`
 * declarado en todos los module.json (Addons y Core), mismo patrón que el
 * backfill de `permissions.description` (#858). Solo inserta lo que falte
 * (no pisa filas ya escritas). down() borra únicamente lo que esta
 * migración insertó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permission_scopes')) {
            return;
        }

        foreach ($this->collectScopeDeclarations() as $name => $criterio) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if (!$permissionId) {
                continue;
            }

            $exists = DB::table('permission_scopes')->where('permission_id', $permissionId)->exists();
            if ($exists) {
                continue;
            }

            DB::table('permission_scopes')->insert([
                'permission_id'    => $permissionId,
                'criterio_propios' => $criterio,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permission_scopes')) {
            return;
        }

        $names = array_keys($this->collectScopeDeclarations());
        if (empty($names)) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');
        DB::table('permission_scopes')->whereIn('permission_id', $permissionIds)->delete();
    }

    /** @return array<string,string> name => criterio_propios */
    private function collectScopeDeclarations(): array
    {
        $manifests = array_merge(
            glob(base_path('app/Modules/Addons/*/module.json')) ?: [],
            glob(base_path('app/Modules/Core/*/module.json')) ?: []
        );

        $out = [];

        foreach ($manifests as $manifestPath) {
            $manifest    = json_decode(file_get_contents($manifestPath), true);
            $permissions = $manifest['permissions'] ?? [];

            foreach ($permissions as $permDef) {
                if (!is_array($permDef)) {
                    continue;
                }

                $name     = $permDef['name'] ?? null;
                $criterio = $permDef['scope_propios'] ?? null;

                if ($name && $criterio && !isset($out[$name])) {
                    $out[$name] = $criterio;
                }
            }
        }

        return $out;
    }
};
