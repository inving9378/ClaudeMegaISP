<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FR-03 (#537, decisión de Irving 2026-10-17): plazo de contrato «Especial» y plazo en CRM. Aditiva
 * e idempotente; los clientes actuales conservan su plazo y no se toca ningún dato:
 *  - duration_contracts += «Especial» (duration 0: los meses se capturan a mano);
 *  - client_main_information += plazo_especial_meses;
 *  - crm_main_information += duration_contract_id, plazo_especial_meses (se copian al convertir
 *    el prospecto en cliente: la conversión copia los campos que se llaman igual);
 *  - campos de formulario: «Meses (plazo Especial)» en la ficha; plazo + meses en CRM (default 18).
 * Las ids del catálogo se resuelven por NOMBRE (no se copian ids entre entornos).
 */
return new class extends Migration
{
    private const ESPECIAL = 'Especial';

    public function up(): void
    {
        if (! DB::table('duration_contracts')->where('name', self::ESPECIAL)->exists()) {
            DB::table('duration_contracts')->insert(['name' => self::ESPECIAL, 'duration' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        if (! Schema::hasColumn('client_main_information', 'plazo_especial_meses')) {
            Schema::table('client_main_information', function (Blueprint $table) {
                $table->unsignedSmallInteger('plazo_especial_meses')->nullable()->after('duration_contract_id');
            });
        }
        Schema::table('crm_main_information', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_main_information', 'duration_contract_id')) {
                $table->unsignedBigInteger('duration_contract_id')->nullable();
            }
            if (! Schema::hasColumn('crm_main_information', 'plazo_especial_meses')) {
                $table->unsignedSmallInteger('plazo_especial_meses')->nullable();
            }
        });

        $buscador = json_encode(['model' => 'App\\Models\\DurationContract', 'text' => 'name', 'id' => 'id']);
        $meses = [
            'type' => '15', 'label' => 'Meses (solo plazo Especial)', 'placeholder' => 'Ej. 9',
            'hint' => 'Solo si la duración del contrato es «Especial».', 'options' => '[]',
        ];

        $this->campo('ClientMainInformation', 'plazo_especial_meses', $meses + ['position' => 33]);

        $id18 = DB::table('duration_contracts')->where('name', '18 Meses')->value('id');
        $this->campo('CrmMainInformation', 'duration_contract_id', [
            'type' => '22', 'label' => 'Duración del Contrato', 'search' => $buscador, 'position' => 21,
            'default_value' => $id18 ? json_encode((string) $id18) : null,
        ]);
        $this->campo('CrmMainInformation', 'plazo_especial_meses', $meses + ['position' => 22]);
    }

    /** Crea el campo de formulario si no existe (firstOrCreate por módulo + nombre). */
    private function campo(string $modulo, string $nombre, array $datos): void
    {
        $moduleId = DB::table('modules')->where('name', $modulo)->value('id');
        if (! $moduleId || DB::table('field_modules')->where('module_id', $moduleId)->where('name', $nombre)->exists()) {
            return;
        }
        DB::table('field_modules')->insert($datos + [
            'module_id' => $moduleId, 'name' => $nombre, 'include' => 1, 'partition' => null,
            'additional_field' => 0, 'class_col' => 'full',
            'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            'class_field' => 'col-sm-12 col-md-9', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach ([['ClientMainInformation', 'plazo_especial_meses'], ['CrmMainInformation', 'duration_contract_id'], ['CrmMainInformation', 'plazo_especial_meses']] as [$modulo, $nombre]) {
            $moduleId = DB::table('modules')->where('name', $modulo)->value('id');
            if ($moduleId) {
                DB::table('field_modules')->where('module_id', $moduleId)->where('name', $nombre)->delete();
            }
        }
        Schema::table('crm_main_information', function (Blueprint $table) {
            foreach (['plazo_especial_meses', 'duration_contract_id'] as $col) {
                if (Schema::hasColumn('crm_main_information', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        if (Schema::hasColumn('client_main_information', 'plazo_especial_meses')) {
            Schema::table('client_main_information', function (Blueprint $table) {
                $table->dropColumn('plazo_especial_meses');
            });
        }
        // La fila «Especial» del catálogo no se borra si algún cliente o prospecto ya la usa.
        $id = DB::table('duration_contracts')->where('name', self::ESPECIAL)->value('id');
        if ($id && ! DB::table('client_main_information')->where('duration_contract_id', $id)->exists()) {
            DB::table('duration_contracts')->where('id', $id)->delete();
        }
    }
};
