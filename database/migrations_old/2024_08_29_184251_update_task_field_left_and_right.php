<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->update([
            'main' => 'App\Models\\Task'
        ]);

        $fieldsLeft = [
            [
                'name' => 'project_id',
                'label' => 'Proyecto',
                'type' => 22,
                'placeholder' => 'Selecciona Proyecto',
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Project',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_col' => 'partial',
            ],

            [
                'name' => 'priority',
                'label' => 'Prioridad',
                'type' => 22,
                'placeholder' => '',
                'position' => 2,
                'additional_field' => false,
                'value' => '',
                'options' => json_encode([
                    'Alta' => 'Alta',
                    'Media' => 'Media',
                    'Baja' => 'Baja'
                ]),
                'class_col' => 'partial',
            ],
            [
                'name' => 'status',
                'label' => 'Estado',
                'type' => 22,
                'placeholder' => '',
                'position' => 3,
                'additional_field' => false,
                'value' => '',
                'options' => json_encode([
                    'ToDo' => 'Por Hacer',
                    'InProgress' => 'En Progreso',
                    'Done' => 'Terminado'
                ]),
                'class_col' => 'partial',
            ],
            [
                'name' => 'partner_id',
                'label' => 'Socio',
                'type' => 22,
                'placeholder' => '',
                'position' => 4,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_col' => 'partial',
            ],
            [
                'name' => 'client_main_information_id',
                'label' => 'Cliente',
                'placeholder' => 'Por Favor introduzca 2 o mas caracteres',
                'type' => 34,
                'position' => 5,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\ClientMainInformation',
                    'id' => 'id',
                    'text' => 'name',
                    'append' => 'client_name_with_fathers_names'
                ]),
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],

            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 8,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],


            [
                'name' => 'file',
                'label' => 'Archivo Adjunto',
                'placeholder' => 'Archivo Adjunto',
                'type' => 6,
                'position' => 9,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'location_id',
                'label' => 'Ubicación',
                'type' => 22,
                'placeholder' => '',
                'position' => 11,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name',
                ]),
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
            [
                'name' => 'address',
                'label' => 'Dirección',
                'placeholder' => 'Dirección',
                'type' => 1,
                'position' => 12,
                'additional_field' => false,
                'class_label' => "col-12",
                'class_field' => "col-12"
            ],
        ];
        $moduleLeft->fields()->createMany($fieldsLeft);


        $moduleRight = Module::where('name', 'TaskRight')->first();
        $fieldsRight = [
            [
                'name' => 'assigned_to',
                'label' => 'Asignado a',
                'type' => 35,
                'placeholder' => '',
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notClientRole'
                ]),
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
                //Agregar scope cuando se sepa que usuarios hay que asignarle
            ],


            [
                'name' => 'start_time',
                'label' => 'Fecha de Inicio',
                'type' => 36,
                'placeholder' => '',
                'position' => 2,
                'additional_field' => false,
                'value' => '',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
            [
                'name' => 'end_time',
                'label' => 'Fecha de Terminación',
                'type' => 36,
                'placeholder' => '',
                'position' => 3,
                'additional_field' => false,
                'value' => '',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],

            [
                'name' => 'time_to_task_location',
                'label' => 'Tiempo hasta la Tarea',
                'type' => 37,
                'placeholder' => '0h 0m',
                'position' => 4,
                'additional_field' => false,
                'value' => '',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
            [
                'name' => 'time_from_task_location',
                'label' => 'Tiempo desde la Tarea',
                'type' => 37,
                'placeholder' => '0h 0m',
                'position' => 5,
                'additional_field' => false,
                'value' => '',
                'class_label' => "col-form-label pr-2",
                'class_field' => "col-sm-12"
            ],
        ];
        $moduleRight->update([
            'main' => 'App\Models\\Task'
        ]);
        $moduleRight->fields()->createMany($fieldsRight);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->fields()->delete();
        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->delete();
    }
};
