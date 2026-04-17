<?php

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
        Schema::create('template_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title_template')->nullable();
            $table->string('title_task')->nullable();
            $table->string('project_id')->nullable();
            $table->string('template_verification_id')->nullable();
            $table->string('priority')->nullable();
            $table->longText('description')->nullable();
            $table->string('assigned_to');
            $table->timestamps();
        });


        $module = Module::create([
            'name' => 'TemplateTask',
            'group' => 'Configuration',
        ]);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $apechart, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $google]);

        $fields = [
            [
                'name' => 'title_template',
                'label' => 'Título de la Plantilla',
                'placeholder' => 'Título de la Plantilla',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'title_task',
                'label' => 'Título de la Tarea',
                'placeholder' => 'Título de la Tarea',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
            ],

            [
                'name' => 'project_id',
                'label' => 'Proyecto',
                'type' => 22,
                'placeholder' => 'Selecciona Proyecto',
                'position' => 3,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Project',
                    'id' => 'id',
                    'text' => 'title',
                ])
            ],

            [
                'name' => 'template_verification_id',
                'label' => 'Lista de Verificación',
                'type' => 22,
                'placeholder' => 'Selecciona Lista',
                'position' => 4,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\TemplateVerification',
                    'id' => 'id',
                    'text' => 'name',
                ])
            ],

            [
                'name' => 'priority',
                'label' => 'Prioridad',
                'type' => 22,
                'placeholder' => '',
                'position' => 5,
                'additional_field' => false,
                'value' => '',
                'options' => json_encode([
                    'Alta' => 'Alta',
                    'Media' => 'Media',
                    'Baja' => 'Baja'
                ]),
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 6,
                'additional_field' => false,
            ],
            [
                'name' => 'assigned_to',
                'label' => 'Asignado a',
                'type' => 35,
                'placeholder' => '',
                'position' => 7,
                'additional_field' => false,
                'value' => '',
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notClientRole'
                ]),
            ],
        ];
        $module->fields()->createMany($fields);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

        $columnsDatatableByModule = [
            [
                'name' => 'id',
                'filter_name' => null,
                'label' => "ID",
                'order' => 1
            ],
            [
                'name' => 'title_template',
                'filter_name' => null,
                'label' => "Título de la Plantilla",
                'order' => 2
            ],
            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_tasks');
        $module = Module::where('name', 'TemplateTask')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
