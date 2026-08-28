<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #199 (Expediente RH — Hijo A). Expone los 2 campos nuevos de
 * `company_information` (legal_representative, data_privacy_address) en la pantalla genérica
 * de "Información de la empresa" (catálogo DB-driven `field_modules`), en vez de construir una
 * pantalla propia — mismo patrón que los campos hermanos (company_street, rfc, etc.) del mismo
 * módulo. Aditiva/idempotente (firstOrCreate por module_id+name).
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('modules')->where('name', 'CompanyInformation')->value('id');
        if (!$moduleId) {
            return; // catálogo no presente en este entorno; no bloquea el resto del item.
        }

        $maxPosition = (int) DB::table('field_modules')->where('module_id', $moduleId)->max('position');

        $base = [
            'module_id'    => $moduleId,
            'include'      => 1,
            'type'         => '1',
            'additional_field' => 0,
            'class_col'    => 'full',
            'class_label'  => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
            'class_field'  => 'col-sm-12 col-md-9',
        ];

        foreach ([
            ['name' => 'legal_representative', 'label' => 'Representante que firma', 'placeholder' => 'Nombre del representante'],
            ['name' => 'data_privacy_address', 'label' => 'Domicilio para datos personales', 'placeholder' => 'Domicilio para avisos de privacidad'],
        ] as $i => $field) {
            $exists = DB::table('field_modules')
                ->where('module_id', $moduleId)
                ->where('name', $field['name'])
                ->exists();

            if (!$exists) {
                DB::table('field_modules')->insert(array_merge($base, [
                    'name'        => $field['name'],
                    'label'       => $field['label'],
                    'placeholder' => $field['placeholder'],
                    'position'    => $maxPosition + 1 + $i,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Forward-only: no se revierte el catálogo. down() vacío a propósito.
    }
};
