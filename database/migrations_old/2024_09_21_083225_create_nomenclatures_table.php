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
        Schema::create('nomenclatures', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('client_id')->nullable(); // Hacer que el client_id sea opcional (nullable)
            $table->timestamps();

            // Relación entre nomenclatures y clients
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        $module = Module::create([
            'name' => 'Nomenclature',
            'group' => 'Administration'
        ]);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $google, $apechart]);


        $fields = [
            [
                'name' => 'district',
                'label' => 'Distrito',
                'placeholder' => 'Ej. 1',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'zone',
                'label' => 'Zona',
                'placeholder' => 'Ej. 1',
                'type' => 1,
                'position' => 2,
                'additional_field' => false,
            ],
            [
                'name' => 'box_zone',
                'label' => 'Caja',
                'placeholder' => 'Ej. 1',
                'type' => 1,
                'position' => 3,
                'additional_field' => false,
            ],

            [
                'name' => 'client',
                'label' => 'Cliente',
                'placeholder' => 'Ej. 1',
                'type' => 1,
                'position' => 4,
                'additional_field' => false,
            ],
            [
                'name' => 'multiple',
                'label' => 'Agregar en Masa',
                'placeholder' => 'Separado por , Ej, D1Z1C1:1, D1Z1C1:2',
                'type' => 1,
                'position' => 5,
                'additional_field' => false,
            ]
        ];
        $module->fields()->createMany($fields);

        $columnsDatatableByModule = [
            [
                'name' => 'id',
                'filter_name' => null,
                'label' => "ID",
                'order' => 1
            ],
            [
                'name' => 'name',
                'filter_name' => null,
                'label' => "Nombre",
                'order' => 2
            ],

            [
                'name' => 'client_id',
                'filter_name' => null,
                'label' => "Cliente",
                'order' => 3
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
        Schema::dropIfExists('nomenclatures');
        $module = Module::where('name', 'Nomenclature')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
