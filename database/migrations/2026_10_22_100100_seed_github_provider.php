<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad V1.64 (2026-10-22). Catálogo del Hub: «GitHub (actualizaciones)». Guarda cifrado el
 * token de solo lectura con el que este servidor consulta las versiones y descarga el código
 * cuando el repositorio es privado (GitHubUpdateService y remote:deploy). Mismo mecanismo de
 * credenciales cifradas que los demás proveedores; sustituye a GITHUB_READ_TOKEN del .env, que
 * queda como respaldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_integration_providers')
            || DB::table('api_integration_providers')->where('slug', 'github')->exists()) {
            return;
        }

        DB::table('api_integration_providers')->insert([
            'slug'        => 'github',
            'name'        => 'GitHub (actualizaciones)',
            'description' => 'Token de solo lectura para ver y descargar las versiones cuando el repositorio es privado',
            'type'        => 'servicios',
            'servicio'    => 'actualizaciones',
            'icon'        => 'bi bi-github',
            'docs_url'    => 'https://github.com/settings/personal-access-tokens/new',
            'key_format'  => 'github_pat_… (fine-grained, solo este repositorio, Contents: Read)',
            'has_config'  => false,
            'is_system'   => true,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('api_integration_providers')->where('slug', 'github')->delete();
    }
};
