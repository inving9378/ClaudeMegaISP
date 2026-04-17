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
        $module = Module::where('name', 'DocumentTemplate')->first();
        $columnsDatatableByModule = [
            [
                'name' => 'name',
                'filter_name' => null,
                'label' => "Nombre",
                'order' => 1
            ],
            [
                'name' => 'html',
                'filter_name' => null,
                'label' => "Html",
                'order' => 2
            ],

            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 3
            ],

        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'DocumentTemplate')->first();
        $module->columnsDatatable()->delete();
    }
};
