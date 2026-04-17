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
        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);

        //Tabla
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('type')->nullable();
            $table->string('project_lead');
            $table->string('category')->nullable();
            $table->string('workflow')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        //Modulo
        $module = Module::create([
            'name' => 'Project',
            'is_main' => true,
            'description' => 'Scheduling Project',
            'main' => null,
        ]);
        //Fields
        $fields = [
            [
                'name' => 'title',
                'label' => 'Título',
                'placeholder' => 'Título',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Descripción',
                'type' => 5,
                'position' => 2,
                'additional_field' => false,
            ],
            [
                'name' => 'type',
                'label' => 'Tipo',
                'type' => 22,
                'placeholder' => 'Seleccione Tipo',
                'position' => 3,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\ProjectType',
                    'id' => 'id',
                    'text' => 'name'
                ])
            ],
            [
                'name' => 'partners',
                'label' => 'Socios',
                'type' => 25,
                'placeholder' => 'Socios',
                'position' => 4,
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
                'name' => 'project_lead',
                'label' => 'Project Lead',
                'type' => 22,
                'placeholder' => 'Seleccione',
                'position' => 5,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\User',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'leadProject'
                ])
            ],
            [
                'name' => 'category',
                'label' => 'Categoría',
                'type' => 22,
                'placeholder' => 'Default',
                'position' => 6,
                'additional_field' => false,
                'value' => '',
                'options' => null
            ],
            [
                'name' => 'workflow',
                'label' => 'Flujo de Trabajo',
                'type' => 22,
                'placeholder' => 'Flujo de Trabajo',
                'position' => 7,
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

        //Creo los Modelos WorkFlow y ProjectType que no existen con sus respectivas tablas
        Schema::create('work_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        //Creo Los ColumnsDatatables
        $columnsDatatableByModule = [
            [
                'name' => 'title',
                'filter_name' => null,
                'label' => "Título",
                'order' => 1
            ],

            [
                'name' => 'task_status_new',
                'filter_name' => null,
                'label' => "Nuevo",
                'order' => 2
            ],

            [
                'name' => 'task_status_in_progress',
                'filter_name' => null,
                'label' => "En Progreso",
                'order' => 3
            ],

            [
                'name' => 'task_status_in_completed',
                'filter_name' => null,
                'label' => "Completado",
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


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
        //module
        $module = Module::where('name', 'Project')->first();
        //fields
        $module->fields()->delete();
        //columnsdatatables
        $module->columnsDatatable()->delete();
        //tablas adicionales
        Schema::dropIfExists('work_flows');
        Schema::dropIfExists('project_types');
        //packages
        $module->packages()->detach();
        $module->delete();
    }
};
