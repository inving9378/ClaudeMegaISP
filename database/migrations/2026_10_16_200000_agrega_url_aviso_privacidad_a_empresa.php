<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAC-03 (item #508) — URL del aviso de privacidad de la empresa, para mostrarla en la
 * Prefactura/Factura. `data_privacy_address` NO sirve: es el domicilio para asuntos de datos
 * personales (texto libre del expediente RH, #199), no un enlace.
 *
 * Aditiva e idempotente: columna nullable (vacía = la línea no aparece) y el campo en el
 * formulario genérico «Información de la empresa» (catálogo DB-driven field_modules, módulo
 * CompanyInformation, mismo patrón que #199) con regla `nullable|url:http,https|max:500`, que
 * valida CompanyInformationController::update(). No se inventa ni se carga ningún aviso: la URL
 * la captura Irving.
 */
return new class extends Migration
{
    private const CAMPO = 'url_aviso_privacidad';

    public function up(): void
    {
        if (! Schema::hasColumn('company_information', self::CAMPO)) {
            Schema::table('company_information', function (Blueprint $table) {
                $table->string(self::CAMPO, 500)->nullable()->after('url_portal');
            });
        }

        $moduleId = DB::table('modules')->where('name', 'CompanyInformation')->value('id');
        if (! $moduleId) {
            return; // catálogo ausente en este entorno; la columna basta para el PDF.
        }

        $existe = DB::table('field_modules')->where('module_id', $moduleId)->where('name', self::CAMPO)->exists();
        if ($existe) {
            return;
        }

        DB::table('field_modules')->insert([
            'module_id'        => $moduleId,
            'include'          => 1,
            'name'             => self::CAMPO,
            'type'             => '1',
            'label'            => 'URL del aviso de privacidad',
            'placeholder'      => 'https://… (vacío = no se muestra en la Prefactura)',
            'rule'             => 'nullable|url:http,https|max:500',
            'position'         => (int) DB::table('field_modules')->where('module_id', $moduleId)->max('position') + 1,
            'additional_field' => 0,
            'class_col'        => 'full',
            'class_label'      => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            'class_field'      => 'col-sm-12 col-md-9',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('name', 'CompanyInformation')->value('id');
        if ($moduleId) {
            DB::table('field_modules')->where('module_id', $moduleId)->where('name', self::CAMPO)->delete();
        }

        if (Schema::hasColumn('company_information', self::CAMPO)) {
            Schema::table('company_information', function (Blueprint $table) {
                $table->dropColumn(self::CAMPO);
            });
        }
    }
};
