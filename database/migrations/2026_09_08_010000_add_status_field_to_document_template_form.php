<?php

use App\Models\Module;
use App\Modules\Core\Configuracion\Models\FieldType;
use Illuminate\Database\Migrations\Migration;

/**
 * Fase 1 (#9990578): agrega el campo 'status' (Borrador/Publicada) al catálogo del CRUD
 * de plantillas (módulo 'DocumentTemplate', id=61 en dev) para que TemplateManager.vue
 * lo pinte — el form es 100% DB-driven vía Module::getfields() (ver CLAUDE.md "Catálogo
 * de módulos"), así que sin este row la columna nueva quedaría decorativa. Mismo patrón
 * que los campos hermanos 'name'/'type' del mismo módulo: select-component con 'options'
 * estático (sin 'search'), igual que 'priority'/'transaction_category' en otros módulos.
 *
 * Aditiva e idempotente (firstOrCreate por module_id+name): segura de correr en cualquier
 * entorno, incluido uno donde el módulo ya tenga el campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $module = Module::where('name', 'DocumentTemplate')->first();
        if (!$module) {
            return;
        }

        $selectTypeId = FieldType::where('name', 'select-component')->value('id');

        $module->fields()->firstOrCreate(
            ['module_id' => $module->id, 'name' => 'status'],
            [
                'type' => $selectTypeId,
                'label' => 'Estado',
                'placeholder' => 'Estado',
                'value' => '',
                'options' => json_encode(['borrador' => 'Borrador', 'publicada' => 'Publicada']),
                'position' => 4,
                'include' => true,
                'additional_field' => false,
                'class_col' => 'full',
                'class_label' => 'col-sm-12 col-md-3 col-form-label text-md-end pr-2 text-sm-center',
                'class_field' => 'col-sm-12 col-md-9',
            ]
        );
    }

    public function down(): void
    {
        $module = Module::where('name', 'DocumentTemplate')->first();
        if ($module) {
            $module->fields()->where('name', 'status')->delete();
        }
    }
};
