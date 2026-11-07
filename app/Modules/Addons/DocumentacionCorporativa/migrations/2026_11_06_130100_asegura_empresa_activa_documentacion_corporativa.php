<?php

use App\Modules\Addons\DocumentacionCorporativa\Seeders\EmpresaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sin una empresa activa, el módulo da 500 en todas sus pantallas y el seeder no corre en los despliegues.
 * El seeder no inventa datos: toma razón social y RFC de company_information y deja lo demás en blanco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_empresas')) {
            return;
        }

        $hayActiva = DB::table('dc_empresas')->where('activo', true)->whereNull('deleted_at')->exists();
        if (! $hayActiva) {
            (new EmpresaSeeder())->run();
        }
    }

    public function down(): void
    {
        // Forward-only: la empresa puede tener expediente asociado.
    }
};
