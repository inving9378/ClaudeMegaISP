<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #858 — backfill de `permissions.description` para las filas existentes,
 * leyendo el `description` declarado en TODOS los module.json (Addons Y Core)
 * y matcheando por `name`. Solo llena filas con description NULL (no pisa nada
 * ya escrito). Idempotente. down() vuelve a NULL únicamente los names que esta
 * migración tocó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'description')) {
            return; // depende de la migración que crea la columna
        }

        $descriptions = $this->collectDescriptionsFromManifests();
        if (empty($descriptions)) {
            return;
        }

        foreach ($descriptions as $name => $description) {
            DB::table('permissions')
                ->where('name', $name)
                ->whereNull('description')
                ->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('permissions', 'description')) {
            return;
        }

        $names = array_keys($this->collectDescriptionsFromManifests());
        if (empty($names)) {
            return;
        }

        DB::table('permissions')->whereIn('name', $names)->update(['description' => null]);
    }

    /** @return array<string,string> name => description */
    private function collectDescriptionsFromManifests(): array
    {
        $manifests = array_merge(
            glob(base_path('app/Modules/Addons/*/module.json')) ?: [],
            glob(base_path('app/Modules/Core/*/module.json')) ?: []
        );

        $descriptions = [];

        foreach ($manifests as $manifestPath) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $permissions = $manifest['permissions'] ?? [];

            foreach ($permissions as $permDef) {
                if (!is_array($permDef)) {
                    continue;
                }

                $name = $permDef['name'] ?? null;
                $description = $permDef['description'] ?? null;

                if ($name && $description && !isset($descriptions[$name])) {
                    $descriptions[$name] = $description;
                }
            }
        }

        return $descriptions;
    }
};
