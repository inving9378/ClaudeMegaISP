<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Module;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::firstOrCreate(
            ['name' => 'DocumentationMenu'],
            [
                'group' => 'Administration', 
                'description' => 'Módulo para gestionar los menús de documentación del sistema',
            ]
        );

        $fields = [
            [
                'name' => 'title',
                'label' => 'Título',
                'placeholder' => 'Ej. Guías de Usuario',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
                'class_col' => 'full',
                'class_field' => 'col-12',
                'class_label' => 'col-12',
            ],
        ];

        $module->fields()->createMany($fields);

        $columnsDatatable = [
            ['name' => 'id', 'label' => 'ID', 'order' => 1],
            ['name' => 'title', 'label' => 'Título', 'order' => 2],
            ['name' => 'created_at', 'label' => 'Fecha de Creación', 'order' => 3],
            ['name' => 'updated_at', 'label' => 'Fecha de Actualización', 'order' => 4],
            ['name' => 'action', 'filter_name' => null, 'label' => "Acciones", 'order' => 999],
        ];

        $module->columnsDatatable()->createMany($columnsDatatable);

        $packagesByCategory = [
            'ui_components' => [
                'bootstrap_multiselect' => [1, 2],
                'select2' => [4, 5],
                'chosen_select' => [21, 22],
            ],
            'notifications' => [
                'toaster' => [3],
            ],
            'data_tables' => [
                'datatables_core' => [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19],
            ],
            'editors' => [
                'ckeditor' => [23],
            ],
            'charts' => [
                'apechart' => [20],
            ],
            'services' => [
                'google' => [26],
            ],
        ];

        $packages = Arr::collapse(
            Arr::collapse($packagesByCategory) // Doble collapse por estructura anidada
        );

        $module->packages()->sync($packages);

        // 4. (Opcional) Logging de qué se sincronizó
        Log::info("Módulo {$module->name} sincronizado con paquetes: " . json_encode($packages));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'DocumentationMenu')->first();
        if ($module) {
            $module->fields()->delete();
            $module->columnsDatatable()->delete();
            $module->packages()->detach();  
        }
    }
};