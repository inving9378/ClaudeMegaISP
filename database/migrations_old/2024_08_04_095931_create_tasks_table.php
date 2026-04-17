<?php

use App\Models\FieldType;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fieldTypeSearchCLient = FieldType::create([
            'name' => 'select_component_with_search_client',
        ]);

        $fieldTypeSearch = FieldType::create([
            'name' => 'select_component_with_search',
        ]);



        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('template_id')->nullable();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('status')->nullable();
            $table->string('client_main_information_id')->nullable();
            $table->string('client_service_id')->nullable();
            $table->string('partner_id')->nullable();
            $table->string('project_id')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('location_id')->nullable();
            $table->string('workflow')->nullable();
            $table->string('geo_data')->nullable();
            $table->string('address')->nullable();
            $table->boolean('notification')->default(false);
            $table->boolean('scheduled')->default(false);
            $table->string('start_date')->nullable();
            $table->string('duration')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });


        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $google]);

        $module = Module::create([
            'name' => 'Task',
            'is_main' => true,
            'description' => 'Scheduling Task',
            'main' => null,
        ]);

        //Fields
        $fields = [
            [
                'name' => 'template_id',
                'label' => 'Selecciona Plantilla',
                'type' => 22,
                'placeholder' => 'Selecciona Plantilla',
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            [
                'name' => 'title',
                'label' => 'Título',
                'placeholder' => 'Título',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 3,
                'additional_field' => false,
            ],
            [
                'name' => 'client_main_information_id',
                'label' => 'Cliente',
                'placeholder' => 'Por Favor introduzca 2 o mas caracteres',
                'type' => $fieldTypeSearchCLient->id,
                'position' => 4,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\ClientMainInformation',
                    'id' => 'id',
                    'text' => 'name',
                    'append' => 'client_name_with_fathers_names'
                ])
            ],
            [
                'name' => 'project_id',
                'label' => 'Proyecto',
                'type' => 22,
                'placeholder' => 'Selecciona Proyecto',
                'position' => 5,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Project',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            [
                'name' => 'assigned_to',
                'label' => 'Asignado a',
                'type' => $fieldTypeSearch->id,
                'placeholder' => '',
                'position' => 6,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'leadProject'
                ])
                //Agregar scope cuando se sepa que usuarios hay que asignarle
            ],
            [
                'name' => 'location_id',
                'label' => 'Ubicación',
                'type' => 22,
                'placeholder' => '',
                'position' => 7,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\Location',
                    'id' => 'id',
                    'text' => 'name',
                ])
                //Agregar scope cuando se sepa que usuarios hay que asignarle
            ],
            [
                'name' => 'address',
                'label' => 'Dirección',
                'placeholder' => 'Dirección',
                'type' => 1,
                'position' => 8,
                'additional_field' => false,
            ],
            [
                'name' => 'geo_data',
                'label' => 'Datos geográficos',
                'placeholder' => 'Datos geográficos',
                'type' => 1,
                'position' => 9,
                'additional_field' => false,
            ],

            [
                'name' => 'notification',
                'label' => 'Habilitar Notificaciones',
                'placeholder' => 'Habilitar Notificaciones',
                'type' => 16,
                'position' => 10,
                'additional_field' => false,
            ],

            [
                'name' => 'scheduled',
                'label' => 'Sheduled',
                'placeholder' => 'Sheduled',
                'type' => 16,
                'position' => 11,
                'additional_field' => false,
            ],

            [
                'name' => 'workflow',
                'label' => 'Flujo de Trabajo',
                'type' => 22,
                'placeholder' => 'Flujo de Trabajo',
                'position' => 12,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\WorkFlow',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
        ];
        $module->fields()->createMany($fields);

        //Creo Los ColumnsDatatables
        $columnsDatatableByModule = [
            [
                'name' => 'id',
                'filter_name' => null,
                'label' => "ID",
                'order' => 1
            ],
            [
                'name' => 'title',
                'filter_name' => null,
                'label' => "Título",
                'order' => 2
            ],
            [
                'name' => 'workflow',
                'filter_name' => null,
                'label' => "Flujo de Trabajo",
                'order' => 3
            ],
            [
                'name' => 'project_id',
                'filter_name' => null,
                'label' => "Proyecto",
                'order' => 4
            ],

            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);


        $moduleLeft = Module::create([
            'name' => 'TaskLeft',
            'is_main' => false,
            'main' => 'App\Models\Task',
            'description' => 'Scheduling Task',
            'main' => null,
        ]);
        $fieldsLeft = [
            [
                'name' => 'template_id',
                'label' => 'Selecciona Plantilla',
                'type' => 22,
                'placeholder' => 'Selecciona Plantilla',
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            [
                'name' => 'title',
                'label' => 'Título',
                'placeholder' => 'Título',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 3,
                'additional_field' => false,
            ],
            [
                'name' => 'partner_id',
                'label' => 'Socio',
                'type' => 22,
                'placeholder' => 'Socios',
                'position' => 5,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Partner',
                    'id' => 'id',
                    'text' => 'name'
                ])
            ],
            [
                'name' => 'project_id',
                'label' => 'Proyecto',
                'type' => 22,
                'placeholder' => 'Selecciona Proyecto',
                'position' => 6,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Project',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
            [
                'name' => 'assigned_to',
                'label' => 'Asignado a',
                'type' => $fieldTypeSearch->id,
                'placeholder' => '',
                'position' => 7,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'leadProject'
                ])
                //Agregar scope cuando se sepa que usuarios hay que asignarle
            ],
            [
                'name' => 'notification',
                'label' => 'Habilitar Notificaciones',
                'placeholder' => 'Habilitar Notificaciones',
                'type' => 16,
                'position' => 11,
                'additional_field' => false,
            ],

            [
                'name' => 'scheduled',
                'label' => 'Scheduled',
                'placeholder' => 'Scheduled',
                'type' => 16,
                'position' => 12,
                'additional_field' => false,
            ],
            [
                'name' => 'workflow',
                'label' => 'Flujo de Trabajo',
                'type' => 22,
                'placeholder' => 'Flujo de Trabajo',
                'position' => 12,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\WorkFlow',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],
        ];
        $moduleLeft->fields()->createMany($fieldsLeft);
        $moduleLeft->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);



        $moduleRight = Module::create([
            'name' => 'TaskRight',
            'is_main' => false,
            'main' => 'App\Models\Task',
            'description' => 'Scheduling Task',
            'main' => null,
        ]);

        $fieldsRight = [
              [
                'name' => 'client_main_information_id',
                'label' => 'Cliente',
                'placeholder' => 'Por Favor introduzca 2 o mas caracteres',
                'type' => $fieldTypeSearchCLient->id,
                'position' => 4,
                'additional_field' => false,
                'search' => json_encode([
                    'model' => 'App\Models\ClientMainInformation',
                    'id' => 'id',
                    'text' => 'name',
                    'append' => 'client_name_with_fathers_names'
                ])
            ],
            [
                'name' => 'client_service_id',
                'label' => 'Cliente',
                'placeholder' => 'Por Favor introduzca 2 o mas caracteres',
                'type' => 3,
                'position' => 999,
                'additional_field' => false,

            ],

            [
                'name' => 'location_id',
                'label' => 'Ubicación',
                'placeholder' => '',
                'type' => 3,
                'position' => 999,
                'additional_field' => false,

            ],
            [
                'name' => 'address',
                'label' => 'Dirección',
                'placeholder' => 'Dirección',
                'type' => 3,
                'position' => 9,
                'additional_field' => false,
            ],
            [
                'name' => 'geo_data',
                'label' => 'Datos geográficos',
                'placeholder' => 'Datos geográficos',
                'type' => 3,
                'position' => 10,
                'additional_field' => false,
            ],
        ];
        $moduleRight->fields()->createMany($fieldsRight);

        $moduleRight->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
        $module = Module::where('name', 'Task')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();

        FieldType::where('name', 'select_component_with_search_client')->delete();
        FieldType::where('name', 'select_component_with_search')->delete();

        $module->delete();

        $moduleLeft = Module::where('name', 'TaskLeft')->first();
        $moduleLeft->fields()->delete();
        $moduleLeft->columnsDatatable()->delete();
        $moduleLeft->packages()->detach();
        $moduleLeft->delete();

        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->delete();
        $moduleRight->columnsDatatable()->delete();
        $moduleRight->packages()->detach();
        $moduleRight->delete();
    }
};
