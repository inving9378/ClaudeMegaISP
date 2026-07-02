<?php

use App\Models\Module;
use App\Modules\Core\Configuracion\Models\FieldType;
use Illuminate\Database\Migrations\Migration;

/**
 * Repone dos rows de catalogo de la tabla 'modules' que existen en produccion
 * pero faltaban en entornos cuya BD se reconstruyo despues de que las
 * migraciones originales se archivaran a database/migrations_old/ (por lo que
 * ya no corren en un migrate fresco):
 *
 *  - DocumentTemplateClient: consumido por el front (CrmTemplate.vue y
 *    PlantillasClientes.vue via requestFieldsByModule). Sin este row,
 *    /fields-by-module devuelve 500 y el modal "Generar Contrato" queda vacio
 *    (sin los selectores Tipo de Plantilla + Plantilla).
 *  - FiltersTaskCalendar: consumido por FullcalendarController y TaskController
 *    (ambos null-safe). Sin el row no se puede guardar/leer la preferencia
 *    'initialView' del calendario de tareas.
 *
 * Aditiva e idempotente (firstOrCreate por row y por campo): segura de correr
 * en cualquier estado, incluido prod donde los rows ya existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Row 1: DocumentTemplateClient (front-consumed) + sus 3 campos ---
        $docModule = Module::firstOrCreate(
            ['name' => 'DocumentTemplateClient'],
            ['is_main' => true, 'description' => 'Plantillas de Clientes', 'main' => null]
        );

        $ftId = FieldType::where('name', 'select-2-type-template')->value('id');

        $fields = [
            ['name' => 'type',     'type' => $ftId, 'label' => 'Tipo de Plantilla', 'placeholder' => 'Tipo de Plantilla', 'value' => '', 'position' => 1,   'include' => true, 'additional_field' => false],
            ['name' => 'template', 'type' => 30,    'label' => 'Plantilla',         'placeholder' => 'Plantilla',         'value' => '', 'position' => 999, 'include' => true, 'additional_field' => false],
            ['name' => 'html',     'type' => 3,                                                                                         'position' => 999, 'include' => true, 'additional_field' => false],
        ];

        foreach ($fields as $field) {
            $docModule->fields()->firstOrCreate(
                ['module_id' => $docModule->id, 'name' => $field['name']],
                $field
            );
        }

        // --- Row 2: FiltersTaskCalendar (backend-consumed, null-safe; sin campos) ---
        Module::firstOrCreate(
            ['name' => 'FiltersTaskCalendar'],
            ['is_main' => true, 'description' => null, 'main' => null]
        );
    }

    public function down(): void
    {
        // Intencionalmente vacio. Esta migracion es ADITIVA: repone catalogo real
        // que ya existe en produccion. Un down() destructivo borraria esos rows y
        // reintroduciria el bug (form vacio / 500). NO completar este metodo.
    }
};
