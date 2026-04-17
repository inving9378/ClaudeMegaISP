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
        Schema::create('box_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('zone_id')->constrained()->onDelete('cascade'); // Relación con la zona
            $table->string('client');
            $table->timestamps();
        });

        $module = Module::create([
            'name' => 'BoxZone',
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
                'name' => 'name',
                'label' => 'Nombre',
                'placeholder' => 'Zona',
                'type' => 1,
                'position' => 1,
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
        Schema::dropIfExists('box_zones');
        $module = Module::where('name', 'BoxZone')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
