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
        $module->fields()->create([

            'name' => 'type',
            'label' => 'Tipo de Documento',
            'type' => 22,
            'placeholder' => 'Tipo de Documento',
            'position' => 2,
            'additional_field' => false,
            'value' => '',
            'options' => null,
            'search' => json_encode([
                'model' => 'App\Models\DocumentTypeTemplate',
                'id' => 'id',
                'text' => 'name'
            ])

        ]);

        $module->columnsDatatable()->create([
            'name' => 'type',
            'filter_name' => null,
            'label' => "Tipo",
            'order' => 3
        ]);
        $module->columnsDatatable()->where('name', 'action')->update([
            'order' => 999
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'DocumentTemplate')->first();
        $module->fields()->where('name', 'type')->delete();
        $module->columnsDatatable()->where('name', 'type')->delete();
    }
};
