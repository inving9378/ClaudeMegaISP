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
        $newModule = [
            'name' => 'ActivityLog',
            'is_main' => true,
            'description' => "Modulo encargado de guardar historico",
            'group' => 'Administration'
        ];

        $module = Module::create($newModule);

        $columnsDatatableByModule = [
            [
                'name' => 'log_name',
                'filter_name' => null,
                'label' => "Nombre",
                'order' => 1
            ],
            [
                'name' => 'description',
                'filter_name' => null,
                'label' => "Descripcion",
                'order' => 2
            ],
            [
                'name' => 'subject_type',
                'filter_name' => null,
                'label' => "Subject Type",
                'order' => 3
            ],
            [
                'name' => 'event',
                'filter_name' => null,
                'label' => "Evento",
                'order' => 4
            ],
            [
                'name' => 'causer_type',
                'filter_name' => null,
                'label' => "Causer Type",
                'order' => 5
            ],
            [
                'name' => 'properties',
                'filter_name' => null,
                'label' => "Propiedades",
                'order' => 6
            ],
            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 7
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name','ActivityLog')->first();
        $module->columnsDatatable()->delete();
        $module->delete();
    }
};
