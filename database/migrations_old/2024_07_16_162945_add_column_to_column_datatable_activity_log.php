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
        $module = Module::where('name', 'ActivityLog')->first();
        $module->columnsDatatable()->createMany([
            [
                'name' => 'id',
                'filter_name' => null,
                'label' => "Id",
                'order' => 0
            ],
            [
                'name' => 'created_at',
                'filter_name' => null,
                'label' => "Fecha de Creación",
                'order' => 8
            ],
            [
                'name' => 'causer_id',
                'filter_name' => null,
                'label' => "Creado Por",
                'order' => 7
            ],
        ]);
        $module->columnsDatatable()->where('name', 'action')->delete();
        $module->columnsDatatable()->where('name', 'causer_type')->update(['label' => "Modelo"]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ActivityLog')->first();
        $module->columnsDatatable()->create([
            'name' => 'action',
            'filter_name' => null,
            'label' => "Acciones",
            'order' => 7
        ]);
        $module->columnsDatatable()->where('name', 'created_at')->delete();
        $module->columnsDatatable()->where('name', 'causer_id')->delete();
        $module->columnsDatatable()->where('name', 'id')->delete();
        $module->columnsDatatable()->where('name', 'causer_type')->update(['label' => "Causer Type"]);
    }
};
