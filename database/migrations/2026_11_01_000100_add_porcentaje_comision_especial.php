<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-H6 F2 (#1150, decisión de Irving en #550 q2): % de comisión de distribuidor propio del contrato
 * con plazo «Especial». Aditiva e idempotente; no toca datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('client_main_information', 'porcentaje_comision_especial')) {
            Schema::table('client_main_information', function (Blueprint $table) {
                $table->decimal('porcentaje_comision_especial', 5, 2)->nullable()->after('plazo_especial_meses');
            });
        }

        $moduleId = DB::table('modules')->where('name', 'ClientMainInformation')->value('id');
        if ($moduleId && ! DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'porcentaje_comision_especial')->exists()) {
            DB::table('field_modules')->insert([
                'module_id' => $moduleId, 'name' => 'porcentaje_comision_especial', 'type' => '15',
                'label' => '% comisión distribuidor (solo plazo Especial)', 'placeholder' => 'Ej. 30',
                'hint' => 'Solo si la duración del contrato es «Especial». Vacío = sin comisión.', 'options' => '[]',
                'position' => 34, 'include' => 1, 'partition' => null, 'additional_field' => 0, 'class_col' => 'full',
                'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('name', 'ClientMainInformation')->value('id');
        if ($moduleId) {
            DB::table('field_modules')->where('module_id', $moduleId)->where('name', 'porcentaje_comision_especial')->delete();
        }
        if (Schema::hasColumn('client_main_information', 'porcentaje_comision_especial')) {
            Schema::table('client_main_information', function (Blueprint $table) {
                $table->dropColumn('porcentaje_comision_especial');
            });
        }
    }
};
