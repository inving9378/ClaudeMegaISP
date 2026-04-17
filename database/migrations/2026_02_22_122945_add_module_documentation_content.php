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
            ['name' => 'DocumentationContent'],
            [
                'group' => 'Administration', 
                'description' => 'Módulo para gestionar los contenidos de documentación del sistema',
            ]
        );

        $fields = [                            
            [
                'name' => 'documentation_submenu_id',
                'label' => 'Submenú de Documentación',
                'placeholder' => 'Seleccione el submenú de documentación al que pertenece este contenido',
                'type' => 3,  // Cambio de 22 a 3. Ver si soluciona problema del formulario
                'position' => 2,
                'additional_field' => false,
                'class_col' => 'full',
                'class_field' => 'col-12',
                'class_label' => 'col-12',
                'search' => json_encode([
                    'model' => 'App\\Models\\DocumentationSubmenu',
                    'text' => 'title',
                    'id' => 'id'
                ]),
            ],
            [
                'name'=>'content',
                'label'=>'Contenido',
                'placeholder'=>'Inserte el contenido de documentación aquí (puede usar HTML)',
                'type'=>42,
                'position'=>3,
                'additional_field'=>false,
                'class_col'=>'full',
                'class_field'=>'col-12',
                'class_label'=>'col-12',                
            ]
        ];
        
        $module->fields()->createMany($fields);

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
        $module = Module::where('name', 'DocumentationContent')->first();
        if ($module) {
            $module->fields()->delete();
            $module->columnsDatatable()->delete();
            $module->packages()->detach();  
        }
    }
};
